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

/**
 * A check that has something to say even when it passes. `check(): void` stays the contract for
 * the monitoring endpoint; `inspect()` is what the dashboard, the API and the audit command read.
 *
 * Reporting checks never throw from `inspect()`: whatever goes wrong becomes a Failure or a
 * NotAvailable report, so one broken check cannot hide the others.
 */
interface ReportingCheckInterface extends CheckInterface
{
    /**
     * Stable machine name, e.g. `system:php_version`. Used as the API key and to select a single
     * check on the command line.
     */
    public function identifier(): string;

    /** Human readable name shown on the dashboard, e.g. `PHP Version`. */
    public function label(): string;

    public function inspect(): Report;
}
