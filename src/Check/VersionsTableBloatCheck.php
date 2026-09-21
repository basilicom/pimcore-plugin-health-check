<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

use Doctrine\DBAL\Connection;

final class VersionsTableBloatCheck extends AbstractCheck
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
        return 'database:versions_table_bloat';
    }

    public function getLabel(): string
    {
        return 'Versions Table';
    }

    protected function doRun(): CheckResult
    {
        $count = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM versions');

        return $this->thresholdResult(
            $count,
            $this->threshold('warning_threshold'),
            $this->threshold('critical_threshold'),
            sprintf('The versions table holds %d rows.', $count),
            ['count' => $count],
        );
    }
}
