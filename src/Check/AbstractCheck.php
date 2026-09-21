<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

use Throwable;

/**
 * Base class for all checks.
 *
 * Sub classes implement `doRun()` and use the `ok()`, `warning()`, `critical()`, `skipped()`,
 * `na()` and `thresholdResult()` helpers to build their result. Timing and exception handling
 * are taken care of here, so `run()` never throws.
 */
abstract class AbstractCheck implements CheckInterface
{
    /**
     * @param array<string, mixed> $config the `checks.<key>` section of the bundle configuration
     */
    public function __construct(protected readonly array $config = [])
    {
    }

    abstract public function getIdentifier(): string;

    abstract public function getLabel(): string;

    public function isEnabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? true);
    }

    final public function run(): CheckResult
    {
        $start = hrtime(true);

        try {
            $result = $this->doRun();
        } catch (Throwable $throwable) {
            $result = $this->critical(
                sprintf('Check failed with %s: %s', $throwable::class, $throwable->getMessage()),
                ['exception' => $throwable::class],
            );
        }

        return $result->withDurationMs((int) round((hrtime(true) - $start) / 1_000_000));
    }

    abstract protected function doRun(): CheckResult;

    protected function option(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Reads a threshold option. Non numeric values (`null`, an empty string) disable the level.
     */
    protected function threshold(string $key): int|float|null
    {
        $value = $this->config[$key] ?? null;

        return is_numeric($value) ? $value + 0 : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function ok(string $message, array $data = []): CheckResult
    {
        return $this->result(CheckStatus::Ok, $message, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function warning(string $message, array $data = []): CheckResult
    {
        return $this->result(CheckStatus::Warning, $message, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function critical(string $message, array $data = []): CheckResult
    {
        return $this->result(CheckStatus::Critical, $message, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function skipped(string $message = 'Check was skipped', array $data = []): CheckResult
    {
        return $this->result(CheckStatus::Skipped, $message, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function na(string $message, array $data = []): CheckResult
    {
        return $this->result(CheckStatus::NotAvailable, $message, $data);
    }

    /**
     * Grades a measured value against a warning and a critical threshold.
     *
     * Thresholds are inclusive. When the critical threshold is lower than the warning
     * threshold the value is graded the other way round ("less is worse"), which allows
     * using the helper for free disk space, remaining quota and similar measures.
     * A `null` threshold disables that level.
     *
     * @param array<string, mixed> $data
     */
    protected function thresholdResult(
        int|float $value,
        int|float|null $warning,
        int|float|null $critical,
        string $message,
        array $data = [],
    ): CheckResult {
        $lessIsWorse = $warning !== null && $critical !== null && $critical < $warning;

        $exceeds = static fn (int|float $threshold): bool => $lessIsWorse
            ? $value <= $threshold
            : $value >= $threshold;

        if ($critical !== null && $exceeds($critical)) {
            return $this->critical($message, $data);
        }

        if ($warning !== null && $exceeds($warning)) {
            return $this->warning($message, $data);
        }

        return $this->ok($message, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function result(CheckStatus $status, string $message, array $data): CheckResult
    {
        return new CheckResult($this->getIdentifier(), $this->getLabel(), $status, $message, $data);
    }
}
