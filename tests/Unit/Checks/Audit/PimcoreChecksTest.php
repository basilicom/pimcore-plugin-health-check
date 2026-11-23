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

use Basilicom\PimcorePluginHealthCheck\Checks\Audit\PimcoreAreabricksCheck;
use Basilicom\PimcorePluginHealthCheck\Checks\Audit\PimcoreBundlesCheck;
use Basilicom\PimcorePluginHealthCheck\Checks\Audit\PimcoreElementCountCheck;
use Basilicom\PimcorePluginHealthCheck\Checks\Audit\PimcoreUsersCheck;
use Basilicom\PimcorePluginHealthCheck\Checks\Audit\PimcoreVersionCheck;
use Basilicom\PimcorePluginHealthCheck\Severity;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pimcore\Extension\Document\Areabrick\AreabrickManagerInterface;

class PimcoreChecksTest extends TestCase
{
    #[Test]
    public function version_reportsTheInstalledPimcore(): void
    {
        // test
        $report = (new PimcoreVersionCheck(true))->inspect();

        // verify
        $this->assertSame(Severity::Ok, $report->severity);
        $this->assertMatchesRegularExpression('/^The system is running on Pimcore v\d/', $report->message);
    }

    #[Test]
    public function bundlesAndAreabricks_areNotAvailableWithoutTheirManagers(): void
    {
        // test / verify
        $this->assertSame(Severity::NotAvailable, (new PimcoreBundlesCheck(true, null))->inspect()->severity);
        $this->assertSame(Severity::NotAvailable, (new PimcoreAreabricksCheck(true, null))->inspect()->severity);
    }

    #[Test]
    public function areabricks_countsTheRegisteredBrickIds(): void
    {
        // prepare
        $manager = $this->createMock(AreabrickManagerInterface::class);
        $manager->method('getBrickIds')->willReturn(['a', 'b', 'c']);

        // test
        $report = (new PimcoreAreabricksCheck(true, $manager))->inspect();

        // verify
        $this->assertSame('There are 3 Areabricks in the system', $report->message);
    }

    #[Test]
    public function users_countsUsersAndActiveUsers(): void
    {
        // prepare
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturnOnConsecutiveCalls('62', '60');

        // test
        $report = (new PimcoreUsersCheck(true, $connection))->inspect();

        // verify
        $this->assertSame('There are 62 Pimcore users in the system', $report->message);
        $this->assertSame(['count' => 62, 'active' => 60], $report->data);
    }

    #[Test]
    public function elementCount_sumsObjectsAssetsAndDocumentsAndGrades(): void
    {
        // prepare
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturnOnConsecutiveCalls('120000', '25000', '6000');

        // test
        $report = (new PimcoreElementCountCheck(true, $connection, 100000, 150000))->inspect();

        // verify
        $this->assertSame(Severity::Failure, $report->severity);
        $this->assertSame('There are 151000 elements in the system (120000 objects, 25000 assets, 6000 documents)', $report->message);
    }
}
