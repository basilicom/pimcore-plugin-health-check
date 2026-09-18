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

namespace Basilicom\PimcorePluginHealthCheck\Tests\Unit\Services;

use Basilicom\PimcorePluginHealthCheck\Checks\CheckInterface;
use Basilicom\PimcorePluginHealthCheck\Exception\AbstractHealthCheckException;
use Basilicom\PimcorePluginHealthCheck\Exception\HealthCheckTimedOutException;
use Basilicom\PimcorePluginHealthCheck\Services\HealthCheckService;
use Basilicom\PimcorePluginHealthCheck\Severity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class HealthCheckServiceTest extends TestCase
{
    // large enough that the time budget never interferes with the behavioural tests below
    private const int GENEROUS_TIMEOUT_MS = 5000;

    #[Test]
    public function run_callsOnlyActiveChecksInOrderAndReturnsOneResultEach(): void
    {
        // prepare
        $called = [];

        $active1 = $this->createStubCheck(true, static function () use (&$called): void {
            $called[] = 'active1';
        });
        $inactive = $this->createStubCheck(false, static function () use (&$called): void {
            $called[] = 'inactive';
        });
        $active2 = $this->createStubCheck(true, static function () use (&$called): void {
            $called[] = 'active2';
        });

        $service = new HealthCheckService([$active1, $inactive, $active2], self::GENEROUS_TIMEOUT_MS);

        // test
        $results = $service->run();

        // verify
        $this->assertSame(['active1', 'active2'], $called);
        $this->assertCount(2, $results);
        $this->assertSame($active1::class, $results[0]->check);
        $this->assertSame($active2::class, $results[1]->check);
        $this->assertFalse($results[0]->hasFailed());
        $this->assertFalse($results[1]->hasFailed());
    }

    #[Test]
    public function run_capturesAFailingCheckAsResultInsteadOfThrowing(): void
    {
        // prepare
        $exception = new class ('boom') extends AbstractHealthCheckException {
        };
        $failing = $this->createStubCheck(true, static function () use ($exception): void {
            throw $exception;
        });

        $service = new HealthCheckService([$failing], self::GENEROUS_TIMEOUT_MS);

        // test
        $results = $service->run();

        // verify
        $this->assertCount(1, $results);
        $this->assertTrue($results[0]->hasFailed());
        $this->assertSame($exception, $results[0]->failure);
    }

    #[Test]
    public function run_capturesANonHealthCheckExceptionAsFailureToo(): void
    {
        // prepare
        $exception = new RuntimeException('unexpected');
        $failing   = $this->createStubCheck(true, static function () use ($exception): void {
            throw $exception;
        });

        $service = new HealthCheckService([$failing], self::GENEROUS_TIMEOUT_MS);

        // test
        $results = $service->run();

        // verify
        $this->assertTrue($results[0]->hasFailed());
        $this->assertSame($exception, $results[0]->failure);
    }

    #[Test]
    public function run_runsAllActiveChecksEvenAfterAFailure(): void
    {
        // prepare
        $called = [];

        $failing = $this->createStubCheck(true, static function () use (&$called): void {
            $called[] = 'failing';

            throw new class ('boom') extends AbstractHealthCheckException {
            };
        });
        $afterFailure = $this->createStubCheck(true, static function () use (&$called): void {
            $called[] = 'afterFailure';
        });

        $service = new HealthCheckService([$failing, $afterFailure], self::GENEROUS_TIMEOUT_MS);

        // test
        $results = $service->run();

        // verify
        $this->assertSame(['failing', 'afterFailure'], $called);
        $this->assertCount(2, $results);
        $this->assertTrue($results[0]->hasFailed());
        $this->assertFalse($results[1]->hasFailed());
    }

    #[Test]
    public function run_returnsEmptyArrayForEmptyIterable(): void
    {
        // prepare
        $service = new HealthCheckService([], self::GENEROUS_TIMEOUT_MS);

        // test
        $results = $service->run();

        // verify
        $this->assertSame([], $results);
    }

    #[Test]
    public function run_returnsEmptyArrayWhenOnlyInactiveChecksArePresent(): void
    {
        // prepare
        $service = new HealthCheckService([
            $this->createStubCheck(false, static function (): void {
            }),
            $this->createStubCheck(false, static function (): void {
            }),
        ], self::GENEROUS_TIMEOUT_MS);

        // test
        $results = $service->run();

        // verify
        $this->assertSame([], $results);
    }

    #[Test]
    public function run_mapsAWarningSeverityExceptionToAWarningResult(): void
    {
        // prepare
        $exception = new class ('degraded', null, Severity::Warning) extends AbstractHealthCheckException {
        };
        $warning = $this->createStubCheck(true, static function () use ($exception): void {
            throw $exception;
        });

        $service = new HealthCheckService([$warning], self::GENEROUS_TIMEOUT_MS);

        // test
        $results = $service->run();

        // verify
        $this->assertTrue($results[0]->isWarning());
        $this->assertFalse($results[0]->hasFailed());
        $this->assertSame($exception, $results[0]->failure);
    }

    #[Test]
    public function run_runsAllActiveChecksEvenAfterAWarning(): void
    {
        // prepare
        $called = [];

        $warning = $this->createStubCheck(true, static function () use (&$called): void {
            $called[] = 'warning';

            throw new class ('degraded', null, Severity::Warning) extends AbstractHealthCheckException {
            };
        });
        $afterWarning = $this->createStubCheck(true, static function () use (&$called): void {
            $called[] = 'afterWarning';
        });

        $service = new HealthCheckService([$warning, $afterWarning], self::GENEROUS_TIMEOUT_MS);

        // test
        $results = $service->run();

        // verify
        $this->assertSame(['warning', 'afterWarning'], $called);
        $this->assertCount(2, $results);
        $this->assertTrue($results[0]->isWarning());
        $this->assertFalse($results[1]->hasFailed());
    }

    #[Test]
    public function run_aRuntimeExceptionAfterAWarningIsStillMappedToFailure(): void
    {
        // prepare
        $warning = $this->createStubCheck(true, static function (): void {
            throw new class ('degraded', null, Severity::Warning) extends AbstractHealthCheckException {
            };
        });
        $unexpected = $this->createStubCheck(true, static function (): void {
            throw new RuntimeException('unexpected');
        });

        $service = new HealthCheckService([$warning, $unexpected], self::GENEROUS_TIMEOUT_MS);

        // test
        $results = $service->run();

        // verify
        $this->assertTrue($results[0]->isWarning());
        $this->assertSame(Severity::Failure, $results[1]->severity);
        $this->assertTrue($results[1]->hasFailed());
    }

    #[Test]
    public function run_stopsStartingFurtherChecksWhenTheTimeBudgetIsExhausted(): void
    {
        // prepare
        $called = [];

        $slow = $this->createStubCheck(true, static function () use (&$called): void {
            $called[] = 'slow';
            usleep(150_000);
        });
        $afterBudget1 = $this->createStubCheck(true, static function () use (&$called): void {
            $called[] = 'afterBudget1';
        });
        $afterBudget2 = $this->createStubCheck(true, static function () use (&$called): void {
            $called[] = 'afterBudget2';
        });

        $service = new HealthCheckService([$slow, $afterBudget1, $afterBudget2], 50);

        // test
        $results = $service->run();

        // verify
        $this->assertSame(['slow'], $called);
        $this->assertCount(2, $results);

        $timeoutResult = $results[1];
        $this->assertSame(HealthCheckService::class, $timeoutResult->check);
        $this->assertTrue($timeoutResult->hasFailed());
        $this->assertInstanceOf(HealthCheckTimedOutException::class, $timeoutResult->failure);
        $this->assertStringContainsString('Run exceeded 50 ms', $timeoutResult->reason());
    }

    #[Test]
    public function run_appendsNoTimeoutResultWhenTheBudgetIsGenerous(): void
    {
        // prepare
        $checks = [
            $this->createStubCheck(true, static function (): void {
            }),
            $this->createStubCheck(true, static function (): void {
            }),
        ];

        $service = new HealthCheckService($checks, self::GENEROUS_TIMEOUT_MS);

        // test
        $results = $service->run();

        // verify
        $this->assertCount(count($checks), $results);
    }

    #[Test]
    public function run_doesNotAbortAnAlreadyRunningCheckWhenTheBudgetExpiresDuringIt(): void
    {
        // prepare
        $slow = $this->createStubCheck(true, static function (): void {
            usleep(150_000);
        });

        $service = new HealthCheckService([$slow], 50);

        // test
        $results = $service->run();

        // verify
        $this->assertCount(1, $results);
        $this->assertSame($slow::class, $results[0]->check);
        $this->assertFalse($results[0]->hasFailed());
    }

    private function createStubCheck(bool $isActive, callable $onCheck): CheckInterface
    {
        return new class ($isActive, $onCheck) implements CheckInterface {
            public function __construct(private bool $isActive, private $onCheck)
            {
            }

            public function check(): void
            {
                ($this->onCheck)();
            }

            public function isActive(): bool
            {
                return $this->isActive;
            }
        };
    }
}
