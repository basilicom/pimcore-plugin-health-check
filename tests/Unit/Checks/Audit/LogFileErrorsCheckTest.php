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

use Basilicom\PimcorePluginHealthCheck\Checks\Audit\LogFileErrorsCheck;
use Basilicom\PimcorePluginHealthCheck\Severity;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class LogFileErrorsCheckTest extends TestCase
{
    private string $logDir;

    protected function setUp(): void
    {
        $this->logDir = sys_get_temp_dir() . '/health-check-logs-' . bin2hex(random_bytes(6));
        mkdir($this->logDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->logDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->logDir);
    }

    #[Test]
    public function inspect_countsRecentErrorLevelLinesOnly(): void
    {
        // prepare
        $recent = (new DateTimeImmutable('-1 hour'))->format('Y-m-d H:i:s');
        $old    = (new DateTimeImmutable('-5 days'))->format('Y-m-d H:i:s');
        $this->write('prod.log', [
            "[$recent] request.INFO: Matched route \"homepage\".",
            "[$recent] php.CRITICAL: Uncaught exception",
            "[$recent] app.ERROR: Something broke",
            "[$recent] app.WARNING: not counted",
            "[$old] app.ERROR: outside the window",
            'no timestamp app.ERROR: not counted',
        ]);
        $this->write('php.log', ["[$recent] PHP Fatal error:  Allowed memory size exhausted", "[$recent] all good"]);

        // test
        $report = $this->check()->inspect();

        // verify
        $this->assertSame(Severity::Ok, $report->severity);
        $this->assertSame('Found 3 errors in log files in the last 24 hours.', $report->message);
        $this->assertSame(['prod.log' => 2, 'php.log' => 1], $report->data['files']);
    }

    #[Test]
    public function inspect_gradesAgainstTheThresholds(): void
    {
        // prepare
        $recent = (new DateTimeImmutable('-10 minutes'))->format('Y-m-d\TH:i:s');
        $this->write('prod.log', array_fill(0, 12, "[$recent] app.ERROR: boom"));

        // test / verify
        $this->assertSame(Severity::Warning, $this->check()->inspect()->severity);
        $this->assertSame(Severity::Failure, $this->check(5, 10)->inspect()->severity);
    }

    #[Test]
    public function inspect_reportsAMissingFileInTheDataWithoutFailing(): void
    {
        // test
        $report = $this->check()->inspect();

        // verify
        $this->assertSame(Severity::Ok, $report->severity);
        $this->assertSame(['prod.log' => 'File not found or not readable', 'php.log' => 'File not found or not readable'], $report->data['files']);
    }

    #[Test]
    public function inspect_onlyScansTheTailOfALargeFile(): void
    {
        // prepare
        $recent = (new DateTimeImmutable('-10 minutes'))->format('Y-m-d H:i:s');
        $lines  = ["[$recent] app.ERROR: at the top"];
        for ($i = 0; $i < 200; ++$i) {
            $lines[] = "[$recent] app.INFO: " . str_repeat('x', 60);
        }
        $lines[] = "[$recent] app.ERROR: at the bottom";
        $this->write('prod.log', $lines);

        // test / verify
        $this->assertSame(1, $this->check(10, 50, 2048)->inspect()->data['total_errors']);
    }

    private function check(int $warning = 10, int $failure = 50, int $maxBytes = 5242880): LogFileErrorsCheck
    {
        return new LogFileErrorsCheck(true, $this->logDir, ['prod.log', 'php.log'], 24, $warning, $failure, $maxBytes);
    }

    /** @param list<string> $lines */
    private function write(string $name, array $lines): void
    {
        file_put_contents($this->logDir . '/' . $name, implode("\n", $lines) . "\n");
    }
}
