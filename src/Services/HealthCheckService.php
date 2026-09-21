<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Services;

use Basilicom\PimcorePluginHealthCheck\Check\CheckInterface;
use Basilicom\PimcorePluginHealthCheck\Check\CheckResult;
use Basilicom\PimcorePluginHealthCheck\Check\CheckStatus;
use Basilicom\PimcorePluginHealthCheck\Exception\HealthCheckFailedException;

/**
 * Collects every service tagged `pimcore_plugin_health_check.check` and runs them.
 */
class HealthCheckService
{
    private const BUNDLE_CHECK_NAMESPACE = 'Basilicom\\PimcorePluginHealthCheck\\Check\\';

    /**
     * @param iterable<CheckInterface> $checks
     */
    public function __construct(private readonly iterable $checks)
    {
    }

    /**
     * @return list<CheckInterface>
     */
    public function getEnabledChecks(): array
    {
        $checks = [];

        foreach ($this->checks as $check) {
            if ($check->isEnabled()) {
                $checks[] = $check;
            }
        }

        // project checks first, bundle checks second - each group alphabetically, as in the ticket
        usort($checks, static function (CheckInterface $a, CheckInterface $b): int {
            $groupA = str_starts_with($a::class, self::BUNDLE_CHECK_NAMESPACE) ? 1 : 0;
            $groupB = str_starts_with($b::class, self::BUNDLE_CHECK_NAMESPACE) ? 1 : 0;

            return $groupA <=> $groupB ?: strcasecmp($a->getLabel(), $b->getLabel());
        });

        return $checks;
    }

    /**
     * @param string|null $identifier restricts the run to a single check
     *
     * @return list<CheckResult>
     */
    public function runAll(?string $identifier = null): array
    {
        $results = [];

        foreach ($this->getEnabledChecks() as $check) {
            if ($identifier !== null && $check->getIdentifier() !== $identifier) {
                continue;
            }

            $results[] = $check->run();
        }

        return $results;
    }

    /**
     * @param list<CheckResult> $results
     */
    public function isHealthy(array $results): bool
    {
        return $this->getFailures($results) === [];
    }

    /**
     * @param list<CheckResult> $results
     *
     * @return list<CheckResult>
     */
    public function getFailures(array $results): array
    {
        return array_values(array_filter(
            $results,
            static fn (CheckResult $result): bool => $result->isFailure(CheckStatus::Critical),
        ));
    }

    /**
     * @param list<CheckResult> $results
     */
    public function getOverallStatus(array $results): CheckStatus
    {
        $status = CheckStatus::Ok;

        foreach ($results as $result) {
            if ($result->status->severity() > $status->severity()) {
                $status = $result->status;
            }
        }

        return $status;
    }

    /**
     * @param list<CheckResult> $results
     *
     * @return array<string, int>
     */
    public function getSummary(array $results): array
    {
        $summary = [];

        foreach (CheckStatus::cases() as $case) {
            $summary[$case->value] = 0;
        }

        foreach ($results as $result) {
            ++$summary[$result->status->value];
        }

        return $summary;
    }

    /**
     * Legacy entry point of the `/health-check-status` endpoint.
     *
     * @throws HealthCheckFailedException
     */
    public function check(): void
    {
        $results = $this->runAll();
        $failures = $this->getFailures($results);

        if ($failures === []) {
            return;
        }

        $message = implode(' | ', array_map(
            static fn (CheckResult $result): string => $result->label . ': ' . $result->message,
            $failures,
        ));

        throw new HealthCheckFailedException($failures, $message);
    }
}
