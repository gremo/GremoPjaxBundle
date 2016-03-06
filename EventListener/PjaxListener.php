<?php

/*
 * This file is part of the pjax-bundle package.
 *
 * (c) Marco Polichetti <gremo1982@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gremo\PjaxBundle\EventListener;

use Doctrine\Common\Annotations\Reader;
use Gremo\PjaxBundle\Annotation\Pjax;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

/**
 * The main listener of this bundle.
 */
class PjaxListener
{
    // Known pjax request/response headers
    const PJAX_HEADER = 'X-PJAX';
    const PJAX_HEADER_CONTAINER = 'X-PJAX-Container';
    const PJAX_HEADER_VERSION = 'X-PJAX-Version';

    /**
     * @var array The default map from pjax headers to request attribute keys
     */
    public static $defaultAttributeMap = array(
        self::PJAX_HEADER => '_isPjax',
        self::PJAX_HEADER_CONTAINER => '_pjaxContainer',
    );

    /**
     * @var Reader
     */
    private $reader;

    /**
     * @var array The map from pjax headers to request attribute keys
     */
    private $attributeMap;

    /**
     * @var array The pjax default values for Pjax annotation
     */
    private $pjaxDefaults;

    public function __construct(Reader $reader, array $attributeMap = array(), array $pjaxDefaults = array())
    {
        $this->reader = $reader;
        $this->attributeMap = array_replace(self::$defaultAttributeMap, $attributeMap);
        $this->pjaxDefaults = $pjaxDefaults;
    }

    /**
     * Sets the request attributes if current request is a pjax request.
     *
     * @param FilterControllerEvent $event
     */
    public function injectRequestAttributes(FilterControllerEvent $event)
    {
        // Ignore sub-requests and closure controllers
        if (!$event->isMasterRequest() || !is_array($event->getController())) {
            return;
        }

        $request = $event->getRequest();

        $request->attributes->set($this->attributeMap[self::PJAX_HEADER], $request->headers->has(self::PJAX_HEADER));
        $request->attributes->set(
            $this->attributeMap[self::PJAX_HEADER_CONTAINER],
            $request->headers->get(self::PJAX_HEADER_CONTAINER)
        );
    }

    /**
     * Sets the request "_pjax" attribute to the configured Pjax object if controller has the Pjax annotation and if
     * request is a pjax request.
     *
     * @param FilterControllerEvent $event
     */
    public function onKernelController(FilterControllerEvent $event)
    {
        // Ignore sub-requests and closure controllers
        if (!$event->isMasterRequest() || !is_array($contoller = $event->getController())) {
            return;
        }

        $request = $event->getRequest();

        // Check if request is pjax
        if (!$request->headers->has(self::PJAX_HEADER)) {
            return;
        }

        // Get pjax configuration (if any) from controller/action
        $pjax = $this->getPjaxConfiguration($contoller[0], $contoller[1]);
        if (!$pjax) {
            return;
        }

        // Set container selector and request attribute
        $pjax->setContainer($request->headers->get(self::PJAX_HEADER_CONTAINER));

        $request->attributes->set('_pjax', $pjax);
    }

    /**
     * Filter the successful text/html response picking only the pjax container HTML if request attribute "_pjax"
     * contains a Pjax object.
     *
     * @param FilterResponseEvent $event
     */
    public function filterResponse(FilterResponseEvent $event)
    {
        // Ignore sub-requests
        if (!$event->isMasterRequest()) {
            return;
        }

        $request = $event->getRequest();

        // If the request was not pjax then the attribute doesn't exist
        $pjax = $request->attributes->get('_pjax');
        if (null === $pjax) {
            return;
        }

        // At this point we need an instance of Pjax
        if (!($pjax instanceof Pjax)) {
            throw new \InvalidArgumentException('Request attribute "_pjax" is reserved for @Pjax annotations.');
        }

        // Ignore unsuccessful and not text/html response
        $response = $event->getResponse();
        if (200 !== $response->getStatusCode() || 0 !== strpos($response->headers->get('Content-Type'), 'text/html')) {
            return;
        }

        // Set response headers
        if (null !== $version = $pjax->getVersion()) {
            $response->headers->set(self::PJAX_HEADER_VERSION, $version);
        }

        // Set response HTML
        $this->setResponseHTML($response, $pjax);
    }

    /**
     * @param Response $response
     * @param Pjax $configuration
     */
    private function setResponseHTML(Response $response, Pjax $configuration)
    {
        $crawler = new Crawler($response->getContent());

        // Do not alter response HTML letting pjax take care of updating the title and strip out scripts
        if (!$configuration->getFilter()) {
            return;
        }

        // Filter out the HTML selecting only the pjax container
        $container = $crawler->filter($configuration->getContainer());
        if (!$container->count()) {
            return;
        }

        // Get the container inner HTML
        $html = $container->html();

        // Copy the entire title tag HTML into fragment HTML, pjax will take care of removing the title
        $title = $crawler->filter('title');
        if ($title->count()) {
            $html = $title->getNode(0)->ownerDocument->saveHTML($title->getNode(0)).$html;
        }

        // Set response content to the pjax container HTML
        $response->setContent($html);
    }

    /**
     * @param object $controller
     * @param string $method
     * @return bool|Pjax
     */
    private function getPjaxConfiguration($controller, $method)
    {
        $object = new \ReflectionClass($controller);
        $method = new \ReflectionMethod($controller, $method);

        // Look for Pjax annotations at class level
        foreach ($this->reader->getClassAnnotations($object) as $classAnnotation) {
            if ($classAnnotation instanceof Pjax) {
                // Look for Pjax annotations at method level
                foreach ($this->reader->getMethodAnnotations($method) as $methodAnnotation) {
                    if ($methodAnnotation instanceof Pjax) {
                        // Merge method configuration into class configuration overwriting not-null values
                        $classAnnotation->merge($methodAnnotation, true);

                        break;
                    }
                }

                // Merge pjax defaults into class configuration without overwriting not-null values
                $classAnnotation->merge($this->pjaxDefaults, false);

                return $classAnnotation;
            }
        }

        // Look for Pjax annotations at method level
        foreach ($this->reader->getMethodAnnotations($method) as $methodAnnotation) {
            if ($methodAnnotation instanceof Pjax) {
                // Merge pjax defaults into method configuration without overwriting not-null values
                $methodAnnotation->merge($this->pjaxDefaults, false);

                return $methodAnnotation;
            }
        }

        return false;
    }
}
