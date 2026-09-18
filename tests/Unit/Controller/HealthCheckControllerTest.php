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

use Basilicom\PimcorePluginHealthCheck\Checks\CheckInterface;
use Basilicom\PimcorePluginHealthCheck\Controller\HealthCheckController;
use Basilicom\PimcorePluginHealthCheck\Exception\AbstractHealthCheckException;
use Basilicom\PimcorePluginHealthCheck\Exception\AdminUserActiveException;
use Basilicom\PimcorePluginHealthCheck\Services\HealthCheckService;
use Basilicom\PimcorePluginHealthCheck\Severity;
use Basilicom\PimcorePluginHealthCheck\Tests\Fixtures\Checks\SpyCheck;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class HealthCheckControllerTest extends TestCase
{
    private const string FAILURE_PATTERN = '/^FAILURE: \[[0-9a-f]{16}\]$/';
    private const string TOKEN           = 'super-secret-test-token';
    private const int TIMEOUT_MS         = 5000;

    #[Test]
    public function checkAction_returnsSuccessAndLogsNothingWhenAllChecksPass(): void
    {
        // prepare
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        $service = new HealthCheckService([$this->createStubCheck(function (): void {
        })], self::TIMEOUT_MS);
        $controller = new HealthCheckController($service, $logger);

        // test
        $response = $controller->checkAction(new Request());

        // verify
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('SUCCESS', $response->getContent());
        $this->assertSame('text/plain; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', (string)$response->headers->get('Cache-Control'));
    }

    #[Test]
    public function checkAction_returnsOpaqueBodyAndLogsTheReasonWhenAHealthCheckExceptionOccurs(): void
    {
        // prepare
        $message = 'The default administrator account "admin" is active.';

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with($this->callback(static fn (string $logged): bool => str_contains($logged, $message)));

        $service = new HealthCheckService([$this->createStubCheck(function () use ($message): void {
            throw new AdminUserActiveException($message);
        })], self::TIMEOUT_MS);
        $controller = new HealthCheckController($service, $logger);

        // test
        $response = $controller->checkAction(new Request());

        // verify
        $this->assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        $this->assertMatchesRegularExpression(self::FAILURE_PATTERN, (string)$response->getContent());

        $body = strtolower((string)$response->getContent());
        $this->assertStringNotContainsString('admin', $body);
        $this->assertStringNotContainsString('administrator', $body);
        $this->assertStringNotContainsString('adminuseractive', $body);
        $this->assertStringNotContainsString(strtolower($message), $body);
    }

    #[Test]
    public function checkAction_returnsOpaqueBodyAndLogsTheReasonForUnexpectedThrowables(): void
    {
        // prepare
        $message = 'SQLSTATE[HY000] connection refused to db-host-01';

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with($this->callback(static fn (string $logged): bool => str_contains($logged, $message)));

        $service = new HealthCheckService([$this->createStubCheck(function () use ($message): void {
            throw new RuntimeException($message);
        })], self::TIMEOUT_MS);
        $controller = new HealthCheckController($service, $logger);

        // test
        $response = $controller->checkAction(new Request());

        // verify
        $this->assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        $this->assertMatchesRegularExpression(self::FAILURE_PATTERN, (string)$response->getContent());

        $body = strtolower((string)$response->getContent());
        $this->assertStringNotContainsString('sqlstate', $body);
        $this->assertStringNotContainsString('db-host-01', $body);
    }

    #[Test]
    public function checkAction_returnsSuccessAndLogsAWarningWhenACheckOnlyWarns(): void
    {
        // prepare
        $message = 'Cache is degraded but still serving.';

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');
        $logger->expects($this->once())
            ->method('warning')
            ->with($this->callback(static fn (string $logged): bool => str_contains($logged, $message)));

        $service = new HealthCheckService([$this->createStubCheck(function () use ($message): void {
            throw new class ($message, null, Severity::Warning) extends AbstractHealthCheckException {
            };
        })], self::TIMEOUT_MS);
        $controller = new HealthCheckController($service, $logger);

        // test
        $response = $controller->checkAction(new Request());

        // verify
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('SUCCESS', $response->getContent());
    }

    #[Test]
    public function checkAction_returns503AndKeepsTheOpaqueBodyWhenAFailureOccursAlongsideAWarning(): void
    {
        // prepare
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');
        $logger->expects($this->once())->method('warning');

        $service = new HealthCheckService([
            $this->createStubCheck(function (): void {
                throw new class ('degraded', null, Severity::Warning) extends AbstractHealthCheckException {
                };
            }),
            $this->createStubCheck(function (): void {
                throw new AdminUserActiveException('The default administrator account "admin" is active.');
            }),
        ], self::TIMEOUT_MS);
        $controller = new HealthCheckController($service, $logger);

        // test
        $response = $controller->checkAction(new Request());

        // verify
        $this->assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        $this->assertMatchesRegularExpression(self::FAILURE_PATTERN, (string)$response->getContent());
    }

    #[Test]
    public function checkAction_logsEveryFailingCheckInsteadOfStoppingAtTheFirst(): void
    {
        // prepare
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))->method('error');

        $service = new HealthCheckService([
            $this->createStubCheck(function (): void {
                throw new AdminUserActiveException('The default administrator account "admin" is active.');
            }),
            $this->createStubCheck(function (): void {
                throw new RuntimeException('SQLSTATE[HY000] connection refused to db-host-01');
            }),
        ], self::TIMEOUT_MS);
        $controller = new HealthCheckController($service, $logger);

        // test
        $response = $controller->checkAction(new Request());

        // verify
        $this->assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
    }

    #[Test]
    public function checkAction_returns404WithAnEmptyBodyAndLogsANoticeWhenATokenIsRequiredButMissing(): void
    {
        // prepare
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('notice');

        $service = new HealthCheckService([$this->createStubCheck(function (): void {
        })], self::TIMEOUT_MS);
        $controller = new HealthCheckController($service, $logger, self::TOKEN);

        // test
        $response = $controller->checkAction(new Request());

        // verify
        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('', $response->getContent());

        $body = strtolower((string)$response->getContent());
        $this->assertStringNotContainsString('failure', $body);
        $this->assertStringNotContainsString('success', $body);
        $this->assertStringNotContainsString('token', $body);
    }

    #[Test]
    public function checkAction_returns404WithAnEmptyBodyWhenTheHeaderTokenIsWrong(): void
    {
        // prepare
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('notice');

        $service = new HealthCheckService([$this->createStubCheck(function (): void {
        })], self::TIMEOUT_MS);
        $controller = new HealthCheckController($service, $logger, self::TOKEN);

        // test
        $response = $controller->checkAction(new Request([], [], [], [], [], ['HTTP_X_HEALTH_CHECK_TOKEN' => 'wrong']));

        // verify
        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
    }

    #[Test]
    public function checkAction_returns404WithAnEmptyBodyWhenTheQueryTokenIsWrong(): void
    {
        // prepare
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('notice');

        $service = new HealthCheckService([$this->createStubCheck(function (): void {
        })], self::TIMEOUT_MS);
        $controller = new HealthCheckController($service, $logger, self::TOKEN);

        // test
        $response = $controller->checkAction(new Request(['token' => 'wrong']));

        // verify
        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
    }

    #[Test]
    public function checkAction_returnsSuccessWhenTheCorrectHeaderTokenIsPresented(): void
    {
        // prepare
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('notice');

        $service = new HealthCheckService([$this->createStubCheck(function (): void {
        })], self::TIMEOUT_MS);
        $controller = new HealthCheckController($service, $logger, self::TOKEN);

        // test
        $response = $controller->checkAction(
            new Request([], [], [], [], [], ['HTTP_X_HEALTH_CHECK_TOKEN' => self::TOKEN])
        );

        // verify
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('SUCCESS', $response->getContent());
    }

    #[Test]
    public function checkAction_returnsSuccessWhenTheCorrectQueryTokenIsPresented(): void
    {
        // prepare
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('notice');

        $service = new HealthCheckService([$this->createStubCheck(function (): void {
        })], self::TIMEOUT_MS);
        $controller = new HealthCheckController($service, $logger, self::TOKEN);

        // test
        $response = $controller->checkAction(new Request(['token' => self::TOKEN]));

        // verify
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('SUCCESS', $response->getContent());
    }

    #[Test]
    public function checkAction_neverRunsAnyCheckWhenTheTokenIsRejected(): void
    {
        // prepare
        $logger     = $this->createMock(LoggerInterface::class);
        $spy        = new SpyCheck();
        $service    = new HealthCheckService([$spy], self::TIMEOUT_MS);
        $controller = new HealthCheckController($service, $logger, self::TOKEN);

        // test
        $controller->checkAction(new Request());

        // verify
        $this->assertFalse($spy->wasCalled());
    }

    #[Test]
    #[DataProvider('nonScalarQueryTokenProvider')]
    public function checkAction_answersTheOpaque404WhenTheQueryTokenIsNotAString(mixed $queryToken): void
    {
        // prepare
        $spy        = new SpyCheck();
        $service    = new HealthCheckService([$spy], self::TIMEOUT_MS);
        $controller = new HealthCheckController($service, $this->createMock(LoggerInterface::class), self::TOKEN);

        // test
        $response = $controller->checkAction(new Request(['token' => $queryToken]));

        // verify
        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
        $this->assertFalse($spy->wasCalled());
    }

    /**
     * InputBag::get() throws on a non-scalar, which the kernel turns into a 400 and would tell an
     * anonymous caller that the route exists.
     */
    public static function nonScalarQueryTokenProvider(): array
    {
        return [
            'list'   => [['x']],
            'nested' => [['a' => ['b']]],
            'empty'  => [[]],
        ];
    }

    #[Test]
    public function liveAction_returnsSuccessWithoutATokenConfigured(): void
    {
        // prepare
        $service    = new HealthCheckService([], self::TIMEOUT_MS);
        $controller = new HealthCheckController($service, $this->createMock(LoggerInterface::class));

        // test
        $response = $controller->liveAction(new Request());

        // verify
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('SUCCESS', $response->getContent());
        $this->assertSame('text/plain; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', (string)$response->headers->get('Cache-Control'));
    }

    #[Test]
    public function liveAction_neverRunsAnyCheckEvenWhenOneIsConfiguredToFail(): void
    {
        // prepare
        $spy        = new SpyCheck();
        $service    = new HealthCheckService([$spy], self::TIMEOUT_MS);
        $controller = new HealthCheckController($service, $this->createMock(LoggerInterface::class));

        // test
        $response = $controller->liveAction(new Request());

        // verify
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertFalse($spy->wasCalled());
    }

    #[Test]
    public function liveAction_returns404WithAnEmptyBodyWhenTheTokenIsMissing(): void
    {
        // prepare
        $service    = new HealthCheckService([], self::TIMEOUT_MS);
        $controller = new HealthCheckController($service, $this->createMock(LoggerInterface::class), self::TOKEN);

        // test
        $response = $controller->liveAction(new Request());

        // verify
        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
    }

    #[Test]
    public function liveAction_returns404WithAnEmptyBodyWhenTheTokenIsWrong(): void
    {
        // prepare
        $service    = new HealthCheckService([], self::TIMEOUT_MS);
        $controller = new HealthCheckController($service, $this->createMock(LoggerInterface::class), self::TOKEN);

        // test
        $response = $controller->liveAction(
            new Request([], [], [], [], [], ['HTTP_X_HEALTH_CHECK_TOKEN' => 'wrong'])
        );

        // verify
        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
    }

    #[Test]
    public function liveAction_returnsSuccessWhenTheCorrectTokenIsPresented(): void
    {
        // prepare
        $service    = new HealthCheckService([], self::TIMEOUT_MS);
        $controller = new HealthCheckController($service, $this->createMock(LoggerInterface::class), self::TOKEN);

        // test
        $response = $controller->liveAction(
            new Request([], [], [], [], [], ['HTTP_X_HEALTH_CHECK_TOKEN' => self::TOKEN])
        );

        // verify
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('SUCCESS', $response->getContent());
    }

    private function createStubCheck(callable $onCheck): CheckInterface
    {
        return new class ($onCheck) implements CheckInterface {
            public function __construct(private $onCheck)
            {
            }

            public function check(): void
            {
                ($this->onCheck)();
            }

            public function isActive(): bool
            {
                return true;
            }
        };
    }
}
