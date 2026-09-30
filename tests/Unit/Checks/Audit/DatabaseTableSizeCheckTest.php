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

use Basilicom\PimcorePluginHealthCheck\Checks\Audit\DatabaseTableSizeCheck;
use Basilicom\PimcorePluginHealthCheck\Severity;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DatabaseTableSizeCheckTest extends TestCase
{
    #[Test]
    public function inspect_namesTheOffendingTablesPerLevel(): void
    {
        // prepare
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['table_name' => 'versions', 'size' => '2000000000'],
            ['table_name' => 'plugin_pim_rawitemData', 'size' => '950000000'],
        ]);

        // test
        $report = (new DatabaseTableSizeCheck(true, $connection, 943718400, 1073741824))->inspect();

        // verify
        $this->assertSame(Severity::Failure, $report->severity);
        $this->assertSame('Following database table sizes are critical: versions', $report->message);
        $this->assertSame(['plugin_pim_rawitemData'], $report->data['warning_tables']);
    }

    #[Test]
    public function inspect_warnsWhenOnlyTheWarningThresholdIsCrossed(): void
    {
        // prepare
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([['table_name' => 'versions', 'size' => '950000000']]);

        // test
        $report = (new DatabaseTableSizeCheck(true, $connection, 943718400, 1073741824))->inspect();

        // verify
        $this->assertSame(Severity::Warning, $report->severity);
        $this->assertSame('Following database table sizes are high: versions', $report->message);
    }

    #[Test]
    public function inspect_isOkWithNoTableOverTheLimitAndSkipsTheQueryWithoutThresholds(): void
    {
        // prepare
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);
        $silent = $this->createMock(Connection::class);
        $silent->expects($this->never())->method('fetchAllAssociative');

        // test / verify
        $this->assertSame(Severity::Ok, (new DatabaseTableSizeCheck(true, $connection, 943718400, 1073741824))->inspect()->severity);
        $this->assertSame(Severity::Ok, (new DatabaseTableSizeCheck(true, $silent, null, null))->inspect()->severity);
    }
}
