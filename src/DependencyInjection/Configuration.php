<?php

declare(strict_types=1);

/**
 * This source file is available under the terms of the MIT License.
 * Full copyright and license information is available in
 * LICENSE.txt which is distributed with this source code.
 *
 * @copyright Copyright (c) Basilicom GmbH (https://basilicom.de)
 * @license   MIT
 */

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

        $rootNode
            ->children()
                ->scalarNode('token')
                    ->info('Shared secret required to reach the endpoint. Null leaves it open to anyone.')
                    ->defaultNull()
                    ->beforeNormalization()
                        ->ifString()
                        ->then(static fn (string $token): ?string => trim($token) === '' ? null : trim($token))
                    ->end()
                    ->validate()
                        ->ifTrue(static fn (mixed $token): bool => is_string($token) && strlen($token) < 16)
                        ->thenInvalid('The health check token must be at least 16 characters long.')
                    ->end()
                ->end()
                ->integerNode('timeout_ms')
                    ->info('Budget for a whole run. Past it no further check is started.')
                    ->min(100)
                    ->defaultValue(5000)
                ->end()
                ->arrayNode('checks')
                    ->info('PimcoreConfigurationCheck is not listed - it cannot be disabled.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('database')
                            ->info('Database reachable and the users table populated.')
                            ->canBeDisabled()
                        ->end()
                        ->arrayNode('database_latency')
                            ->info('Round trip time of a SELECT 1. Off by default: a load spike must not page anyone.')
                            ->canBeEnabled()
                            ->children()
                                ->integerNode('threshold_ms')
                                    ->info('Round trip time above which the database counts as broken.')
                                    ->min(1)
                                    ->defaultValue(1000)
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('admin_user')
                            ->info('Fails while the named account exists and is active.')
                            ->canBeDisabled()
                            ->children()
                                ->scalarNode('user_name')
                                    ->info('The account to look for. Projects that renamed it must say so here.')
                                    ->cannotBeEmpty()
                                    ->defaultValue('admin')
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('filesystem')
                            ->info('Pimcore temporary directory readable and writeable.')
                            ->canBeDisabled()
                        ->end()
                        ->arrayNode('cache')
                            ->info('Pimcore cache pool stores and returns a value.')
                            ->canBeDisabled()
                        ->end()
                        ->arrayNode('robots_txt')
                            ->info('robots.txt present, readable and not disallowing the whole domain.')
                            ->canBeDisabled()
                        ->end()
                        ->arrayNode('asset_storage')
                            ->info('Writes and reads back a probe in the Pimcore asset storage.')
                            ->canBeEnabled()
                        ->end()
                        ->arrayNode('pending_migrations')
                            ->info('Warns while Doctrine migrations are waiting to be executed.')
                            ->canBeEnabled()
                        ->end()
                        ->arrayNode('disk_space')
                            ->info('Free space on the Pimcore temporary directory.')
                            ->canBeEnabled()
                            ->children()
                                ->integerNode('warning_below_percent')->min(1)->max(100)->defaultValue(20)->end()
                                ->integerNode('failure_below_percent')->min(1)->max(100)->defaultValue(5)->end()
                            ->end()
                            ->validate()
                                ->ifTrue(static fn (array $v): bool => $v['failure_below_percent'] > $v['warning_below_percent'])
                                ->thenInvalid('failure_below_percent must not be higher than warning_below_percent.')
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
