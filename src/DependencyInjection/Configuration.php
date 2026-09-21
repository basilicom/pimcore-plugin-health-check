<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    /**
     * The boolean flags of bundle version 1.x, mapped onto their `checks.<name>` node.
     */
    public const LEGACY_FLAGS = [
        'cache_check_enabled' => 'cache',
        'database_check_enabled' => 'database_accessible',
        'filesystem_check_enabled' => 'filesystem',
        'robots_txt_check_enabled' => 'robots_txt',
    ];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('pimcore_plugin_health_check');
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        $children = $rootNode->children();

        $children
            ->arrayNode('dashboard')
                ->addDefaultsIfNotSet()
                ->children()
                    ->booleanNode('enabled')->defaultTrue()->end()
                    ->scalarNode('path')->defaultValue('/health-check')->cannotBeEmpty()->end()
                ->end()
            ->end();

        $children
            ->arrayNode('api')
                ->addDefaultsIfNotSet()
                ->children()
                    ->booleanNode('enabled')->defaultTrue()->end()
                    ->scalarNode('path')->defaultValue('/health-check/api')->cannotBeEmpty()->end()
                    ->scalarNode('api_key')
                        ->info('Empty means: only a logged in Pimcore admin user may access the endpoints.')
                        ->defaultValue('%env(default::HEALTH_CHECK_API_KEY)%')
                    ->end()
                ->end()
            ->end();

        $children
            ->arrayNode('external_lookups')
                ->info('Lookups against third party services such as endoflife.date.')
                ->addDefaultsIfNotSet()
                ->children()
                    ->booleanNode('enabled')->defaultTrue()->end()
                    ->integerNode('timeout')->min(1)->defaultValue(3)->end()
                    ->integerNode('cache_ttl')->min(0)->defaultValue(86400)->end()
                ->end()
            ->end();

        $checks = $children->arrayNode('checks')->addDefaultsIfNotSet()->children();

        $this->appendCheck($checks, 'pimcore_configuration');
        $this->appendCheck($checks, 'database_accessible');
        $this->appendCheck($checks, 'filesystem');
        $this->appendCheck($checks, 'cache');
        $this->appendCheck($checks, 'robots_txt', ['path' => null]);

        $this->appendCheck($checks, 'app_environment', ['environment' => '%kernel.environment%']);
        $this->appendCheck($checks, 'php_version', ['version' => null, 'operator' => '>=']);
        $this->appendCheck($checks, 'mysql_version', ['version' => '10.5', 'operator' => '>=']);
        $this->appendCheck($checks, 'https_connection');
        $this->appendCheck($checks, 'doctrine_migrations');

        $this->appendCheck($checks, 'disk_usage', [
            'warning_threshold' => 90,
            'critical_threshold' => 95,
            'path' => '%kernel.project_dir%',
        ]);
        $this->appendCheck($checks, 'hosting_size', [
            'warning_threshold' => 48318382080,
            'critical_threshold' => 53687091200,
            'path' => '%kernel.project_dir%',
        ]);
        $this->appendCheck($checks, 'database_size', [
            'warning_threshold' => 9878424780,
            'critical_threshold' => 10737418240,
        ]);
        $this->appendCheck($checks, 'database_table_size', [
            'warning_threshold' => 943718400,
            'critical_threshold' => 1073741824,
        ]);
        $this->appendCheck($checks, 'thumbnail_storage', [
            'warning_threshold' => null,
            'critical_threshold' => null,
            'path' => '%kernel.project_dir%/public/var/tmp/thumbnails',
        ]);

        $this->appendCheck($checks, 'pimcore_version');
        $this->appendCheck($checks, 'pimcore_bundles');
        $this->appendCheck($checks, 'pimcore_areabricks');
        $this->appendCheck($checks, 'pimcore_users');
        $this->appendCheck($checks, 'pimcore_element_count', [
            'warning_threshold' => 100000,
            'critical_threshold' => 150000,
        ]);

        $this->appendCheck($checks, 'maintenance_last_run', [
            'warning_threshold' => 120,
            'critical_threshold' => 1440,
        ]);
        $this->appendCheck($checks, 'messenger_message_count', [
            'warning_threshold' => 500,
            'critical_threshold' => 1000,
        ]);
        $this->appendCheck($checks, 'messenger_message_age', [
            'warning_threshold' => 60,
            'critical_threshold' => 180,
        ]);
        $this->appendCheck($checks, 'failed_messages', [
            'warning_threshold' => 1,
            'critical_threshold' => 50,
        ]);

        $this->appendCheck($checks, 'log_file_errors', [
            'log_dir' => '%kernel.logs_dir%',
            'files' => ['prod.log', 'php.log'],
            'lookback_hours' => 24,
            'warning_threshold' => 10,
            'critical_threshold' => 50,
            'max_bytes' => 5242880,
        ]);
        $this->appendCheck($checks, 'application_log_errors', [
            'lookback_hours' => 24,
            'warning_threshold' => 10,
            'critical_threshold' => 50,
        ]);

        $this->appendCheck($checks, 'composer_audit', [
            'timeout' => 60,
            'composer_binary' => 'composer',
            'working_directory' => '%kernel.project_dir%',
        ]);
        $this->appendCheck($checks, 'composer_outdated', [
            'timeout' => 60,
            'composer_binary' => 'composer',
            'working_directory' => '%kernel.project_dir%',
            'warning_threshold' => 10,
            'critical_threshold' => 25,
        ]);
        $this->appendCheck($checks, 'inactive_admin_users', [
            'days' => 90,
            'warning_threshold' => 1,
            'critical_threshold' => null,
        ]);
        $this->appendCheck($checks, 'users_without_2fa', [
            'warning_threshold' => 1,
            'critical_threshold' => null,
        ]);
        $this->appendCheck($checks, 'debug_mode');

        $this->appendCheck($checks, 'versions_table_bloat', [
            'warning_threshold' => 500000,
            'critical_threshold' => 1000000,
        ]);
        $this->appendCheck($checks, 'largest_tables', ['limit' => 10]);

        $this->appendCheck($checks, 'dataobject_classes');
        $this->appendCheck($checks, 'assets_storage', [
            'warning_threshold' => null,
            'critical_threshold' => null,
            'storage_path' => '%kernel.project_dir%/var/assets',
        ]);
        $this->appendCheck($checks, 'objects_per_class', [
            'limit' => 10,
            'warn_on_empty_classes' => false,
        ]);
        $this->appendCheck($checks, 'documents_by_type');
        $this->appendCheck($checks, 'stale_objects', [
            'days' => 365,
            'warning_threshold' => null,
            'critical_threshold' => null,
        ]);
        $this->appendCheck($checks, 'unpublished_objects', [
            'warning_threshold' => null,
            'critical_threshold' => null,
        ]);
        $this->appendCheck($checks, 'assets_without_metadata', [
            'asset_types' => ['image'],
            'metadata_names' => [],
            'warning_threshold' => null,
            'critical_threshold' => null,
        ]);
        $this->appendCheck($checks, 'asset_types', [
            'limit' => 10,
            'disallowed_extensions' => ['exe', 'bat', 'cmd', 'com', 'msi', 'dll', 'sh', 'ps1', 'scr'],
        ]);
        $this->appendCheck($checks, 'custom_templates', [
            'templates_dir' => '%kernel.project_dir%/templates',
        ]);
        $this->appendCheck($checks, 'documents_missing_seo', [
            'published_only' => true,
            'warning_threshold' => null,
            'critical_threshold' => null,
        ]);

        $checks->end()->end();

        foreach (self::LEGACY_FLAGS as $flag => $checkKey) {
            $children
                ->booleanNode($flag)
                    ->info(sprintf('Alias for checks.%s.enabled.', $checkKey))
                    ->defaultNull()
                ->end();
        }

        $children->end();

        return $treeBuilder;
    }

    /**
     * @param array<string, mixed> $options option name => default value; the value type
     *                                      determines the node type
     */
    private function appendCheck(NodeBuilder $checks, string $name, array $options = [], bool $enabled = true): void
    {
        $check = $checks->arrayNode($name)->addDefaultsIfNotSet()->children();
        $check->booleanNode('enabled')->defaultValue($enabled)->end();

        foreach ($options as $option => $default) {
            if (is_bool($default)) {
                $check->booleanNode($option)->defaultValue($default)->end();
            } elseif (is_int($default)) {
                $check->integerNode($option)->defaultValue($default)->end();
            } elseif (is_float($default)) {
                $check->floatNode($option)->defaultValue($default)->end();
            } elseif (is_array($default)) {
                $check->arrayNode($option)->scalarPrototype()->end()->defaultValue($default)->end();
            } else {
                $check->scalarNode($option)->defaultValue($default)->end();
            }
        }

        $check->end()->end();
    }
}
