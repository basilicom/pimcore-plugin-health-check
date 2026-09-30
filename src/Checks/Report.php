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

namespace Basilicom\PimcorePluginHealthCheck\Checks;

use Basilicom\PimcorePluginHealthCheck\Severity;

/**
 * What a reporting check found: the severity, a sentence for humans and the measured values
 * behind it. A passing check has a message too - "Current PHP version is 8.3.12" is worth
 * showing, which is why `check(): void` alone is not enough for the dashboard.
 */
final readonly class Report
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public Severity $severity,
        public string $message,
        public array $data = [],
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function ok(string $message, array $data = []): self
    {
        return new self(Severity::Ok, $message, $data);
    }

    /** @param array<string, mixed> $data */
    public static function warning(string $message, array $data = []): self
    {
        return new self(Severity::Warning, $message, $data);
    }

    /** @param array<string, mixed> $data */
    public static function failure(string $message, array $data = []): self
    {
        return new self(Severity::Failure, $message, $data);
    }

    /** @param array<string, mixed> $data */
    public static function skipped(string $message, array $data = []): self
    {
        return new self(Severity::Skipped, $message, $data);
    }

    /** @param array<string, mixed> $data */
    public static function notAvailable(string $message, array $data = []): self
    {
        return new self(Severity::NotAvailable, $message, $data);
    }

    /**
     * Grades a measured value against two inclusive thresholds. A null threshold disables that
     * level. When the failure threshold is the lower one the value is graded the other way round
     * ("less is worse"), which covers free space and remaining quota.
     *
     * @param array<string, mixed> $data
     */
    public static function graded(
        int|float $value,
        int|float|null $warning,
        int|float|null $failure,
        string $message,
        array $data = [],
    ): self {
        $lessIsWorse = $warning !== null && $failure !== null && $failure < $warning;
        $exceeds     = static fn (int|float $threshold): bool => $lessIsWorse ? $value <= $threshold : $value >= $threshold;

        if ($failure !== null && $exceeds($failure)) {
            return self::failure($message, $data);
        }

        if ($warning !== null && $exceeds($warning)) {
            return self::warning($message, $data);
        }

        return self::ok($message, $data);
    }

    public function isProblem(): bool
    {
        return $this->severity === Severity::Warning || $this->severity === Severity::Failure;
    }
}
