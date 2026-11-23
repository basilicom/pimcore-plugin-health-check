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

use Basilicom\PimcorePluginHealthCheck\Checks\Audit\ApplicationLogErrorsCheck;
use Basilicom\PimcorePluginHealthCheck\Severity;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ApplicationLogErrorsCheckTest extends TestCase
{
    #[Test]
    public function inspect_usesTheDecorUnionWordingAndGrades(): void
    {
        // prepare
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->with($this->stringContains('application_logs'), ['emergency', 'alert', 'critical', 'error', 24])->willReturn('2379');

        // test
        $report = (new ApplicationLogErrorsCheck(true, $connection, 24, 10, 50))->inspect();

        // verify
        $this->assertSame(Severity::Failure, $report->severity);
        $this->assertSame('Found 2379 errors in application logs in the last 24 hours.', $report->message);
        $this->assertSame(['error_count' => 2379, 'lookback_hours' => 24], $report->data);
    }

    #[Test]
    public function inspect_isNotAvailableWithoutTheApplicationLoggerTable(): void
    {
        // prepare
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willThrowException(new RuntimeException("Table 'pimcore.application_logs' doesn't exist"));

        // test / verify
        $this->assertSame(Severity::NotAvailable, (new ApplicationLogErrorsCheck(true, $connection, 24, 10, 50))->inspect()->severity);
    }
}
