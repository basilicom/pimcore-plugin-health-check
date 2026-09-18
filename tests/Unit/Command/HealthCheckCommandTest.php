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

namespace Basilicom\PimcorePluginHealthCheck\Tests\Unit\Command;

use Basilicom\PimcorePluginHealthCheck\Command\HealthCheckCommand;
use Basilicom\PimcorePluginHealthCheck\Services\HealthCheckService;
use Basilicom\PimcorePluginHealthCheck\Tests\Fixtures\Checks\AnotherFailingCheck;
use Basilicom\PimcorePluginHealthCheck\Tests\Fixtures\Checks\FailingCheck;
use Basilicom\PimcorePluginHealthCheck\Tests\Fixtures\Checks\FirstCheck;
use Basilicom\PimcorePluginHealthCheck\Tests\Fixtures\Checks\SecondCheck;
use Basilicom\PimcorePluginHealthCheck\Tests\Fixtures\Checks\SlowCheck;
use Basilicom\PimcorePluginHealthCheck\Tests\Fixtures\Checks\WarningCheck;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class HealthCheckCommandTest extends TestCase
{
    // a token is set on the unrelated tests below so the "endpoint is open" warning does not pollute their output
    private const string TOKEN            = 'super-secret-test-token';
    private const int GENEROUS_TIMEOUT_MS = 5000;

    #[Test]
    public function execute_returnsSuccessAndListsEveryCheckWhenAllPass(): void
    {
        // prepare
        $service = new HealthCheckService([new FirstCheck(), new SecondCheck()], self::GENEROUS_TIMEOUT_MS);
        $tester  = new CommandTester(new HealthCheckCommand($service, self::TOKEN));

        // test
        $exitCode = $tester->execute([]);

        // verify
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $this->normalize($tester->getDisplay());
        $this->assertStringContainsString('FirstCheck', $output);
        $this->assertStringContainsString('SecondCheck', $output);
        $this->assertStringContainsString('SUCCESS', $output);
    }

    #[Test]
    public function execute_returnsFailureAndShowsTheReasonWhenACheckFails(): void
    {
        // prepare
        $service = new HealthCheckService(
            [new FailingCheck('temp directory is not writeable')],
            self::GENEROUS_TIMEOUT_MS
        );
        $tester = new CommandTester(new HealthCheckCommand($service, self::TOKEN));

        // test
        $exitCode = $tester->execute([]);

        // verify
        $this->assertSame(Command::FAILURE, $exitCode);
        $output = $this->normalize($tester->getDisplay());
        $this->assertStringContainsString('FAILURE', $output);
        $this->assertStringContainsString('temp directory is not writeable', $output);
    }

    #[Test]
    public function execute_runsAllChecksAndShowsEveryFailureReason(): void
    {
        // prepare
        $service = new HealthCheckService([
            new FailingCheck('first reason'),
            new AnotherFailingCheck('second reason'),
        ], self::GENEROUS_TIMEOUT_MS);
        $tester = new CommandTester(new HealthCheckCommand($service, self::TOKEN));

        // test
        $exitCode = $tester->execute([]);

        // verify
        $this->assertSame(Command::FAILURE, $exitCode);
        $output = $this->normalize($tester->getDisplay());
        $this->assertStringContainsString('first reason', $output);
        $this->assertStringContainsString('second reason', $output);
    }

    #[Test]
    public function execute_returnsSuccessAndShowsAWarningWhenOnlyWarningsOccur(): void
    {
        // prepare
        $service = new HealthCheckService(
            [new WarningCheck('cache latency elevated')],
            self::GENEROUS_TIMEOUT_MS
        );
        $tester = new CommandTester(new HealthCheckCommand($service, self::TOKEN));

        // test
        $exitCode = $tester->execute([]);

        // verify
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $this->normalize($tester->getDisplay());
        $this->assertStringContainsString('WARNING', $output);
        $this->assertStringContainsString('cache latency elevated', $output);
    }

    #[Test]
    public function execute_returnsFailureWhenAWarningAndAFailureBothOccur(): void
    {
        // prepare
        $service = new HealthCheckService([
            new WarningCheck('cache latency elevated'),
            new FailingCheck('temp directory is not writeable'),
        ], self::GENEROUS_TIMEOUT_MS);
        $tester = new CommandTester(new HealthCheckCommand($service, self::TOKEN));

        // test
        $exitCode = $tester->execute([]);

        // verify
        $this->assertSame(Command::FAILURE, $exitCode);
    }

    #[Test]
    public function execute_returnsFailureAndListsTheServiceRowWhenTheTimeBudgetExpires(): void
    {
        // prepare
        $service = new HealthCheckService(
            [new SlowCheck(150_000), new SecondCheck()],
            50
        );
        $tester = new CommandTester(new HealthCheckCommand($service, self::TOKEN));

        // test
        $exitCode = $tester->execute([]);

        // verify
        $this->assertSame(Command::FAILURE, $exitCode);
        $output = $this->normalize($tester->getDisplay());
        $this->assertStringContainsString('HealthCheckService', $output);
        $this->assertStringContainsString('FAILURE', $output);
        $this->assertStringContainsString('Run exceeded 50 ms', $output);
    }

    #[Test]
    public function execute_returnsSuccessAndWarnsWhenNoCheckIsActive(): void
    {
        // prepare
        $service = new HealthCheckService([], self::GENEROUS_TIMEOUT_MS);
        $tester  = new CommandTester(new HealthCheckCommand($service, self::TOKEN));

        // test
        $exitCode = $tester->execute([]);

        // verify
        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $this->normalize($tester->getDisplay());
        $this->assertStringContainsString('No health check is active.', $output);
    }

    #[Test]
    public function execute_warnsThatTheEndpointIsOpenWhenNoTokenIsConfigured(): void
    {
        // prepare
        $service = new HealthCheckService([new FirstCheck()], self::GENEROUS_TIMEOUT_MS);
        $tester  = new CommandTester(new HealthCheckCommand($service));

        // test
        $tester->execute([]);

        // verify
        $output = $this->normalize($tester->getDisplay());
        $this->assertStringContainsString('reachable by anyone', $output);
    }

    #[Test]
    public function execute_doesNotWarnAboutTheOpenEndpointWhenATokenIsConfigured(): void
    {
        // prepare
        $service = new HealthCheckService([new FirstCheck()], self::GENEROUS_TIMEOUT_MS);
        $tester  = new CommandTester(new HealthCheckCommand($service, self::TOKEN));

        // test
        $tester->execute([]);

        // verify
        $output = $this->normalize($tester->getDisplay());
        $this->assertStringNotContainsString('reachable by anyone', $output);
    }

    private function normalize(string $output): string
    {
        return preg_replace('/\s+/', ' ', $output) ?? $output;
    }
}
