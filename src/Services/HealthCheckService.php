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

namespace Basilicom\PimcorePluginHealthCheck\Services;

use Basilicom\PimcorePluginHealthCheck\Checks\CheckInterface;
use Basilicom\PimcorePluginHealthCheck\Checks\ReportingCheckInterface;
use Basilicom\PimcorePluginHealthCheck\Exception\AbstractHealthCheckException;
use Basilicom\PimcorePluginHealthCheck\Exception\HealthCheckTimedOutException;
use Basilicom\PimcorePluginHealthCheck\Severity;
use Throwable;

final readonly class HealthCheckService
{
    /** @param iterable<CheckInterface> $checks ordered by service tag priority */
    public function __construct(
        private iterable $checks,
        private int $timeoutMilliseconds,
    ) {
    }

    /**
     * Stopping at the first failure would let the response time reveal which check failed.
     *
     * @return list<CheckResult> one per check that ran, in order
     */
    public function run(): array
    {
        $deadline = hrtime(true) + ($this->timeoutMilliseconds * 1_000_000);
        $results  = [];

        foreach ($this->checks as $check) {
            if (!$check->isActive()) {
                continue;
            }

            // DBAL retries the full connect timeout per query, so without this the database
            // checks would each wait it out in turn
            if (hrtime(true) >= $deadline) {
                $results[] = new CheckResult(self::class, Severity::Failure, new HealthCheckTimedOutException(
                    sprintf('Run exceeded %d ms, remaining checks were skipped.', $this->timeoutMilliseconds)
                ));

                break;
            }

            $start = hrtime(true);

            try {
                // a reporting check has something to say when it passes, which check(): void cannot carry
                if ($check instanceof ReportingCheckInterface) {
                    $report    = $check->inspect();
                    $results[] = $this->result($check, $report->severity, null, $report->message, $report->data, $start);

                    continue;
                }

                $check->check();
                $results[] = $this->result($check, Severity::Ok, null, '', [], $start);

                continue;
            } catch (AbstractHealthCheckException $exception) {
                $result = $this->result($check, $exception->severity(), $exception, '', [], $start);
            } catch (Throwable $exception) {
                // anything that is not a health check exception is a defect, never a mere warning
                $result = $this->result($check, Severity::Failure, $exception, '', [], $start);
            }

            $results[] = $result;
        }

        return $results;
    }

    /** @param array<string, mixed> $data */
    private function result(
        CheckInterface $check,
        Severity $severity,
        ?Throwable $failure,
        string $message,
        array $data,
        int $start,
    ): CheckResult {
        $reporting = $check instanceof ReportingCheckInterface;

        return new CheckResult(
            $check::class,
            $severity,
            $failure,
            $message,
            $data,
            $reporting ? $check->identifier() : null,
            $reporting ? $check->label() : null,
            intdiv(hrtime(true) - $start, 1_000_000),
        );
    }
}
