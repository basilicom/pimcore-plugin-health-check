<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

use Doctrine\DBAL\Connection;

/**
 * Ratio of unpublished to total objects; sudden shifts hint at workflow problems (PF-88 metric 32).
 * Thresholds are compared against the percentage.
 */
final class UnpublishedObjectsCheck extends AbstractCheck
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
        return 'data:unpublished_objects';
    }

    public function getLabel(): string
    {
        return 'Unpublished Objects';
    }

    protected function doRun(): CheckResult
    {
        $total = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM objects WHERE `type` IN ('object', 'variant')",
        );
        $unpublished = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM objects WHERE `type` IN ('object', 'variant') AND published = 0",
        );

        $percent = $total > 0 ? round($unpublished / $total * 100, 1) : 0.0;

        return $this->thresholdResult(
            $percent,
            $this->threshold('warning_threshold'),
            $this->threshold('critical_threshold'),
            sprintf('%d of %d objects (%s%%) are unpublished.', $unpublished, $total, $percent),
            ['unpublished' => $unpublished, 'total' => $total, 'percent' => $percent],
        );
    }
}
