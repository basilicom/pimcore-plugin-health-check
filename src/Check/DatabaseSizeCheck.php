<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

use Basilicom\PimcorePluginHealthCheck\Util\Bytes;
use Doctrine\DBAL\Connection;

final class DatabaseSizeCheck extends AbstractCheck
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
        return 'device:database_size';
    }

    public function getLabel(): string
    {
        return 'Database Size';
    }

    protected function doRun(): CheckResult
    {
        $size = (int) $this->connection->fetchOne(
            'SELECT COALESCE(SUM(data_length + index_length), 0)
             FROM information_schema.TABLES
             WHERE table_schema = DATABASE()',
        );

        return $this->thresholdResult(
            $size,
            $this->threshold('warning_threshold'),
            $this->threshold('critical_threshold'),
            sprintf('Database size is %s', Bytes::format($size)),
            ['size_bytes' => $size, 'size_human' => Bytes::format($size)],
        );
    }
}
