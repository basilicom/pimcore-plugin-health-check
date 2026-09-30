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

final readonly class DatabaseTableSizeCheck extends AbstractReportingCheck
{
    public function __construct(
        bool $enabled,
        private Connection $connection,
        private int|float|null $warningThreshold,
        private int|float|null $failureThreshold,
    ) {
        parent::__construct($enabled);
    }

    public function identifier(): string
    {
        return 'device:database_table_size';
    }

    public function label(): string
    {
        return 'Database Table Size';
    }

    protected function examine(): Report
    {
        $thresholds = array_filter([$this->warningThreshold, $this->failureThreshold], static fn ($t): bool => $t !== null && $t > 0);

        if ($thresholds === []) {
            return Report::ok('No table size thresholds configured', []);
        }

        /** @var list<array{table_name: string, size: string|int}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT TABLE_NAME AS table_name, (data_length + index_length) AS size
             FROM information_schema.TABLES
             WHERE table_schema = DATABASE() AND (data_length + index_length) >= ?
             ORDER BY size DESC',
            [min($thresholds)]
        );

        $warning = $failure = $sizes = [];

        foreach ($rows as $row) {
            $name         = (string)$row['table_name'];
            $size         = (int)$row['size'];
            $sizes[$name] = Bytes::format($size);

            if ($this->failureThreshold !== null && $size >= $this->failureThreshold) {
                $failure[] = $name;
            } elseif ($this->warningThreshold !== null && $size >= $this->warningThreshold) {
                $warning[] = $name;
            }
        }

        $data = ['warning_tables' => $warning, 'failure_tables' => $failure, 'sizes' => $sizes];

        if ($failure !== []) {
            return Report::failure('Following database table sizes are critical: ' . implode(',', $failure), $data);
        }

        if ($warning !== []) {
            return Report::warning('Following database table sizes are high: ' . implode(',', $warning), $data);
        }

        return Report::ok('All database table sizes are within the configured limits', $data);
    }
}
