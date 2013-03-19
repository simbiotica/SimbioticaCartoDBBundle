<?php

namespace Simbiotica\CartoDBBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class SimbioticaCartoDBExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);
        
        if (!empty($config['connections']) && is_array($config['connections'])) {
            foreach ($config['connections'] as $name => $connection) {
                $this->loadConnection($name, $connection, $container);
            }
        }
        
        $loader =  new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');
    }
    
    /**
     * Loads a configured DBAL connection.
     *
     * @param string           $name       The name of the connection
     * @param array            $connection A dbal connection configuration.
     * @param ContainerBuilder $container  A ContainerBuilder instance
     */
    protected function loadConnection($name, array $connection, ContainerBuilder $container)
    {
        $configuration = $container->setDefinition(sprintf('simbiotica.cartodb_connection.%s', $name), new DefinitionDecorator('cartodb_connection'));
        if ($connection['private'])
        {
            $configuration->setArguments(array(
                $connection['key'],
                $connection['secret'],
                $connection['subdomain'],
                $connection['email'],
                $connection['password']
           ));
        }
        else
        {
            $configuration->setArguments(array(
                $connection['key'],
                $connection['secret'],
                $connection['subdomain'],
                $connection['email'],
                $connection['password']
           ));
        }
    }
}
