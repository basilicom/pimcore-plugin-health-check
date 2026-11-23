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

namespace Basilicom\PimcorePluginHealthCheck\Tests\Unit\DependencyInjection;

use Basilicom\PimcorePluginHealthCheck\DependencyInjection\Configuration;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase
{
    #[Test]
    public function processConfiguration_defaultsMatchTheDocumentedTree(): void
    {
        // prepare
        $processor = new Processor();

        // test
        $config = $processor->processConfiguration(new Configuration(), []);

        // verify
        $this->assertSame(
            [
                'token'      => null,
                'timeout_ms' => 5000,
                'dashboard'  => [
                    'enabled'  => true,
                    'path'     => '/health-check',
                    'api_path' => '/health-check/api',
                    'api_key'  => null,
                ],
                'audit' => [
                    'timeout_ms'               => 120000,
                    'directory_size_timeout_s' => 30,
                ],
                'external_lookups' => [
                    'enabled'             => true,
                    'timeout_s'           => 3,
                    'cache_ttl_s'         => 86400,
                    'failure_cache_ttl_s' => 600,
                ],
                'messenger' => [
                    'failed_queue_names' => ['failed'],
                ],
                'checks' => [
                    'database' => [
                        'enabled' => true,
                    ],
                    'database_latency' => [
                        'enabled'      => false,
                        'threshold_ms' => 1000,
                    ],
                    'admin_user' => [
                        'enabled'   => true,
                        'user_name' => 'admin',
                    ],
                    'filesystem' => [
                        'enabled' => true,
                    ],
                    'cache' => [
                        'enabled' => true,
                    ],
                    'robots_txt' => [
                        'enabled' => true,
                    ],
                    'asset_storage' => [
                        'enabled' => false,
                    ],
                    'pending_migrations' => [
                        'enabled' => false,
                    ],
                    'disk_space' => [
                        'enabled'               => false,
                        'warning_below_percent' => 20,
                        'failure_below_percent' => 5,
                    ],
                    'https_connection' => [
                        'enabled' => true,
                    ],
                    'app_environment' => [
                        'enabled'     => true,
                        'environment' => '%kernel.environment%',
                    ],
                    'php_version' => [
                        'enabled'  => true,
                        'version'  => null,
                        'operator' => '>=',
                    ],
                    'mysql_version' => [
                        'enabled'  => true,
                        'version'  => null,
                        'operator' => '>=',
                    ],
                    'doctrine_migrations' => [
                        'enabled' => true,
                    ],
                    'debug_mode' => [
                        'enabled' => true,
                    ],
                    'hosting_size' => [
                        'enabled'           => true,
                        'warning_threshold' => 48318382080,
                        'failure_threshold' => 53687091200,
                    ],
                    'thumbnail_storage' => [
                        'enabled'           => true,
                        'warning_threshold' => null,
                        'failure_threshold' => null,
                    ],
                    'database_size' => [
                        'enabled'           => true,
                        'warning_threshold' => 9878424780,
                        'failure_threshold' => 10737418240,
                    ],
                    'database_table_size' => [
                        'enabled'           => true,
                        'warning_threshold' => 943718400,
                        'failure_threshold' => 1073741824,
                    ],
                    'pimcore_version' => [
                        'enabled' => true,
                    ],
                    'pimcore_bundles' => [
                        'enabled' => true,
                    ],
                    'pimcore_areabricks' => [
                        'enabled' => true,
                    ],
                    'pimcore_users' => [
                        'enabled' => true,
                    ],
                    'dataobject_classes' => [
                        'enabled' => true,
                    ],
                    'documents_by_type' => [
                        'enabled' => true,
                    ],
                    'custom_templates' => [
                        'enabled' => true,
                    ],
                    'pimcore_element_count' => [
                        'enabled'           => true,
                        'warning_threshold' => 100000,
                        'failure_threshold' => 150000,
                    ],
                    'maintenance_last_run' => [
                        'enabled'         => true,
                        'warning_minutes' => 120,
                        'failure_minutes' => 1440,
                    ],
                    'messenger_message_count' => [
                        'enabled'           => true,
                        'warning_threshold' => 500,
                        'failure_threshold' => 1000,
                    ],
                    'messenger_message_age' => [
                        'enabled'           => true,
                        'warning_threshold' => 60,
                        'failure_threshold' => 180,
                    ],
                    'failed_messages' => [
                        'enabled'           => true,
                        'warning_threshold' => 1,
                        'failure_threshold' => 50,
                    ],
                    'log_file_errors' => [
                        'enabled'           => true,
                        'files'             => ['prod.log', 'php.log'],
                        'lookback_hours'    => 24,
                        'max_bytes'         => 5242880,
                        'warning_threshold' => 10,
                        'failure_threshold' => 50,
                    ],
                    'application_log_errors' => [
                        'enabled'           => true,
                        'lookback_hours'    => 24,
                        'warning_threshold' => 10,
                        'failure_threshold' => 50,
                    ],
                    'composer_audit' => [
                        'enabled'   => false,
                        'binary'    => 'composer',
                        'timeout_s' => 60,
                    ],
                    'composer_outdated' => [
                        'enabled'           => false,
                        'binary'            => 'composer',
                        'timeout_s'         => 60,
                        'warning_threshold' => 10,
                        'failure_threshold' => 25,
                    ],
                    'inactive_admin_users' => [
                        'enabled'           => true,
                        'days'              => 90,
                        'warning_threshold' => 1,
                        'failure_threshold' => null,
                    ],
                    'users_without_2fa' => [
                        'enabled'           => true,
                        'warning_threshold' => 1,
                        'failure_threshold' => null,
                    ],
                    'versions_table' => [
                        'enabled'           => true,
                        'warning_threshold' => 500000,
                        'failure_threshold' => 1000000,
                    ],
                    'largest_tables' => [
                        'enabled' => true,
                        'limit'   => 10,
                    ],
                    'assets_storage' => [
                        'enabled'           => true,
                        'warning_threshold' => null,
                        'failure_threshold' => null,
                    ],
                    'objects_per_class' => [
                        'enabled'               => true,
                        'limit'                 => 10,
                        'warn_on_empty_classes' => false,
                    ],
                    'stale_objects' => [
                        'enabled'           => true,
                        'days'              => 365,
                        'warning_threshold' => null,
                        'failure_threshold' => null,
                    ],
                    'unpublished_objects' => [
                        'enabled'           => true,
                        'warning_threshold' => null,
                        'failure_threshold' => null,
                    ],
                    'assets_without_metadata' => [
                        'enabled'           => true,
                        'asset_types'       => ['image'],
                        'metadata_names'    => [],
                        'warning_threshold' => null,
                        'failure_threshold' => null,
                    ],
                    'asset_types' => [
                        'enabled'               => true,
                        'limit'                 => 10,
                        'disallowed_extensions' => ['exe', 'bat', 'cmd', 'com', 'msi', 'dll', 'sh', 'ps1', 'scr'],
                    ],
                    'documents_missing_seo' => [
                        'enabled'           => true,
                        'published_only'    => true,
                        'warning_threshold' => null,
                        'failure_threshold' => null,
                    ],
                ],
            ],
            $config
        );
    }

    #[Test]
    public function processConfiguration_defaultsTimeoutMsTo5000(): void
    {
        // prepare
        $processor = new Processor();

        // test
        $config = $processor->processConfiguration(new Configuration(), []);

        // verify
        $this->assertSame(5000, $config['timeout_ms']);
    }

    #[Test]
    public function processConfiguration_allowsOverridingTimeoutMs(): void
    {
        // prepare
        $processor = new Processor();

        // test
        $config = $processor->processConfiguration(new Configuration(), [['timeout_ms' => 10000]]);

        // verify
        $this->assertSame(10000, $config['timeout_ms']);
    }

    #[Test]
    public function processConfiguration_rejectsATimeoutMsBelow100(): void
    {
        // prepare
        $processor = new Processor();

        // test / verify
        $this->expectException(InvalidConfigurationException::class);
        $processor->processConfiguration(new Configuration(), [['timeout_ms' => 99]]);
    }

    #[Test]
    public function processConfiguration_defaultsTokenToNull(): void
    {
        // prepare
        $processor = new Processor();

        // test
        $config = $processor->processConfiguration(new Configuration(), []);

        // verify
        $this->assertNull($config['token']);
    }

    #[Test]
    public function processConfiguration_keepsAValidTokenUnchanged(): void
    {
        // prepare
        $processor = new Processor();
        $token     = 'a-valid-token-of-16-chars-or-more';

        // test
        $config = $processor->processConfiguration(new Configuration(), [['token' => $token]]);

        // verify
        $this->assertSame($token, $config['token']);
    }

    #[Test]
    public function processConfiguration_normalizesAWhitespaceOnlyTokenToNull(): void
    {
        // prepare
        $processor = new Processor();

        // test
        $config = $processor->processConfiguration(new Configuration(), [['token' => "   \t  "]]);

        // verify
        $this->assertNull($config['token']);
    }

    #[Test]
    public function processConfiguration_normalizesAnEmptyTokenToNull(): void
    {
        // prepare
        $processor = new Processor();

        // test
        $config = $processor->processConfiguration(new Configuration(), [['token' => '']]);

        // verify
        $this->assertNull($config['token']);
    }

    #[Test]
    public function processConfiguration_trimsLeadingAndTrailingWhitespaceFromTheToken(): void
    {
        // prepare
        $processor = new Processor();
        $token     = 'a-valid-token-of-16-chars-or-more';

        // test
        $config = $processor->processConfiguration(new Configuration(), [['token' => "  {$token}  "]]);

        // verify
        $this->assertSame($token, $config['token']);
    }

    #[Test]
    public function processConfiguration_rejectsATokenShorterThan16CharactersAndDoesNotLeakItInTheMessage(): void
    {
        // prepare
        $processor = new Processor();
        $token     = 'geheim12345';

        // test / verify
        try {
            $processor->processConfiguration(new Configuration(), [['token' => $token]]);
            $this->fail('Expected an InvalidConfigurationException.');
        } catch (InvalidConfigurationException $exception) {
            $this->assertStringContainsString(
                'The health check token must be at least 16 characters long.',
                $exception->getMessage()
            );
            $this->assertStringNotContainsString($token, $exception->getMessage());
        }
    }

    #[Test]
    public function processConfiguration_allowsDisablingIndividualChecks(): void
    {
        // prepare
        $processor = new Processor();

        // test
        $config = $processor->processConfiguration(new Configuration(), [
            ['checks' => ['robots_txt' => false, 'cache' => false]],
        ]);

        // verify
        $this->assertFalse($config['checks']['robots_txt']['enabled']);
        $this->assertFalse($config['checks']['cache']['enabled']);
        $this->assertTrue($config['checks']['database']['enabled']);
        $this->assertTrue($config['checks']['admin_user']['enabled']);
        $this->assertTrue($config['checks']['filesystem']['enabled']);
    }

    #[Test]
    public function processConfiguration_acceptsTheBooleanShorthandForEveryCheck(): void
    {
        // prepare
        $processor = new Processor();

        // test
        $config = $processor->processConfiguration(new Configuration(), [
            [
                'checks' => [
                    'database'         => false,
                    'database_latency' => true,
                    'admin_user'       => false,
                    'filesystem'       => false,
                    'cache'            => false,
                    'robots_txt'       => false,
                ],
            ],
        ]);

        // verify
        $this->assertFalse($config['checks']['database']['enabled']);
        $this->assertTrue($config['checks']['database_latency']['enabled']);
        $this->assertFalse($config['checks']['admin_user']['enabled']);
        $this->assertFalse($config['checks']['filesystem']['enabled']);
        $this->assertFalse($config['checks']['cache']['enabled']);
        $this->assertFalse($config['checks']['robots_txt']['enabled']);
    }

    #[Test]
    public function processConfiguration_shorthandDatabaseLatencyKeepsTheDefaultThreshold(): void
    {
        // prepare
        $processor = new Processor();

        // test
        $config = $processor->processConfiguration(new Configuration(), [
            ['checks' => ['database_latency' => true]],
        ]);

        // verify
        $this->assertTrue($config['checks']['database_latency']['enabled']);
        $this->assertSame(1000, $config['checks']['database_latency']['threshold_ms']);
    }

    #[Test]
    public function processConfiguration_allowsOverridingTheDatabaseLatencyThreshold(): void
    {
        // prepare
        $processor = new Processor();

        // test
        $config = $processor->processConfiguration(new Configuration(), [
            ['checks' => ['database_latency' => ['enabled' => true, 'threshold_ms' => 250]]],
        ]);

        // verify
        $this->assertTrue($config['checks']['database_latency']['enabled']);
        $this->assertSame(250, $config['checks']['database_latency']['threshold_ms']);
    }

    #[Test]
    public function processConfiguration_rejectsADatabaseLatencyThresholdBelowOne(): void
    {
        // prepare
        $processor = new Processor();

        // test / verify
        $this->expectException(InvalidConfigurationException::class);
        $processor->processConfiguration(new Configuration(), [
            ['checks' => ['database_latency' => ['threshold_ms' => 0]]],
        ]);
    }

    #[Test]
    public function processConfiguration_rejectsANegativeDatabaseLatencyThreshold(): void
    {
        // prepare
        $processor = new Processor();

        // test / verify
        $this->expectException(InvalidConfigurationException::class);
        $processor->processConfiguration(new Configuration(), [
            ['checks' => ['database_latency' => ['threshold_ms' => -1]]],
        ]);
    }

    #[Test]
    public function processConfiguration_rejectsTheOldFlatKeysWithAnExplicitMessage(): void
    {
        // prepare
        $processor = new Processor();

        // test / verify
        try {
            $processor->processConfiguration(new Configuration(), [['database_check_enabled' => true]]);
            $this->fail('Expected an InvalidConfigurationException.');
        } catch (InvalidConfigurationException $exception) {
            // the enumeration of available options changes with every new top-level node, so it is not asserted here
            $this->assertStringContainsString('database_check_enabled', $exception->getMessage());
            $this->assertStringContainsString('Unrecognized option', $exception->getMessage());
        }
    }

    #[Test]
    public function processConfiguration_rejectsAnUnknownCheck(): void
    {
        // prepare
        $processor = new Processor();

        // test / verify
        $this->expectException(InvalidConfigurationException::class);
        $processor->processConfiguration(new Configuration(), [['checks' => ['unknown_check' => true]]]);
    }

    #[Test]
    public function processConfiguration_rejectsADiskSpaceFailureThresholdAboveTheWarningThreshold(): void
    {
        // prepare
        $processor = new Processor();

        // test / verify
        try {
            $processor->processConfiguration(new Configuration(), [
                ['checks' => ['disk_space' => ['warning_below_percent' => 10, 'failure_below_percent' => 20]]],
            ]);
            $this->fail('Expected an InvalidConfigurationException.');
        } catch (InvalidConfigurationException $exception) {
            $this->assertStringContainsString('failure_below_percent', $exception->getMessage());
        }
    }

    #[Test]
    public function processConfiguration_allowsOverridingTheAdminUserName(): void
    {
        // prepare
        $processor = new Processor();

        // test
        $config = $processor->processConfiguration(new Configuration(), [
            ['checks' => ['admin_user' => ['user_name' => 'root']]],
        ]);

        // verify
        $this->assertSame('root', $config['checks']['admin_user']['user_name']);
    }

    #[Test]
    public function processConfiguration_rejectsAnEmptyAdminUserName(): void
    {
        // prepare
        $processor = new Processor();

        // test / verify
        $this->expectException(InvalidConfigurationException::class);
        $processor->processConfiguration(new Configuration(), [
            ['checks' => ['admin_user' => ['user_name' => '']]],
        ]);
    }

    #[Test]
    public function processConfiguration_theBooleanShorthandForAdminUserKeepsTheDefaultUserName(): void
    {
        // prepare
        $processor = new Processor();

        // test
        $config = $processor->processConfiguration(new Configuration(), [
            ['checks' => ['admin_user' => false]],
        ]);

        // verify
        $this->assertFalse($config['checks']['admin_user']['enabled']);
        $this->assertSame('admin', $config['checks']['admin_user']['user_name']);
    }

    #[Test]
    public function processConfiguration_rejectsAPercentValueAboveOneHundred(): void
    {
        // prepare
        $processor = new Processor();

        // test / verify
        $this->expectException(InvalidConfigurationException::class);
        $processor->processConfiguration(new Configuration(), [
            ['checks' => ['disk_space' => ['warning_below_percent' => 101]]],
        ]);
    }

    #[Test]
    public function processConfiguration_rejectsAPercentValueBelowOne(): void
    {
        // prepare
        $processor = new Processor();

        // test / verify
        $this->expectException(InvalidConfigurationException::class);
        $processor->processConfiguration(new Configuration(), [
            ['checks' => ['disk_space' => ['failure_below_percent' => 0]]],
        ]);
    }
}
