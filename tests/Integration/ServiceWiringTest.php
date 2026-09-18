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

namespace Basilicom\PimcorePluginHealthCheck\Tests\Integration;

use Basilicom\PimcorePluginHealthCheck\Checks\AdminUserCheck;
use Basilicom\PimcorePluginHealthCheck\Checks\AssetStorageCheck;
use Basilicom\PimcorePluginHealthCheck\Checks\CacheCheck;
use Basilicom\PimcorePluginHealthCheck\Checks\DatabaseAccessibleCheck;
use Basilicom\PimcorePluginHealthCheck\Checks\DatabaseLatencyCheck;
use Basilicom\PimcorePluginHealthCheck\Checks\DiskSpaceCheck;
use Basilicom\PimcorePluginHealthCheck\Checks\FilesystemCheck;
use Basilicom\PimcorePluginHealthCheck\Checks\PendingMigrationsCheck;
use Basilicom\PimcorePluginHealthCheck\Checks\PimcoreConfigurationCheck;
use Basilicom\PimcorePluginHealthCheck\Checks\RobotsTxtCheck;
use Basilicom\PimcorePluginHealthCheck\Command\HealthCheckCommand;
use Basilicom\PimcorePluginHealthCheck\Controller\HealthCheckController;
use Basilicom\PimcorePluginHealthCheck\DependencyInjection\PimcorePluginHealthCheckExtension;
use Basilicom\PimcorePluginHealthCheck\Services\HealthCheckService;
use Doctrine\DBAL\Connection;
use Doctrine\Migrations\DependencyFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pimcore\Tool\Storage;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Command\Command as ConsoleCommand;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class ServiceWiringTest extends TestCase
{
    // ordered by tag priority, cheapest local checks first
    private const array CHECK_CLASSES = [
        PimcoreConfigurationCheck::class,
        DiskSpaceCheck::class,
        DatabaseAccessibleCheck::class,
        DatabaseLatencyCheck::class,
        PendingMigrationsCheck::class,
        AdminUserCheck::class,
        FilesystemCheck::class,
        AssetStorageCheck::class,
        CacheCheck::class,
        RobotsTxtCheck::class,
    ];

    #[Test]
    public function theBundleServicesCompileAndCanBeInstantiated(): void
    {
        // prepare
        $container = $this->buildContainer();

        // test
        $container->compile();

        // verify
        $this->assertInstanceOf(HealthCheckService::class, $container->get(HealthCheckService::class));
        $this->assertInstanceOf(HealthCheckController::class, $container->get(HealthCheckController::class));
        $this->assertInstanceOf(HealthCheckCommand::class, $container->get(HealthCheckCommand::class));
        $this->assertArrayHasKey(
            HealthCheckCommand::class,
            $container->findTaggedServiceIds('console.command')
        );

        $tagged = $container->findTaggedServiceIds('basilicom.health_check');
        $this->assertSame(self::CHECK_CLASSES, array_keys($tagged));

        $priorities = [];
        foreach ($tagged as $id => $tags) {
            $priorities[$id] = $tags[0]['priority'];
        }
        $this->assertSame(85, $priorities[DatabaseLatencyCheck::class]);

        // the tagged_iterator resolves this order at runtime; verify it here so it stays correct
        arsort($priorities);
        $this->assertSame(self::CHECK_CLASSES, array_keys($priorities));
    }

    #[Test]
    public function aDisabledCheckIsInactiveWhileTheOthersStayActive(): void
    {
        // prepare
        $container = $this->buildContainer(['checks' => ['robots_txt' => false]]);

        // test
        $container->compile();

        // verify
        $this->assertFalse($container->get(RobotsTxtCheck::class)->isActive());
        $this->assertTrue($container->get(DatabaseAccessibleCheck::class)->isActive());
        $this->assertTrue($container->get(AdminUserCheck::class)->isActive());
        $this->assertTrue($container->get(FilesystemCheck::class)->isActive());
        $this->assertTrue($container->get(CacheCheck::class)->isActive());
    }

    #[Test]
    public function theDatabaseLatencyCheckIsInactiveByDefault(): void
    {
        // prepare
        $container = $this->buildContainer();

        // test
        $container->compile();

        // verify
        $this->assertFalse($container->get(DatabaseLatencyCheck::class)->isActive());
    }

    #[Test]
    public function theDatabaseLatencyThresholdCanBeConfigured(): void
    {
        // prepare
        $container = $this->buildContainer([
            'checks' => ['database_latency' => ['enabled' => true, 'threshold_ms' => 250]],
        ]);

        // test
        $container->compile();

        // verify
        $this->assertTrue($container->get(DatabaseLatencyCheck::class)->isActive());
        $this->assertSame(
            250,
            $container->getParameter('pimcore_plugin_health_check.checks.database_latency.threshold_ms')
        );
    }

    #[Test]
    public function theNewChecksAreInactiveByDefaultExceptAdminUser(): void
    {
        // prepare
        $container = $this->buildContainer();

        // test
        $container->compile();

        // verify
        $this->assertFalse($container->get(AssetStorageCheck::class)->isActive());
        $this->assertFalse($container->get(PendingMigrationsCheck::class)->isActive());
        $this->assertFalse($container->get(DiskSpaceCheck::class)->isActive());
        $this->assertTrue($container->get(AdminUserCheck::class)->isActive());
    }

    #[Test]
    public function allCheckEnabledParametersAreSetForAnEmptyConfiguration(): void
    {
        // prepare
        $container = $this->buildContainer();

        // test
        $container->compile();

        // verify
        foreach (
            [
                'database',
                'database_latency',
                'admin_user',
                'filesystem',
                'cache',
                'robots_txt',
                'asset_storage',
                'pending_migrations',
                'disk_space',
            ] as $check
        ) {
            $this->assertIsBool(
                $container->getParameter(sprintf('pimcore_plugin_health_check.checks.%s.enabled', $check))
            );
        }
    }

    #[Test]
    public function theTokenParameterIsNullForAnEmptyConfiguration(): void
    {
        // prepare
        $container = $this->buildContainer();

        // test
        $container->compile();

        // verify
        $this->assertNull($container->getParameter('pimcore_plugin_health_check.token'));
    }

    #[Test]
    public function theTimeoutParameterDefaultsTo5000ForAnEmptyConfiguration(): void
    {
        // prepare
        $container = $this->buildContainer();

        // test
        $container->compile();

        // verify
        $this->assertSame(5000, $container->getParameter('pimcore_plugin_health_check.timeout_ms'));
    }

    #[Test]
    public function theTimeoutParameterIsPassedThrough(): void
    {
        // prepare
        $container = $this->buildContainer(['timeout_ms' => 10000]);

        // test
        $container->compile();

        // verify
        $this->assertSame(10000, $container->getParameter('pimcore_plugin_health_check.timeout_ms'));
    }

    #[Test]
    public function theTokenParameterIsPassedThroughAndInjectedIntoControllerAndCommand(): void
    {
        // prepare
        $token     = 'a-valid-token-of-16-chars-or-more';
        $container = $this->buildContainer(['token' => $token]);

        // test
        $container->compile();

        // verify
        $this->assertSame($token, $container->getParameter('pimcore_plugin_health_check.token'));
        $this->assertInstanceOf(HealthCheckController::class, $container->get(HealthCheckController::class));
        $this->assertInstanceOf(HealthCheckCommand::class, $container->get(HealthCheckCommand::class));
    }

    #[Test]
    public function pathParametersFallBackToProjectDirectoryWithoutPimcoreConstants(): void
    {
        // prepare
        $container = $this->buildContainer();

        // test
        $container->compile();

        // verify
        $this->assertSame(
            '/tmp/project/var/tmp',
            $container->getParameter('pimcore_plugin_health_check.temp_directory')
        );
        $this->assertSame(
            '/tmp/project/public',
            $container->getParameter('pimcore_plugin_health_check.web_root_directory')
        );
    }

    private function buildContainer(array $config = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', '/tmp/project');

        $container->setDefinition('doctrine.dbal.default_connection', new Definition(Connection::class))
            ->setSynthetic(true);
        $container->set('doctrine.dbal.default_connection', $this->createMock(Connection::class));

        $container->setDefinition(Storage::class, new Definition(Storage::class))->setSynthetic(true);
        $container->set(Storage::class, $this->createMock(Storage::class));

        $container->setDefinition('doctrine.migrations.dependency_factory', new Definition(DependencyFactory::class))
            ->setSynthetic(true);
        $container->set('doctrine.migrations.dependency_factory', $this->createMock(DependencyFactory::class));

        $container->setDefinition('pimcore.cache.pool', new Definition(ArrayAdapter::class));
        $container->setDefinition('logger', new Definition(NullLogger::class));

        // mirrors FrameworkBundle's own autoconfiguration, absent here since only this bundle's extension loads
        $container->registerForAutoconfiguration(ConsoleCommand::class)->addTag('console.command');

        (new PimcorePluginHealthCheckExtension())->load([$config], $container);

        $ids = array_merge(
            self::CHECK_CLASSES,
            [HealthCheckService::class, HealthCheckController::class, HealthCheckCommand::class]
        );
        foreach ($ids as $id) {
            $container->getDefinition($id)->setPublic(true);
        }

        return $container;
    }
}
