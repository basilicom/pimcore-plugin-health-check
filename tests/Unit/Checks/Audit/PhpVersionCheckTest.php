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

use Basilicom\PimcorePluginHealthCheck\Checks\Audit\PhpVersionCheck;
use Basilicom\PimcorePluginHealthCheck\Severity;
use Basilicom\PimcorePluginHealthCheck\Tests\Fixtures\StubEndOfLifeDateClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PhpVersionCheckTest extends TestCase
{
    #[Test]
    public function inspect_configuredMode_acceptsASufficientVersion(): void
    {
        // test
        $report = (new PhpVersionCheck(true, new StubEndOfLifeDateClient(null), '8.0', '>='))->inspect();

        // verify
        $this->assertSame(Severity::Ok, $report->severity);
        $this->assertSame('Current PHP version is ' . PHP_VERSION, $report->message);
    }

    #[Test]
    public function inspect_configuredMode_failsAnInsufficientVersionAndNamesTheRequirement(): void
    {
        // test
        $report = (new PhpVersionCheck(true, new StubEndOfLifeDateClient(null), '99.0', '>='))->inspect();

        // verify
        $this->assertSame(Severity::Failure, $report->severity);
        $this->assertStringContainsString('required is >= 99.0', $report->message);
    }

    #[Test]
    public function inspect_configuredMode_reportsAnUnsupportedOperatorAsNotAvailable(): void
    {
        // test / verify
        $this->assertSame(Severity::NotAvailable, (new PhpVersionCheck(true, new StubEndOfLifeDateClient(null), '8.0', 'rm -rf'))->inspect()->severity);
    }

    #[Test]
    public function inspect_dynamicMode_isOkOnTheLatestSupportedCycle(): void
    {
        // prepare
        $client = new StubEndOfLifeDateClient([['cycle' => self::cycle(), 'eol' => '2099-01-01'], ['cycle' => '7.4', 'eol' => '2022-11-28']]);

        // test
        $report = (new PhpVersionCheck(true, $client, null, '>='))->inspect();

        // verify
        $this->assertSame(Severity::Ok, $report->severity);
        $this->assertSame(self::cycle(), $report->data['latest_supported_cycle']);
    }

    #[Test]
    public function inspect_dynamicMode_warnsWhenANewerCycleIsSupported(): void
    {
        // prepare
        $client = new StubEndOfLifeDateClient([['cycle' => '99.9', 'eol' => false], ['cycle' => self::cycle(), 'eol' => '2099-01-01']]);

        // test
        $report = (new PhpVersionCheck(true, $client, null, '>='))->inspect();

        // verify
        $this->assertSame(Severity::Warning, $report->severity);
        $this->assertStringContainsString('latest supported release cycle is 99.9', $report->message);
    }

    #[Test]
    public function inspect_dynamicMode_failsAfterEndOfLife(): void
    {
        // prepare
        $client = new StubEndOfLifeDateClient([['cycle' => '99.9', 'eol' => false], ['cycle' => self::cycle(), 'eol' => '2020-01-01']]);

        // test
        $report = (new PhpVersionCheck(true, $client, null, '>='))->inspect();

        // verify
        $this->assertSame(Severity::Failure, $report->severity);
        $this->assertStringContainsString('end of life on 2020-01-01', $report->message);
    }

    #[Test]
    public function inspect_dynamicMode_treatsEolTrueAsEndOfLife(): void
    {
        // test / verify
        $this->assertSame(
            Severity::Failure,
            (new PhpVersionCheck(true, new StubEndOfLifeDateClient([['cycle' => self::cycle(), 'eol' => true]]), null, '>='))->inspect()->severity
        );
    }

    #[Test]
    public function inspect_dynamicMode_isNotAvailableWhenTheLookupFailsOrKnowsNothing(): void
    {
        // test
        $offline = (new PhpVersionCheck(true, new StubEndOfLifeDateClient(null), null, '>='))->inspect();
        $unknown = (new PhpVersionCheck(true, new StubEndOfLifeDateClient([['cycle' => '5.6', 'eol' => true]]), null, '>='))->inspect();

        // verify
        $this->assertSame(Severity::NotAvailable, $offline->severity);
        $this->assertSame('Could not fetch version data from endoflife.date (n/a)', $offline->message);
        $this->assertSame(Severity::NotAvailable, $unknown->severity);
        $this->assertStringContainsString('has no data for PHP', $unknown->message);
    }

    private static function cycle(): string
    {
        return PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
    }
}
