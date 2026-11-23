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

namespace Basilicom\PimcorePluginHealthCheck\Checks\Audit;

use Basilicom\PimcorePluginHealthCheck\Checks\AbstractReportingCheck;
use Basilicom\PimcorePluginHealthCheck\Checks\Report;
use Doctrine\DBAL\Connection;
use Throwable;

final readonly class ApplicationLogErrorsCheck extends AbstractReportingCheck
{
    public function __construct(
        bool $enabled,
        private Connection $connection,
        private int $lookbackHours,
        private int|float|null $warningThreshold,
        private int|float|null $failureThreshold,
    ) {
        parent::__construct($enabled);
    }

    public function identifier(): string
    {
        return 'logs:application_log_errors';
    }

    public function label(): string
    {
        return 'Application Log Errors';
    }

    protected function examine(): Report
    {
        try {
            $count = (int)$this->connection->fetchOne(
                'SELECT COUNT(*) FROM application_logs
                 WHERE priority IN (?, ?, ?, ?) AND timestamp >= DATE_SUB(NOW(), INTERVAL ? HOUR)',
                ['emergency', 'alert', 'critical', 'error', $this->lookbackHours]
            );
        } catch (Throwable $exception) {
            return str_contains(strtolower($exception->getMessage()), 'application_logs')
                ? Report::notAvailable('No application_logs table - the application logger bundle is not installed (n/a)')
                : Report::failure('Could not query the application_logs table');
        }

        return Report::graded(
            $count,
            $this->warningThreshold,
            $this->failureThreshold,
            sprintf('Found %d errors in application logs in the last %d hours.', $count, $this->lookbackHours),
            ['error_count' => $count, 'lookback_hours' => $this->lookbackHours]
        );
    }
}
