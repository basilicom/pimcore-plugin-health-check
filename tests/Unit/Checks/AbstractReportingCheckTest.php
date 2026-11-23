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

use Basilicom\PimcorePluginHealthCheck\Checks\AbstractReportingCheck;
use Basilicom\PimcorePluginHealthCheck\Checks\Report;
use Basilicom\PimcorePluginHealthCheck\Exception\ReportedProblemException;
use Basilicom\PimcorePluginHealthCheck\Severity;
use Basilicom\PimcorePluginHealthCheck\Tests\Fixtures\Checks\ReportingFixtureCheck;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class AbstractReportingCheckTest extends TestCase
{
    #[Test]
    public function inspect_returnsTheReportOfExamine(): void
    {
        // prepare
        $check = new ReportingFixtureCheck(static fn (): Report => Report::ok('all good', ['n' => 1]));

        // test
        $report = $check->inspect();

        // verify
        $this->assertSame(Severity::Ok, $report->severity);
        $this->assertSame('all good', $report->message);
        $this->assertSame(['n' => 1], $report->data);
    }

    #[Test]
    public function inspect_turnsAThrowingExamineIntoAFailureThatNamesTheClassButNotTheMessage(): void
    {
        // prepare
        $check = new ReportingFixtureCheck(static function (): Report {
            throw new RuntimeException('dsn=mysql://root:hunter2@db');
        });

        // test
        $report = $check->inspect();

        // verify
        $this->assertSame(Severity::Failure, $report->severity);
        $this->assertStringContainsString('RuntimeException', $report->message);
        $this->assertStringNotContainsString('hunter2', $report->message);
        $this->assertSame(RuntimeException::class, $report->data['exception']);
    }

    #[Test]
    public function check_throwsWithTheReportSeverityForAProblem(): void
    {
        // prepare
        $check = new ReportingFixtureCheck(static fn (): Report => Report::warning('degraded', ['x' => 2]));

        // test
        try {
            $check->check();
            $this->fail('Expected exception was not thrown.');
        } catch (ReportedProblemException $exception) {
            // verify
            $this->assertSame(Severity::Warning, $exception->severity());
            $this->assertSame('degraded', $exception->getMessage());
            $this->assertSame(['x' => 2], $exception->report()->data);
        }
    }

    #[Test]
    public function check_doesNotThrowForOkSkippedOrNotAvailable(): void
    {
        // test
        (new ReportingFixtureCheck(static fn (): Report => Report::ok('fine')))->check();
        (new ReportingFixtureCheck(static fn (): Report => Report::skipped('cli')))->check();
        (new ReportingFixtureCheck(static fn (): Report => Report::notAvailable('offline')))->check();

        // verify
        $this->addToAssertionCount(3);
    }

    #[Test]
    public function isActive_reflectsTheInjectedFlag(): void
    {
        // test / verify
        $this->assertTrue((new ReportingFixtureCheck(static fn (): Report => Report::ok(''), true))->isActive());
        $this->assertFalse((new ReportingFixtureCheck(static fn (): Report => Report::ok(''), false))->isActive());
    }

    #[Test]
    public function threshold_acceptsNumbersAndTreatsEverythingElseAsOff(): void
    {
        // prepare
        $threshold = (new \ReflectionClass(AbstractReportingCheck::class))->getMethod('threshold');

        // test / verify
        $this->assertSame(10, $threshold->invoke(null, '10'));
        $this->assertSame(1.5, $threshold->invoke(null, '1.5'));
        $this->assertSame(7, $threshold->invoke(null, 7));
        $this->assertNull($threshold->invoke(null, null));
        $this->assertNull($threshold->invoke(null, ''));
        $this->assertNull($threshold->invoke(null, 'lots'));
    }
}
