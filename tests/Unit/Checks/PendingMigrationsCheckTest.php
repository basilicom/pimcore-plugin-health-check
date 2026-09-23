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

namespace Basilicom\PimcorePluginHealthCheck\Tests\Unit\Checks;

use Basilicom\PimcorePluginHealthCheck\Checks\PendingMigrationsCheck;
use Basilicom\PimcorePluginHealthCheck\Exception\PendingMigrationsException;
use Basilicom\PimcorePluginHealthCheck\Severity;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Metadata\AvailableMigration;
use Doctrine\Migrations\Metadata\AvailableMigrationsList;
use Doctrine\Migrations\Version\MigrationStatusCalculator;
use Doctrine\Migrations\Version\Version;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

class PendingMigrationsCheckTest extends TestCase
{
    #[Test]
    public function check_doesNotThrowWhenNoMigrationsArePending(): void
    {
        // prepare
        $calculator = $this->createMock(MigrationStatusCalculator::class);
        $calculator->method('getNewMigrations')->willReturn(new AvailableMigrationsList([]));

        $dependencyFactory = $this->createMock(DependencyFactory::class);
        $dependencyFactory->method('getMigrationStatusCalculator')->willReturn($calculator);

        $check = new PendingMigrationsCheck($dependencyFactory, true);

        // test
        $check->check();

        // verify
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function check_throwsAWarningNamingTheCountWhenMigrationsArePending(): void
    {
        // prepare
        $calculator = $this->createMock(MigrationStatusCalculator::class);
        $calculator->method('getNewMigrations')->willReturn(new AvailableMigrationsList($this->twoAvailableMigrations()));

        $dependencyFactory = $this->createMock(DependencyFactory::class);
        $dependencyFactory->method('getMigrationStatusCalculator')->willReturn($calculator);

        $check = new PendingMigrationsCheck($dependencyFactory, true);

        // test
        try {
            $check->check();
            $this->fail('Expected exception was not thrown.');
        } catch (PendingMigrationsException $exception) {
            // verify
            $this->assertSame(Severity::Warning, $exception->severity());
            $this->assertStringContainsString('2', $exception->getMessage());
        }
    }

    #[Test]
    public function check_wrapsAFailureToDetermineTheStatusAsAFailureWithThePrevious(): void
    {
        // prepare
        $statusException = new RuntimeException('migrations table could not be read');

        $dependencyFactory = $this->createMock(DependencyFactory::class);
        $dependencyFactory->method('getMigrationStatusCalculator')->willThrowException($statusException);

        $check = new PendingMigrationsCheck($dependencyFactory, true);

        // test
        try {
            $check->check();
            $this->fail('Expected exception was not thrown.');
        } catch (PendingMigrationsException $exception) {
            // verify
            $this->assertSame(Severity::Failure, $exception->severity());
            $this->assertSame($statusException, $exception->getPrevious());
        }
    }

    #[Test]
    public function isActive_reflectsTheInjectedFlag(): void
    {
        // prepare
        $dependencyFactory = $this->createMock(DependencyFactory::class);

        // test / verify
        $this->assertTrue((new PendingMigrationsCheck($dependencyFactory, true))->isActive());
        $this->assertFalse((new PendingMigrationsCheck($dependencyFactory, false))->isActive());
    }

    /** @return AvailableMigration[] */
    private function twoAvailableMigrations(): array
    {
        $migration = new class ($this->createMock(Connection::class), new NullLogger()) extends AbstractMigration {
            public function up(Schema $schema): void
            {
            }
        };

        return [
            new AvailableMigration(new Version('20260101120000'), $migration),
            new AvailableMigration(new Version('20260102120000'), $migration),
        ];
    }
}
