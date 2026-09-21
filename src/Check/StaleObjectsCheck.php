<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

use Doctrine\DBAL\Connection;

/**
 * Objects that have not been modified for a long time - candidates for archival (PF-88 metric 31).
 */
final class StaleObjectsCheck extends AbstractCheck
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
        return 'data:stale_objects';
    }

    public function getLabel(): string
    {
        return 'Stale Objects';
    }

    protected function doRun(): CheckResult
    {
        $days = max(1, (int) $this->option('days', 365));
        $cutoff = time() - $days * 86400;

        $total = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM objects WHERE `type` IN ('object', 'variant')",
        );
        $stale = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM objects WHERE `type` IN ('object', 'variant') AND modificationDate < ?",
            [$cutoff],
        );

        $percent = $total > 0 ? round($stale / $total * 100, 1) : 0.0;

        return $this->thresholdResult(
            $stale,
            $this->threshold('warning_threshold'),
            $this->threshold('critical_threshold'),
            sprintf('%d of %d objects have not been modified in the last %d days.', $stale, $total, $days),
            ['stale' => $stale, 'total' => $total, 'percent' => $percent, 'days' => $days],
        );
    }
}
