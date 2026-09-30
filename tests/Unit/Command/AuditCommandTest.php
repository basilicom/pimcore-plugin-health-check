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

use Basilicom\PimcorePluginHealthCheck\Checks\Report;
use Basilicom\PimcorePluginHealthCheck\Command\AuditCommand;
use Basilicom\PimcorePluginHealthCheck\Services\HealthCheckService;
use Basilicom\PimcorePluginHealthCheck\Tests\Fixtures\Checks\FailingCheck;
use Basilicom\PimcorePluginHealthCheck\Tests\Fixtures\Checks\FirstCheck;
use Basilicom\PimcorePluginHealthCheck\Tests\Fixtures\Checks\ReportingFixtureCheck;
use Basilicom\PimcorePluginHealthCheck\Tests\Fixtures\InMemoryRunStore;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class AuditCommandTest extends TestCase
{
    #[Test]
    public function execute_runsCoreAndAuditChecksStoresTheRunAndSucceedsWhenNothingFailed(): void
    {
        // prepare
        $store  = new InMemoryRunStore();
        $tester = new CommandTester(new AuditCommand(
            new HealthCheckService([new FirstCheck()], 5000),
            new HealthCheckService([new ReportingFixtureCheck(static fn (): Report => Report::warning('meh', ['n' => 1]), true, 'audit:thing', 'A Thing')], 5000),
            $store
        ));

        // test
        $exitCode = $tester->execute([]);

        // verify
        $this->assertSame(Command::SUCCESS, $exitCode);
        $run = $store->load();
        $this->assertNotNull($run);
        $this->assertCount(2, $run->results);
        $this->assertSame('audit:thing', $run->results[1]->identifier());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('A Thing', $output);
        $this->assertStringContainsString('WARNING', $output);
        $this->assertStringContainsString('System is healthy', $output);
    }

    #[Test]
    public function execute_failsWhenACheckFailedAndStillStoresTheRun(): void
    {
        // prepare
        $store  = new InMemoryRunStore();
        $tester = new CommandTester(new AuditCommand(new HealthCheckService([new FailingCheck('temp directory is not writeable')], 5000), new HealthCheckService([], 5000), $store));

        // test
        $exitCode = $tester->execute([]);

        // verify
        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertNotNull($store->load());
        $this->assertStringContainsString('temp directory is not writeable', $tester->getDisplay());
        $this->assertStringContainsString('NOT healthy', $tester->getDisplay());
    }

    #[Test]
    public function execute_canPrintJsonAndSkipStoring(): void
    {
        // prepare
        $store  = new InMemoryRunStore();
        $tester = new CommandTester(new AuditCommand(new HealthCheckService([new FirstCheck()], 5000), new HealthCheckService([], 5000), $store));

        // test
        $exitCode = $tester->execute(['--format' => 'json', '--no-store' => true]);
        $payload  = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);

        // verify
        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertNull($store->load());
        $this->assertSame('ok', $payload['status']);
        $this->assertTrue($payload['healthy']);
        $this->assertSame('core:first', $payload['checks'][0]['identifier']);
    }

    #[Test]
    public function execute_rejectsAnUnknownFormat(): void
    {
        // prepare
        $tester = new CommandTester(new AuditCommand(new HealthCheckService([], 5000), new HealthCheckService([], 5000), new InMemoryRunStore()));

        // test / verify
        $this->assertSame(Command::INVALID, $tester->execute(['--format' => 'xml']));
    }
}
