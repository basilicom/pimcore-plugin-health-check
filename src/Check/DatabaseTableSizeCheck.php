<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

use Basilicom\PimcorePluginHealthCheck\Util\Bytes;
use Doctrine\DBAL\Connection;

final class DatabaseTableSizeCheck extends AbstractCheck
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
        return 'device:database_table_size';
    }

    public function getLabel(): string
    {
        return 'Database Table Size';
    }

    protected function doRun(): CheckResult
    {
        $warningThreshold = (int) $this->option('warning_threshold', 0);
        $criticalThreshold = (int) $this->option('critical_threshold', 0);
        $candidates = array_filter(
            [$warningThreshold, $criticalThreshold],
            static fn (int $value): bool => $value > 0,
        );
        $threshold = $candidates === [] ? 0 : min($candidates);

        /** @var list<array{table_name: string, size: string|int}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT TABLE_NAME AS table_name, (data_length + index_length) AS size
             FROM information_schema.TABLES
             WHERE table_schema = DATABASE() AND (data_length + index_length) >= :threshold
             ORDER BY size DESC',
            ['threshold' => $threshold],
        );

        $warning = [];
        $critical = [];
        $sizes = [];

        foreach ($rows as $row) {
            $name = (string) $row['table_name'];
            $size = (int) $row['size'];
            $sizes[$name] = Bytes::format($size);

            if ($criticalThreshold > 0 && $size >= $criticalThreshold) {
                $critical[] = $name;
            } elseif ($warningThreshold > 0 && $size >= $warningThreshold) {
                $warning[] = $name;
            }
        }

        $data = [
            'warning_tables' => $warning,
            'critical_tables' => $critical,
            'sizes' => $sizes,
        ];

        if ($critical !== []) {
            return $this->critical(
                'Following database table sizes are critical: ' . implode(',', $critical),
                $data,
            );
        }

        if ($warning !== []) {
            return $this->warning(
                'Following database table sizes are high: ' . implode(',', $warning),
                $data,
            );
        }

        return $this->ok('All database table sizes are within the configured limits', $data);
    }
}
