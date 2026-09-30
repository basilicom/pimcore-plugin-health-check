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

use Basilicom\PimcorePluginHealthCheck\Checks\Audit\MaintenanceLastRunCheck;
use Basilicom\PimcorePluginHealthCheck\Severity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pimcore\Maintenance\ExecutorInterface;

class MaintenanceLastRunCheckTest extends TestCase
{
    #[Test]
    public function inspect_isNotAvailableWithoutTheExecutor(): void
    {
        // test / verify
        $this->assertSame(Severity::NotAvailable, (new MaintenanceLastRunCheck(true, null, 120, 1440))->inspect()->severity);
    }

    #[Test]
    public function inspect_failsWhenMaintenanceNeverRan(): void
    {
        // test
        $report = (new MaintenanceLastRunCheck(true, $this->executor(0), 120, 1440))->inspect();

        // verify
        $this->assertSame(Severity::Failure, $report->severity);
        $this->assertSame('Pimcore maintenance has never been executed.', $report->message);
    }

    #[Test]
    public function inspect_gradesTheAgeInMinutes(): void
    {
        // test / verify
        $this->assertSame(Severity::Ok, (new MaintenanceLastRunCheck(true, $this->executor(time() - 600), 120, 1440))->inspect()->severity);
        $this->assertSame(Severity::Warning, (new MaintenanceLastRunCheck(true, $this->executor(time() - 3 * 3600), 120, 1440))->inspect()->severity);
        $this->assertSame(Severity::Failure, (new MaintenanceLastRunCheck(true, $this->executor(time() - 2 * 86400), 120, 1440))->inspect()->severity);
    }

    private function executor(int $lastRun): ExecutorInterface
    {
        $executor = $this->createMock(ExecutorInterface::class);
        $executor->method('getLastExecution')->willReturn($lastRun);

        return $executor;
    }
}
