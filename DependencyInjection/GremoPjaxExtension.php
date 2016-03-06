<?php

/*
 * This file is part of the pjax-bundle package.
 *
 * (c) Marco Polichetti <gremo1982@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gremo\PjaxBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\HttpKernel\KernelEvents;

class GremoPjaxExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

        $listenerId = 'gremo_pjax.event_listener.controller';
        $listener = $container->getDefinition($listenerId);

        if ($config['annotations']['enabled']) {
            if (isset($config['annotations']['defaults'])) {
                $listener->replaceArgument(2, $config['annotations']['defaults']);
            }

            $listener
                ->addTag('kernel.event_listener', array(
                    'event'  => KernelEvents::CONTROLLER,
                    'method' => 'onKernelController',
                ))
                ->addTag('kernel.event_listener', array(
                    'event' => KernelEvents::RESPONSE,
                    'method' => 'filterResponse',
                    'priority' => -32,
                ));
        }

        if ($config['controller_injection']['enabled']) {
            if (isset($config['controller_injection']['parameters'])) {
                $listener->replaceArgument(1, $config['controller_injection']['parameters']);
            }

            $listener
                ->addTag('kernel.event_listener', array(
                    'event'  => KernelEvents::CONTROLLER,
                    'method' => 'injectRequestAttributes',
                ));
        }

        // No tags means listener is unuseful so remove it
        if (!count($listener->getTags())) {
            $container->removeDefinition($listenerId);
        }
    }
}
