<?php

/*
 * This file is part of the CsaGuzzleBundle package
 *
 * (c) Charles Sarrazin <charles@sarraz.in>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code
 */

namespace Csa\Bundle\GuzzleBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Csa Guzzle Extension
 *
 * @author Charles Sarrazin <charles@sarraz.in>
 */
class CsaGuzzleExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));

        $loader->load('subscribers.xml');
        $loader->load('collector.xml');
        $loader->load('twig.xml');
        $loader->load('factory.xml');

        $dataCollector = $container->getDefinition('csa_guzzle.data_collector.guzzle');
        $dataCollector->addArgument($config['profiler']['max_body_size']);

        if (!$config['profiler']['enabled']) {
            $container->removeDefinition('csa_guzzle.subscriber.debug');
            $container->removeDefinition('csa_guzzle.subscriber.stopwatch');
            $container->removeDefinition('csa_guzzle.data_collector.guzzle');
            $container->removeDefinition('csa_guzzle.twig.extension');
        }

        if (!$config['logger']) {
            $container->removeDefinition('csa_guzzle.subscriber.logger');
        }

        $this->processCacheConfiguration($config, $container);

        $definition = $container->getDefinition('csa_guzzle.client_factory');
        $definition->replaceArgument(0, $config['factory_class']);

        $this->processClientsConfiguration($config, $container, $definition);
    }

    private function processCacheConfiguration(array $config, ContainerBuilder $container)
    {
        if (!$config['cache']['enabled']) {
            $container->removeDefinition('csa_guzzle.subscriber.cache');

            return;
        }

        $id = sprintf(
            'csa_guzzle.cache.adapter.%s',
            $config['cache']['type']
        );

        if (!$cacheService = $config['cache']['service']) {
            throw new \InvalidArgumentException('The "service" node is mandatory if the cache is enabled');
        }

        $adapter = $container->getDefinition($id);
        $adapter->addArgument(new Reference($cacheService));
        $container->setAlias('csa_guzzle.default_cache_adapter', $id);
    }

    private function processClientsConfiguration(array $config, ContainerBuilder $container, Definition $clientFactory)
    {
        foreach ($config['clients'] as $name => $options) {
            $clientFactory->addMethodCall('registerClientConfiguration', [
                $name,
                $options['config'],
                $options['subscribers']]
            );
            $client = new DefinitionDecorator('csa_guzzle.client.abstract');
            $client->setFactoryService('csa_guzzle.client_factory');
            $client->setClass($config['factory_class']);
            $client->setFactoryMethod('createNamed');
            $client->setArguments([$name]);
            $container->setDefinition(sprintf('csa_guzzle.client.%s', $name), $client);
        }
    }
}
