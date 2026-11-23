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

namespace Basilicom\PimcorePluginHealthCheck\Tests\Unit\Checks\Audit;

use Basilicom\PimcorePluginHealthCheck\Checks\Audit\DoctrineMigrationsCheck;
use Basilicom\PimcorePluginHealthCheck\Severity;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Metadata\AvailableMigration;
use Doctrine\Migrations\Metadata\AvailableMigrationsList;
use Doctrine\Migrations\Metadata\ExecutedMigration;
use Doctrine\Migrations\Metadata\ExecutedMigrationsList;
use Doctrine\Migrations\Version\MigrationStatusCalculator;
use Doctrine\Migrations\Version\Version;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DoctrineMigrationsCheckTest extends TestCase
{
    #[Test]
    public function inspect_isNotAvailableWithoutMigrations(): void
    {
        // test / verify
        $this->assertSame(Severity::NotAvailable, (new DoctrineMigrationsCheck(true, null))->inspect()->severity);
    }

    #[Test]
    public function inspect_isOkWhenNothingIsPendingOrOrphaned(): void
    {
        // test
        $report = (new DoctrineMigrationsCheck(true, $this->factory(0, 0)))->inspect();

        // verify
        $this->assertSame(Severity::Ok, $report->severity);
        $this->assertSame('All migrations are applied', $report->message);
    }

    #[Test]
    public function inspect_warnsOnPendingMigrations(): void
    {
        // test
        $report = (new DoctrineMigrationsCheck(true, $this->factory(2, 0)))->inspect();

        // verify
        $this->assertSame(Severity::Warning, $report->severity);
        $this->assertSame('2 migrations are pending', $report->message);
    }

    #[Test]
    public function inspect_failsOnMigrationsExecutedButMissingInCode(): void
    {
        // test
        $report = (new DoctrineMigrationsCheck(true, $this->factory(1, 1)))->inspect();

        // verify
        $this->assertSame(Severity::Failure, $report->severity);
        $this->assertSame('Migrations applied which are not available', $report->message);
    }

    private function factory(int $pending, int $unavailable): DependencyFactory
    {
        $new = [];
        for ($i = 0; $i < $pending; ++$i) {
            $new[] = new AvailableMigration(new Version('Pending' . $i), $this->createMock(\Doctrine\Migrations\AbstractMigration::class));
        }
        $gone = [];
        for ($i = 0; $i < $unavailable; ++$i) {
            $gone[] = new ExecutedMigration(new Version('Gone' . $i));
        }

        $calculator = $this->createMock(MigrationStatusCalculator::class);
        $calculator->method('getNewMigrations')->willReturn(new AvailableMigrationsList($new));
        $calculator->method('getExecutedUnavailableMigrations')->willReturn(new ExecutedMigrationsList($gone));

        $factory = $this->createMock(DependencyFactory::class);
        $factory->method('getMigrationStatusCalculator')->willReturn($calculator);

        return $factory;
    }
}
