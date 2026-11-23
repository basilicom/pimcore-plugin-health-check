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

use Basilicom\PimcorePluginHealthCheck\Checks\Audit\DatabaseSizeCheck;
use Basilicom\PimcorePluginHealthCheck\Severity;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DatabaseSizeCheckTest extends TestCase
{
    #[Test]
    public function inspect_gradesTheSchemaSizeAndFormatsIt(): void
    {
        // prepare
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn('8150000000');

        // test
        $report = (new DatabaseSizeCheck(true, $connection, 9878424780, 10737418240))->inspect();

        // verify
        $this->assertSame(Severity::Ok, $report->severity);
        $this->assertSame('Database size is 7.59 GB', $report->message);
        $this->assertSame(8150000000, $report->data['size_bytes']);
    }

    #[Test]
    public function inspect_failsAboveTheFailureThreshold(): void
    {
        // prepare
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn('20000000000');

        // test / verify
        $this->assertSame(Severity::Failure, (new DatabaseSizeCheck(true, $connection, 9878424780, 10737418240))->inspect()->severity);
    }

    #[Test]
    public function inspect_neverGradesWithoutThresholds(): void
    {
        // prepare
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn('20000000000');

        // test / verify
        $this->assertSame(Severity::Ok, (new DatabaseSizeCheck(true, $connection, null, null))->inspect()->severity);
    }
}
