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
use Basilicom\PimcorePluginHealthCheck\Controller\ApiController;
use Basilicom\PimcorePluginHealthCheck\Security\AccessGuard;
use Basilicom\PimcorePluginHealthCheck\Security\AdminSessionInterface;
use Basilicom\PimcorePluginHealthCheck\Services\CheckResult;
use Basilicom\PimcorePluginHealthCheck\Severity;
use Basilicom\PimcorePluginHealthCheck\Tests\Fixtures\InMemoryRunStore;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class ApiControllerTest extends TestCase
{
    private const string KEY = 'a-valid-api-key-of-16-chars-or-more';

    #[Test]
    public function apiAction_answersAnEmpty404WhenDisabledOrUnauthorised(): void
    {
        // test
        $disabled     = (new ApiController(new InMemoryRunStore(), $this->guard(true), false))->apiAction(new Request());
        $unauthorised = (new ApiController(new InMemoryRunStore(), $this->guard(false), true))->apiAction(new Request());

        // verify
        $this->assertSame(404, $disabled->getStatusCode());
        $this->assertSame('', $disabled->getContent());
        $this->assertSame(404, $unauthorised->getStatusCode());
    }

    #[Test]
    public function apiAction_acceptsTheApiKeyHeader(): void
    {
        // prepare
        $request = new Request();
        $request->headers->set(AccessGuard::API_KEY_HEADER, self::KEY);

        // test
        $response = (new ApiController(new InMemoryRunStore($this->storedRun()), $this->guard(false), true))->apiAction($request);

        // verify
        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function apiAction_returnsTheDocumentedPayload(): void
    {
        // test
        $response = (new ApiController(new InMemoryRunStore($this->storedRun()), $this->guard(true), true))->apiAction(new Request());
        $payload  = json_decode((string)$response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        // verify
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('critical', $payload['status']);
        $this->assertFalse($payload['healthy']);
        $this->assertSame('2026-09-23T10:00:00+02:00', $payload['generated_at']);
        $this->assertSame(['ok' => 1, 'warning' => 0, 'critical' => 1, 'skipped' => 0, 'na' => 0], $payload['summary']);
        $this->assertCount(2, $payload['checks']);
        $this->assertSame([
            'check'       => 'Foo\\ApplicationLogErrorsCheck',
            'identifier'  => 'logs:application_log_errors',
            'label'       => 'Application Log Errors',
            'status'      => 'critical',
            'message'     => 'Found 2379 errors in application logs in the last 24 hours.',
            'data'        => ['error_count' => 2379, 'lookback_hours' => 24],
            'duration_ms' => 12,
        ], $payload['checks'][0]);
    }

    #[Test]
    public function apiAction_filtersToOneCheckAnd404sAnUnknownIdentifier(): void
    {
        // test
        $one     = (new ApiController(new InMemoryRunStore($this->storedRun()), $this->guard(true), true))->apiAction(new Request(['check' => 'core:database_accessible']));
        $unknown = (new ApiController(new InMemoryRunStore($this->storedRun()), $this->guard(true), true))->apiAction(new Request(['check' => 'nope:nothing']));

        // verify
        $payload = json_decode((string)$one->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(1, $payload['checks']);
        $this->assertSame('core:database_accessible', $payload['checks'][0]['identifier']);
        $this->assertSame(404, $unknown->getStatusCode());
        $this->assertStringContainsString('Unknown check', (string)$unknown->getContent());
    }

    #[Test]
    public function apiAction_saysSoWhenNoRunIsStoredYet(): void
    {
        // test
        $response = (new ApiController(new InMemoryRunStore(), $this->guard(true), true))->apiAction(new Request());
        $payload  = json_decode((string)$response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        // verify
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('na', $payload['status']);
        $this->assertNull($payload['healthy']);
        $this->assertStringContainsString('basilicom:health-check:audit', $payload['message']);
    }

    private function storedRun(): StoredRun
    {
        return new StoredRun(new DateTimeImmutable('2026-09-23 10:00:00', new \DateTimeZone('Europe/Berlin')), [
            new CheckResult('Foo\\ApplicationLogErrorsCheck', Severity::Failure, null, 'Found 2379 errors in application logs in the last 24 hours.', ['error_count' => 2379, 'lookback_hours' => 24], 'logs:application_log_errors', 'Application Log Errors', 12),
            new CheckResult('Basilicom\\PimcorePluginHealthCheck\\Checks\\DatabaseAccessibleCheck'),
        ]);
    }

    private function guard(bool $adminSession): AccessGuard
    {
        $session = $this->createMock(AdminSessionInterface::class);
        $session->method('isAdmin')->willReturn($adminSession);

        return new AccessGuard($session, self::KEY);
    }
}
