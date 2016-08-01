<?php

namespace Simbiotica\CartoBundle\DependencyInjection;

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
class SimbioticaCartoExtension extends Extension
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

        foreach ($config['orm'] as $name => $listeners) {
            foreach ($listeners as $ext => $enabled) {
                $listener = sprintf('carto.listener.%s', $ext);
                if ($enabled && $container->hasDefinition($listener)) {
                    $attributes = array('connection' => $name);
                    $definition = $container->getDefinition($listener);
                    $definition->addTag('doctrine.event_subscriber', $attributes);
                }
            }

            $this->entityManagers[$name] = $listeners;
        }

        if (!empty($config['connections']) && is_array($config['connections'])) {
            foreach ($config['connections'] as $name => $connection) {
                $this->loadConnection($name, $connection, $container);
            }
        }


    }

    /**
     * Loads a configured DBAL connection.
     *
     * @param string $name The name of the connection
     * @param array $connection A dbal connection configuration.
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function loadConnection($name, array $connection, ContainerBuilder $container)
    {
        if ($connection['private']) {
            $container->setDefinition(
                sprintf('simbiotica.carto_connection.%s', $name),
                new DefinitionDecorator('carto_connection_private')
            )
                ->setArguments(
                    array(
                        $connection['subdomain'],
                        array_key_exists('api_key', $connection) ? $connection['api_key'] : null,
                        array_key_exists('consumer_key', $connection) ? $connection['consumer_key'] : null,
                        array_key_exists('consumer_secret', $connection) ? $connection['consumer_secret'] : null,
                        $connection['email'],
                        $connection['password'],
                    )
                );
        } else {
            $container->setDefinition(
                sprintf('simbiotica.carto_connection.%s', $name),
                new DefinitionDecorator('carto_connection_public')
            )
                ->setArguments(
                    array(
                        $connection['subdomain'],
                    )
                );
        }
    }
}
