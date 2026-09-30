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

namespace Basilicom\PimcorePluginHealthCheck\Exception;

use Basilicom\PimcorePluginHealthCheck\Checks\Report;

/**
 * Bridges a reporting check into the throwing `check(): void` contract: a Warning or Failure
 * report becomes an exception of the same severity, so a consumer that only knows
 * CheckInterface still gets the right answer.
 */
class ReportedProblemException extends AbstractHealthCheckException
{
    public function __construct(private readonly Report $report)
    {
        parent::__construct($report->message, severity: $report->severity);
    }

    public function report(): Report
    {
        return $this->report;
    }
}
