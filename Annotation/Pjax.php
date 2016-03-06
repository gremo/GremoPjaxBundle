<?php

/*
 * This file is part of the pjax-bundle package.
 *
 * (c) Marco Polichetti <gremo1982@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gremo\PjaxBundle\Annotation;

/**
 * @Annotation
 * @Target({"CLASS","METHOD"})
 */
class Pjax
{
    /**
     * Pjax container selector.
     *
     * @var string
     */
    protected $container;

    /**
     * @var bool Whatever to filter the response (picking the container only) or not.
     */
    protected $filter;

    /**
     * Pjax version.
     *
     * @var string
     */
    protected $version;

    /**
     * @var array Disallowed keys for this annotation
     */
    protected static $disallowedKeys = array(
        'container'
    );

    public function __construct(array $values)
    {
        $this->merge($values);
    }

    /**
     * @param string $container
     */
    public function setContainer($container)
    {
        $this->container = $container;
    }

    /**
     * @return string
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * @param bool $filter
     */
    public function setFilter($filter)
    {
        $this->filter = $filter;
    }

    /**
     * @return bool
     */
    public function getFilter()
    {
        return $this->filter;
    }

    /**
     * @param string $version
     */
    public function setVersion($version)
    {
        $this->version = $version;
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return array_diff_key(get_object_vars($this), array_flip(self::$disallowedKeys));
    }

    /**
     * Merge values into current instance attributes, preserving not null source values if specified.
     *
     * @param mixed $values
     * @param bool $overwriteNotNull
     */
    public function merge($values, $overwriteNotNull = true)
    {
        if ($values instanceof Pjax) {
            $values = $values->toArray();
        }

        if (!is_array($values)) {
            throw new \RuntimeException(sprintf('Expected array or Pjax object, got "%s".', gettype($values)));
        }

        // Merging using setters/getters
        foreach ($values as $key => $value) {
            if (in_array($key, self::$disallowedKeys)) {
                throw new \RuntimeException(sprintf('Key "%s" is not allowed for annotation "@%s".', $key, get_class($this)));
            }

            if (!method_exists($this, $setter = 'set'.ucfirst($key))) {
                throw new \RuntimeException(sprintf('Unknown key "%s" for annotation "@%s".', $key, get_class($this)));
            }

            if (!$overwriteNotNull && method_exists($this, $getter = 'get'.ucfirst($key))) {
                if (null !== $this->$getter()) {
                    continue;
                }
            }

            $this->$setter($value);
        }
    }
}
