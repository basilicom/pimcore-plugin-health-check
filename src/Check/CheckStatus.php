<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

/**
 * Result status of a single health check.
 *
 * The string values are the machine readable status names used in the JSON API,
 * `label()` returns the user facing label known from the instride/pimcore-monitor bundle.
 */
enum CheckStatus: string
{
    case Ok = 'ok';
    case Warning = 'warning';
    case Critical = 'critical';
    case Skipped = 'skipped';
    case NotAvailable = 'na';

    public function label(): string
    {
        return 'check_result_' . $this->value;
    }

    public function icon(): string
    {
        return match ($this) {
            self::Ok => '✓',
            self::Warning => '!',
            self::Critical => '✕',
            self::Skipped, self::NotAvailable => '–',
        };
    }

    /**
     * Higher is worse. Used to derive the overall status of a run.
     */
    public function severity(): int
    {
        return match ($this) {
            self::Skipped => 0,
            self::NotAvailable => 1,
            self::Ok => 2,
            self::Warning => 3,
            self::Critical => 4,
        };
    }
}
