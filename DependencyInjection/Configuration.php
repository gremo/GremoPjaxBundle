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

use Gremo\PjaxBundle\EventListener\PjaxListener;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('gremo_pjax');

        $this->addAnnotations($rootNode);
        $this->addControllerInjection($rootNode);

        return $treeBuilder;
    }

    /**
     * Adds the "annotations" section to the configuration.
     *
     * @param ArrayNodeDefinition $node
     */
    private function addAnnotations(ArrayNodeDefinition $node)
    {
        $node
            ->children()
                ->arrayNode('annotations')
                    ->addDefaultsIfNotSet()
                    ->treatFalseLike(array('enabled' => false))
                    ->treatNullLike(array('enabled' => true))
                    ->treatTrueLike(array('enabled' => true))
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->arrayNode('defaults')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('version')->defaultNull()->end()
                                ->booleanNode('filter')->defaultTrue()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * Adds the "controller_injection" section to the configuration.
     *
     * @param ArrayNodeDefinition $node
     */
    private function addControllerInjection(ArrayNodeDefinition $node)
    {
        $parametersNode = $node
            ->children()
                ->arrayNode('controller_injection')
                    ->addDefaultsIfNotSet()
                    ->treatFalseLike(array('enabled' => false))
                    ->treatNullLike(array('enabled' => true))
                    ->treatTrueLike(array('enabled' => true))
                    ->validate()
                        ->always(function ($v) {
                            if ($v['enabled'] && isset($v['parameters'])) {
                                foreach ($v['parameters'] as $key => $value) {
                                    if ('_pjax' === $value) {
                                        throw new InvalidConfigurationException(sprintf(
                                            'The value of "%s" parameter must be not equal to "_pjax".',
                                            $key
                                        ));
                                    }
                                }
                            }

                            return $v;
                        })
                    ->end()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->arrayNode('parameters')
                            ->beforeNormalization()
                                ->always(function ($v) {
                                    foreach ($v as $key => $value) {
                                        unset($v[$key]);
                                        $v[str_replace('_', '-', $key)] = $value;
                                    }

                                    return $v;
                                })
                            ->end()
                            ->children();

        foreach (PjaxListener::$defaultAttributeMap as $key => $value) {
            $parametersNode
                ->scalarNode($key)
                    ->defaultValue($value)
                    ->cannotBeEmpty()
                ->end();
        }
    }
}
