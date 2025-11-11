<?php

namespace Basilicom\PimcorePluginHealthCheck\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('pimcore_plugin_health_check');
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        $rootNode->children()
            ->booleanNode('cache_check_enabled')
                ->info('Set to false to disable the cache check.')
                ->defaultTrue()
            ->end()
            ->booleanNode('database_check_enabled')
                ->info('Set to false to disable the database check.')
                ->defaultTrue()
            ->end()
            ->booleanNode('filesystem_check_enabled')
                ->info('Set to false to disable the filesystem check.')
                ->defaultTrue()
            ->end()
            ->booleanNode('robots_txt_check_enabled')
                ->info('Set to false to disable the robots.txt check.')
                ->defaultTrue()
            ->end()
        ;

        return $treeBuilder;
    }
}
