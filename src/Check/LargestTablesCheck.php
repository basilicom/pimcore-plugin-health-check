<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

use Basilicom\PimcorePluginHealthCheck\Util\Bytes;
use Doctrine\DBAL\Connection;

/**
 * Informational check: lists the largest tables of the Pimcore database.
 */
final class LargestTablesCheck extends AbstractCheck
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
        return 'database:largest_tables';
    }

    public function getLabel(): string
    {
        return 'Largest Tables';
    }

    protected function doRun(): CheckResult
    {
        $limit = max(1, min(100, (int) $this->option('limit', 10)));

        /** @var list<array{table_name: string, size: string|int|null}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            sprintf(
                'SELECT TABLE_NAME AS table_name, (data_length + index_length) AS size
                 FROM information_schema.TABLES
                 WHERE table_schema = DATABASE() AND data_length IS NOT NULL
                 ORDER BY size DESC
                 LIMIT %d',
                $limit,
            ),
        );

        $tables = [];
        $formatted = [];

        foreach ($rows as $row) {
            $name = (string) $row['table_name'];
            $size = (int) $row['size'];
            $tables[$name] = $size;
            $formatted[] = sprintf('%s (%s)', $name, Bytes::format($size));
        }

        if ($formatted === []) {
            return $this->ok('No tables found in the current database schema.', ['tables' => []]);
        }

        return $this->ok(
            sprintf('Largest tables: %s', implode(', ', $formatted)),
            ['tables' => $tables],
        );
    }
}
