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
    public function processConfiguration_defaultsAllChecksToEnabled(): void
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
                'checks'     => [
                    'database'           => ['enabled' => true],
                    'database_latency'   => ['enabled' => false, 'threshold_ms' => 1000],
                    'admin_user'         => ['enabled' => true, 'user_name' => 'admin'],
                    'filesystem'         => ['enabled' => true],
                    'cache'              => ['enabled' => true],
                    'robots_txt'         => ['enabled' => true],
                    'asset_storage'      => ['enabled' => false],
                    'pending_migrations' => ['enabled' => false],
                    'disk_space'         => ['enabled' => false, 'warning_below_percent' => 20, 'failure_below_percent' => 5],
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
