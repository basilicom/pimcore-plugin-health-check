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

namespace Basilicom\PimcorePluginHealthCheck;

enum Severity
{
    case Ok;

    /**
     * Degraded but still able to serve requests, so the endpoint stays green.
     */
    case Warning;

    case Failure;

    /**
     * The check had nothing to examine in this run - a request-bound check on the CLI, for
     * example. Neither good nor bad, and never a reason for the endpoint to change its answer.
     */
    case Skipped;

    /**
     * The check could not obtain its data: an external service was unreachable, a binary is not
     * installed, a table does not exist. Distinct from Failure on purpose - an offline
     * endoflife.date must never look like a broken node.
     */
    case NotAvailable;

    /**
     * The status label rendered on the dashboard and in the API, kept identical to the
     * instride/pimcore-monitor labels so existing users recognise them.
     */
    public function label(): string
    {
        return match ($this) {
            self::Ok           => 'check_result_ok',
            self::Warning      => 'check_result_warning',
            self::Failure      => 'check_result_critical',
            self::Skipped      => 'check_result_skipped',
            self::NotAvailable => 'check_result_na',
        };
    }

    public static function fromKey(string $key): self
    {
        foreach (self::cases() as $case) {
            if ($case->key() === $key) {
                return $case;
            }
        }

        throw new \ValueError(sprintf('Unknown severity key "%s".', $key));
    }

    /** Machine readable name used in the JSON API. */
    public function key(): string
    {
        return match ($this) {
            self::Ok           => 'ok',
            self::Warning      => 'warning',
            self::Failure      => 'critical',
            self::Skipped      => 'skipped',
            self::NotAvailable => 'na',
        };
    }
}
