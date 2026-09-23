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

            try {
                $check->check();
                $results[] = new CheckResult($check::class);

                continue;
            } catch (AbstractHealthCheckException $exception) {
                $result = new CheckResult($check::class, $exception->severity(), $exception);
            } catch (Throwable $exception) {
                // anything that is not a health check exception is a defect, never a mere warning
                $result = new CheckResult($check::class, Severity::Failure, $exception);
            }

            $results[] = $result;
        }

        return $results;
    }
}
