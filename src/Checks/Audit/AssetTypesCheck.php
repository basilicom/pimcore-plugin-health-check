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

final readonly class AssetTypesCheck extends AbstractReportingCheck
{
    /** @param list<string> $disallowedExtensions */
    public function __construct(bool $enabled, private Connection $connection, private int $limit, private array $disallowedExtensions)
    {
        parent::__construct($enabled);
    }

    public function identifier(): string
    {
        return 'data:asset_types';
    }

    public function label(): string
    {
        return 'Asset Types';
    }

    protected function examine(): Report
    {
        /** @var list<array{mimetype: string|null, cnt: string|int}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            "SELECT mimetype, COUNT(*) AS cnt FROM assets WHERE `type` <> 'folder' GROUP BY mimetype ORDER BY cnt DESC"
        );

        $byMime = [];
        $total  = 0;

        foreach ($rows as $row) {
            $byMime[(string)($row['mimetype'] ?? 'unknown')] = (int)$row['cnt'];
            $total += (int)$row['cnt'];
        }

        $disallowed = array_values(array_filter(array_map(static fn (string $e): string => strtolower(ltrim(trim($e), '.')), $this->disallowedExtensions)));
        $suspicious = [];

        if ($disallowed !== []) {
            $in = implode(', ', array_fill(0, count($disallowed), '?'));
            /** @var list<string> $suspicious */
            $suspicious = $this->connection->fetchFirstColumn(
                "SELECT CONCAT(`path`, filename) FROM assets
                 WHERE `type` <> 'folder' AND LOWER(SUBSTRING_INDEX(filename, '.', -1)) IN ($in)
                 ORDER BY `path`, filename LIMIT 20",
                $disallowed
            );
        }

        $data = ['total' => $total, 'by_mimetype' => $byMime, 'disallowed_extensions' => $disallowed, 'suspicious_assets' => $suspicious];

        if ($suspicious !== []) {
            return Report::warning(sprintf('Found %d assets with a disallowed file extension: %s', count($suspicious), implode(', ', $suspicious)), $data);
        }

        $top    = array_slice($byMime, 0, max(1, min(50, $this->limit)), true);
        $listed = implode(', ', array_map(static fn (string $m, int $c): string => sprintf('%s (%d)', $m, $c), array_keys($top), $top));

        return Report::ok(
            $total === 0 ? 'There are no assets in the system' : sprintf('%d assets across %d MIME types, most common: %s', $total, count($byMime), $listed),
            $data
        );
    }
}
