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
            ->append($this->getAnnotationNode('orm'))
            ->children()
                ->arrayNode('connections')
                    ->isRequired()
                    ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                    ->children()
                        ->booleanNode('private')->isRequired()->defaultValue(true)->end()
                        ->scalarNode('subdomain')->isRequired()->end()
                        ->scalarNode('api_key')->end()
                        ->scalarNode('consumer_key')->end()
                        ->scalarNode('consumer_secret')->end()
                        ->scalarNode('email')->end()
                        ->scalarNode('password')->end()
                        
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
    
    /**
     * @param string $name
     */
    private function getAnnotationNode($name)
    {
        $treeBuilder = new TreeBuilder();
        $node = $treeBuilder->root($name);
    
        $node
        ->useAttributeAsKey('id')
            ->prototype('array')
            ->performNoDeepMerging()
            ->children()
                ->booleanNode('cartodblink')->defaultFalse()->end()
            ->end()
        ->end()
        ;
    
        return $node;
    }
}
