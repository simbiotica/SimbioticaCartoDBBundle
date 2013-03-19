<?php

namespace Simbiotica\CartoDBBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('simbiotica_carto_db');

        $rootNode
            ->children()
                ->arrayNode('connections')
                    ->isRequired()
                    ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                    ->children()
                        ->booleanNode('private')->isRequired()->defaultValue(true)->end()
                        ->scalarNode('subdomain')->isRequired()->end()
                        ->scalarNode('key')->isRequired()->end()
                        ->scalarNode('secret')->isRequired()->end()
                        ->scalarNode('email')->isRequired()->end()
                        ->scalarNode('password')->isRequired()->end()
                        
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
