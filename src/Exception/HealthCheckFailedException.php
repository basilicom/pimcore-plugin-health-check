<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Exception;

use Basilicom\PimcorePluginHealthCheck\Check\CheckResult;

/**
 * Thrown by HealthCheckService::check() so the legacy `/health-check-status`
 * endpoint keeps its `FAILURE: <message>` contract.
 */
final class HealthCheckFailedException extends AbstractHealthCheckException
{
    /**
     * @param list<CheckResult> $failedResults
     */
    public function __construct(private readonly array $failedResults, string $message)
    {
        parent::__construct($message);
    }

    /**
     * @return list<CheckResult>
     */
    public function getFailedResults(): array
    {
        return $this->failedResults;
    }
}
