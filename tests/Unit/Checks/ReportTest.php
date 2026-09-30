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

use Basilicom\PimcorePluginHealthCheck\Checks\Report;
use Basilicom\PimcorePluginHealthCheck\Severity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ReportTest extends TestCase
{
    #[Test]
    #[DataProvider('gradedValues')]
    public function graded_picksTheSeverityFromInclusiveThresholds(int|float $value, int|float|null $warning, int|float|null $failure, Severity $expected): void
    {
        // test
        $report = Report::graded($value, $warning, $failure, 'value is ' . $value, ['value' => $value]);

        // verify
        $this->assertSame($expected, $report->severity);
        $this->assertSame('value is ' . $value, $report->message);
        $this->assertSame(['value' => $value], $report->data);
    }

    /** @return iterable<string, array{int|float, int|float|null, int|float|null, Severity}> */
    public static function gradedValues(): iterable
    {
        yield 'below both' => [5, 10, 50, Severity::Ok];
        yield 'at warning' => [10, 10, 50, Severity::Warning];
        yield 'between' => [40, 10, 50, Severity::Warning];
        yield 'at failure' => [50, 10, 50, Severity::Failure];
        yield 'above failure' => [999, 10, 50, Severity::Failure];
        yield 'floats' => [90.5, 90.0, 95.0, Severity::Warning];
        yield 'no thresholds' => [1_000_000, null, null, Severity::Ok];
        yield 'warning only' => [11, 10, null, Severity::Warning];
        yield 'failure only' => [11, null, 10, Severity::Failure];
        yield 'inverted, plenty left' => [80, 20, 10, Severity::Ok];
        yield 'inverted, at warning' => [20, 20, 10, Severity::Warning];
        yield 'inverted, at failure' => [10, 20, 10, Severity::Failure];
    }

    #[Test]
    public function isProblem_isTrueForWarningAndFailureOnly(): void
    {
        // test / verify
        $this->assertFalse(Report::ok('fine')->isProblem());
        $this->assertTrue(Report::warning('meh')->isProblem());
        $this->assertTrue(Report::failure('bad')->isProblem());
        $this->assertFalse(Report::skipped('later')->isProblem());
        $this->assertFalse(Report::notAvailable('n/a')->isProblem());
    }
}
