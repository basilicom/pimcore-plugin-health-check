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
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('pimcore_plugin_health_check');
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        $root = $rootNode->children();

        $this->secret($root->scalarNode('token'), 'Shared secret required to reach the endpoint. Null leaves it open to anyone.', 'token');

        $root
            ->integerNode('timeout_ms')
                ->info('Budget for a whole run. Past it no further check is started.')
                ->min(100)
                ->defaultValue(5000)
            ->end();

        $dashboard = $root->arrayNode('dashboard')
            ->info('The System Health Status page and its JSON API. Both read the stored audit run, both are never public.')
            ->addDefaultsIfNotSet()
            ->children();
        $dashboard->booleanNode('enabled')->defaultTrue()->end();
        $dashboard->scalarNode('path')->cannotBeEmpty()->defaultValue('/health-check')->end();
        $dashboard->scalarNode('api_path')->cannotBeEmpty()->defaultValue('/health-check/api')->end();
        $this->secret(
            $dashboard->scalarNode('api_key'),
            'Grants access without a Pimcore admin session, as the X-Health-Check-Api-Key header. A separate secret from token on purpose.',
            'API key'
        );

        $audit = $root->arrayNode('audit')
            ->info('The audit run started by basilicom:health-check:audit, meant for cron.')
            ->addDefaultsIfNotSet()
            ->children();
        $audit->integerNode('timeout_ms')->info('Budget for the whole audit run.')->min(1000)->defaultValue(120000)->end();
        $audit->integerNode('directory_size_timeout_s')->info('How long du may take per directory before the size counts as n/a.')->min(1)->defaultValue(30)->end();

        $lookups = $root->arrayNode('external_lookups')
            ->info('Version data from endoflife.date. Cached, bounded, and never a failure when unreachable.')
            ->addDefaultsIfNotSet()
            ->children();
        $lookups->booleanNode('enabled')->defaultTrue()->end();
        $lookups->integerNode('timeout_s')->min(1)->defaultValue(3)->end();
        $lookups->integerNode('cache_ttl_s')->min(0)->defaultValue(86400)->end();
        $lookups->integerNode('failure_cache_ttl_s')->info('How long a failed lookup is remembered, so an offline host does not pay the timeout on every run.')->min(0)->defaultValue(600)->end();

        $messenger = $root->arrayNode('messenger')
            ->addDefaultsIfNotSet()
            ->children();
        $messenger->arrayNode('failed_queue_names')
            ->info('Transports that hold dead messages. Any queue named *_failed counts as well.')
            ->scalarPrototype()->end()
            ->defaultValue(['failed'])
        ->end();

        $checks = $root->arrayNode('checks')
            ->info('PimcoreConfigurationCheck is not listed - it cannot be disabled.')
            ->addDefaultsIfNotSet()
            ->children();

        // --- core checks: run on every request to /health-check-status ---
        $checks->arrayNode('database')->info('Database reachable and the users table populated.')->canBeDisabled()->end();
        $checks->arrayNode('database_latency')
            ->info('Round trip time of a SELECT 1. Off by default: a load spike must not page anyone.')
            ->canBeEnabled()
            ->children()
                ->integerNode('threshold_ms')->info('Round trip time above which the database counts as broken.')->min(1)->defaultValue(1000)->end()
            ->end()
        ->end();
        $checks->arrayNode('admin_user')
            ->info('Fails while the named account exists and is active.')
            ->canBeDisabled()
            ->children()
                ->scalarNode('user_name')->info('The account to look for. Projects that renamed it must say so here.')->cannotBeEmpty()->defaultValue('admin')->end()
            ->end()
        ->end();
        $checks->arrayNode('filesystem')->info('Pimcore temporary directory readable and writeable.')->canBeDisabled()->end();
        $checks->arrayNode('cache')->info('Pimcore cache pool stores and returns a value.')->canBeDisabled()->end();
        $checks->arrayNode('robots_txt')->info('robots.txt present, readable and not disallowing the whole domain.')->canBeDisabled()->end();
        $checks->arrayNode('asset_storage')->info('Writes and reads back a probe in the Pimcore asset storage.')->canBeEnabled()->end();
        $checks->arrayNode('pending_migrations')->info('Warns while Doctrine migrations are waiting to be executed.')->canBeEnabled()->end();
        $checks->arrayNode('disk_space')
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
        ->end();
        $checks->arrayNode('https_connection')->info('Warns when the request that runs the checks arrived over plain HTTP. Skipped on the CLI.')->canBeDisabled()->end();

        // --- audit checks: run by basilicom:health-check:audit, shown on the dashboard ---
        $node = $checks->arrayNode('app_environment')->canBeDisabled()->children();
        $node->scalarNode('environment')->info('The environment the application is expected to run in.')->cannotBeEmpty()->defaultValue('%kernel.environment%')->end();

        foreach (['php_version' => 'PHP', 'mysql_version' => 'MySQL/MariaDB'] as $name => $subject) {
            $node = $checks->arrayNode($name)->canBeDisabled()->children();
            $node->scalarNode('version')->info(sprintf('%s version to require. Null grades against the release cycles on endoflife.date instead.', $subject))->defaultNull()->end();
            $node->scalarNode('operator')->defaultValue('>=')->end();
        }

        $checks->arrayNode('doctrine_migrations')->info('Pending migrations and migrations recorded as executed that no longer exist in the code.')->canBeDisabled()->end();
        $checks->arrayNode('debug_mode')->info('Critical while kernel.debug is on in the prod environment.')->canBeDisabled()->end();

        $this->thresholds($checks->arrayNode('hosting_size')->info('Size of the project directory in bytes.')->canBeDisabled()->children(), 48318382080, 53687091200);
        $this->thresholds($checks->arrayNode('thumbnail_storage')->info('Size of the generated thumbnails in bytes.')->canBeDisabled()->children(), null, null);
        $this->thresholds($checks->arrayNode('database_size')->info('data_length + index_length of the schema, in bytes.')->canBeDisabled()->children(), 9878424780, 10737418240);
        $this->thresholds($checks->arrayNode('database_table_size')->info('Per table, in bytes; the message names the offending tables.')->canBeDisabled()->children(), 943718400, 1073741824);

        foreach (['pimcore_version', 'pimcore_bundles', 'pimcore_areabricks', 'pimcore_users', 'dataobject_classes', 'documents_by_type', 'custom_templates'] as $name) {
            $checks->arrayNode($name)->info('Informational.')->canBeDisabled()->end();
        }

        $this->thresholds($checks->arrayNode('pimcore_element_count')->info('Objects + assets + documents.')->canBeDisabled()->children(), 100000, 150000);

        $node = $checks->arrayNode('maintenance_last_run')->info('Age of the last Pimcore maintenance run, in minutes.')->canBeDisabled()->children();
        $this->threshold($node, 'warning_minutes', 120);
        $this->threshold($node, 'failure_minutes', 1440);

        $this->thresholds($checks->arrayNode('messenger_message_count')->info('Messages waiting in messenger_messages.')->canBeDisabled()->children(), 500, 1000);
        $this->thresholds($checks->arrayNode('messenger_message_age')->info('Age of the oldest waiting message, in minutes.')->canBeDisabled()->children(), 60, 180);
        $this->thresholds($checks->arrayNode('failed_messages')->info('Messages parked in a failure transport.')->canBeDisabled()->children(), 1, 50);

        $node = $checks->arrayNode('log_file_errors')->info('Error level lines in the log files, within the lookback window.')->canBeDisabled()->children();
        $node->arrayNode('files')->scalarPrototype()->end()->defaultValue(['prod.log', 'php.log'])->end();
        $node->integerNode('lookback_hours')->min(1)->defaultValue(24)->end();
        $node->integerNode('max_bytes')->info('Only this many bytes from the end of each file are scanned.')->min(0)->defaultValue(5242880)->end();
        $this->thresholds($node, 10, 50);

        $node = $checks->arrayNode('application_log_errors')->info('Error level rows in application_logs, within the lookback window.')->canBeDisabled()->children();
        $node->integerNode('lookback_hours')->min(1)->defaultValue(24)->end();
        $this->thresholds($node, 10, 50);

        foreach (['composer_audit' => 'Runs composer audit.', 'composer_outdated' => 'Runs composer outdated --direct.'] as $name => $info) {
            $node = $checks->arrayNode($name)->info($info . ' Off by default: needs the composer binary and network access on the host.')->canBeEnabled()->children();
            $node->scalarNode('binary')->cannotBeEmpty()->defaultValue('composer')->end();
            $node->integerNode('timeout_s')->min(1)->defaultValue(60)->end();

            if ($name === 'composer_outdated') {
                $this->thresholds($node, 10, 25);
            }
        }

        $node = $checks->arrayNode('inactive_admin_users')->info('Admin accounts without a login in the last N days.')->canBeDisabled()->children();
        $node->integerNode('days')->min(1)->defaultValue(90)->end();
        $this->thresholds($node, 1, null);

        $this->thresholds($checks->arrayNode('users_without_2fa')->info('Admin accounts without a TOTP secret.')->canBeDisabled()->children(), 1, null);
        $this->thresholds($checks->arrayNode('versions_table')->info('Rows in the versions table.')->canBeDisabled()->children(), 500000, 1000000);

        $node = $checks->arrayNode('largest_tables')->info('Informational: the largest tables of the schema.')->canBeDisabled()->children();
        $node->integerNode('limit')->min(1)->max(100)->defaultValue(10)->end();

        $this->thresholds($checks->arrayNode('assets_storage')->info('Asset count and storage size in bytes.')->canBeDisabled()->children(), null, null);

        $node = $checks->arrayNode('objects_per_class')->canBeDisabled()->children();
        $node->integerNode('limit')->min(1)->max(50)->defaultValue(10)->end();
        $node->booleanNode('warn_on_empty_classes')->defaultFalse()->end();

        $node = $checks->arrayNode('stale_objects')->info('Objects not modified for N days.')->canBeDisabled()->children();
        $node->integerNode('days')->min(1)->defaultValue(365)->end();
        $this->thresholds($node, null, null);

        $this->thresholds($checks->arrayNode('unpublished_objects')->info('Thresholds are percentages of all objects.')->canBeDisabled()->children(), null, null);

        $node = $checks->arrayNode('assets_without_metadata')->canBeDisabled()->children();
        $node->arrayNode('asset_types')->scalarPrototype()->end()->defaultValue(['image'])->end();
        $node->arrayNode('metadata_names')->info('Empty: count assets without any metadata. Otherwise: assets missing one of these.')->scalarPrototype()->end()->defaultValue([])->end();
        $this->thresholds($node, null, null);

        $node = $checks->arrayNode('asset_types')->info('MIME distribution; warns on assets with a disallowed extension.')->canBeDisabled()->children();
        $node->integerNode('limit')->min(1)->max(50)->defaultValue(10)->end();
        $node->arrayNode('disallowed_extensions')->scalarPrototype()->end()->defaultValue(['exe', 'bat', 'cmd', 'com', 'msi', 'dll', 'sh', 'ps1', 'scr'])->end();

        $node = $checks->arrayNode('documents_missing_seo')->info('Pages without a title or meta description.')->canBeDisabled()->children();
        $node->booleanNode('published_only')->defaultTrue()->end();
        $this->thresholds($node, null, null);

        return $treeBuilder;
    }

    /** A shared secret: trimmed, empty means none, and at least 16 characters when given literally. */
    private function secret(ScalarNodeDefinition $node, string $info, string $what): void
    {
        $node
            ->info($info)
            ->defaultNull()
            ->beforeNormalization()
                ->ifString()
                ->then(static fn (string $value): ?string => trim($value) === '' ? null : trim($value))
            ->end()
            ->validate()
                // '' is the placeholder Symfony validates a %env()% node against, never a real secret
                ->ifTrue(static fn (mixed $value): bool => is_string($value) && $value !== '' && strlen($value) < 16)
                ->thenInvalid(sprintf('The health check %s must be at least 16 characters long.', $what))
            ->end()
        ->end();
    }

    /** warning_threshold + failure_threshold, each numeric or null to switch that level off. */
    private function thresholds(NodeBuilder $node, int|float|null $warning, int|float|null $failure): NodeBuilder
    {
        $this->threshold($node, 'warning_threshold', $warning);
        $this->threshold($node, 'failure_threshold', $failure);

        return $node;
    }

    private function threshold(NodeBuilder $node, string $name, int|float|null $default): void
    {
        $node->scalarNode($name)
            ->defaultValue($default)
            ->validate()
                ->ifTrue(static fn (mixed $value): bool => $value !== null && !is_numeric($value))
                ->thenInvalid(sprintf('%s must be a number or null.', $name))
            ->end()
        ->end();
    }
}
