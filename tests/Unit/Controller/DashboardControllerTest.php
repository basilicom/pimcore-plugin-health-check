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

namespace Basilicom\PimcorePluginHealthCheck\Tests\Unit\Controller;

use Basilicom\PimcorePluginHealthCheck\Audit\StoredRun;
use Basilicom\PimcorePluginHealthCheck\Controller\DashboardController;
use Basilicom\PimcorePluginHealthCheck\Security\AccessGuard;
use Basilicom\PimcorePluginHealthCheck\Security\AdminSessionInterface;
use Basilicom\PimcorePluginHealthCheck\Services\CheckResult;
use Basilicom\PimcorePluginHealthCheck\Severity;
use Basilicom\PimcorePluginHealthCheck\Tests\Fixtures\InMemoryRunStore;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class DashboardControllerTest extends TestCase
{
    #[Test]
    public function dashboardAction_answers404WhenDisabledOrUnauthorised(): void
    {
        // prepare
        $twig = $this->createMock(Environment::class);
        $twig->expects($this->never())->method('render');

        // test
        $disabled     = (new DashboardController(new InMemoryRunStore(), $this->guard(true), $twig, false))->dashboardAction(new Request());
        $unauthorised = (new DashboardController(new InMemoryRunStore(), $this->guard(false), $twig, true))->dashboardAction(new Request());

        // verify
        $this->assertSame(404, $disabled->getStatusCode());
        $this->assertSame('', $disabled->getContent());
        $this->assertSame(404, $unauthorised->getStatusCode());
    }

    #[Test]
    public function dashboardAction_rendersTheStoredRunWithTheInstrideLabelsAndDoesNotRunAnyCheck(): void
    {
        // prepare
        $run = new StoredRun(new DateTimeImmutable('2026-09-23 10:00:00'), [
            new CheckResult('Foo\\ApplicationLogErrorsCheck', Severity::Failure, null, 'Found 2379 errors in application logs in the last 24 hours.', ['error_count' => 2379], 'logs:application_log_errors', 'Application Log Errors', 12),
            new CheckResult('Foo\\HttpsConnectionCheck', Severity::Skipped, null, 'No HTTP request to inspect (CLI)', [], 'system:https_connection', 'HTTPS Connection'),
            new CheckResult('Basilicom\\PimcorePluginHealthCheck\\Checks\\DatabaseAccessibleCheck'),
        ], 340);

        // test
        $response = (new DashboardController(new InMemoryRunStore($run), $this->guard(true), $this->twig(), true))->dashboardAction(new Request());

        // verify
        $html = (string)$response->getContent();
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('no-store', (string)$response->headers->get('Cache-Control'));
        $this->assertStringContainsString('System Health Status', $html);
        $this->assertStringContainsString('check_result_critical', $html);
        $this->assertStringContainsString('check_result_skipped', $html);
        $this->assertStringContainsString('check_result_ok', $html);
        $this->assertStringContainsString('Found 2379 errors in application logs', $html);
        $this->assertStringContainsString('Database Accessible', $html);
        $this->assertStringContainsString('2026-09-23 10:00:00', $html);
        $this->assertStringContainsString('340 ms', $html);
        $this->assertStringContainsString('logs:application_log_errors', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    #[Test]
    public function dashboardAction_explainsHowToProduceAFirstRunWhenNothingIsStored(): void
    {
        // test
        $response = (new DashboardController(new InMemoryRunStore(), $this->guard(true), $this->twig(), true))->dashboardAction(new Request());

        // verify
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('basilicom:health-check:audit', (string)$response->getContent());
    }

    private function guard(bool $granted): AccessGuard
    {
        $session = $this->createMock(AdminSessionInterface::class);
        $session->method('isAdmin')->willReturn($granted);

        return new AccessGuard($session, null);
    }

    private function twig(): Environment
    {
        $loader = new FilesystemLoader();
        $loader->addPath(__DIR__ . '/../../../src/Resources/views', 'PimcorePluginHealthCheck');

        return new Environment($loader, ['strict_variables' => true]);
    }
}
