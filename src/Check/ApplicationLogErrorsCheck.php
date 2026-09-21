<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

use Doctrine\DBAL\Connection;
use Throwable;

final class ApplicationLogErrorsCheck extends AbstractCheck
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config, private readonly Connection $connection)
    {
        parent::__construct($config);
    }

    public function getIdentifier(): string
    {
        return 'logs:application_log_errors';
    }

    public function getLabel(): string
    {
        return 'Application Log Errors';
    }

    protected function doRun(): CheckResult
    {
        $lookbackHours = (int) $this->option('lookback_hours', 24);

        try {
            $count = (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM application_logs
                 WHERE priority IN (?, ?, ?, ?)
                   AND timestamp >= DATE_SUB(NOW(), INTERVAL ? HOUR)',
                ['emergency', 'alert', 'critical', 'error', $lookbackHours],
            );
        } catch (Throwable $throwable) {
            return $this->na('Could not query application_logs table: ' . $throwable->getMessage());
        }

        return $this->thresholdResult(
            $count,
            $this->threshold('warning_threshold'),
            $this->threshold('critical_threshold'),
            sprintf('Found %d errors in application logs in the last %d hours.', $count, $lookbackHours),
            ['error_count' => $count, 'lookback_hours' => $lookbackHours],
        );
    }
}
