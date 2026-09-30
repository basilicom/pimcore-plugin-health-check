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
use Basilicom\PimcorePluginHealthCheck\Util\Bytes;
use Doctrine\DBAL\Connection;

final readonly class LargestTablesCheck extends AbstractReportingCheck
{
    public function __construct(bool $enabled, private Connection $connection, private int $limit)
    {
        parent::__construct($enabled);
    }

    public function identifier(): string
    {
        return 'database:largest_tables';
    }

    public function label(): string
    {
        return 'Largest Tables';
    }

    protected function examine(): Report
    {
        /** @var list<array{table_name: string, size: string|int|null}> $rows */
        $rows = $this->connection->fetchAllAssociative(sprintf(
            'SELECT TABLE_NAME AS table_name, (data_length + index_length) AS size
             FROM information_schema.TABLES
             WHERE table_schema = DATABASE() AND data_length IS NOT NULL
             ORDER BY size DESC LIMIT %d',
            max(1, min(100, $this->limit))
        ));

        $tables = [];

        foreach ($rows as $row) {
            $tables[(string)$row['table_name']] = (int)$row['size'];
        }

        if ($tables === []) {
            return Report::ok('No tables found in the current database schema.', ['tables' => []]);
        }

        $listed = implode(', ', array_map(static fn (string $n, int $s): string => sprintf('%s (%s)', $n, Bytes::format($s)), array_keys($tables), $tables));

        return Report::ok('Largest tables: ' . $listed, ['tables' => $tables]);
    }
}
