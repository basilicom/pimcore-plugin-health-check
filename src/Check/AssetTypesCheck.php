<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

use Doctrine\DBAL\Connection;

/**
 * Asset distribution by MIME type, flagging unexpected uploads such as executables (PF-88 metric 40).
 */
final class AssetTypesCheck extends AbstractCheck
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
        return 'data:asset_types';
    }

    public function getLabel(): string
    {
        return 'Asset Types';
    }

    protected function doRun(): CheckResult
    {
        /** @var list<array{mimetype: string|null, cnt: string|int}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            "SELECT mimetype, COUNT(*) AS cnt FROM assets WHERE `type` <> 'folder' GROUP BY mimetype ORDER BY cnt DESC",
        );

        $byMimeType = [];
        $total = 0;

        foreach ($rows as $row) {
            $byMimeType[(string) ($row['mimetype'] ?? 'unknown')] = (int) $row['cnt'];
            $total += (int) $row['cnt'];
        }

        $limit = max(1, min(50, (int) $this->option('limit', 10)));
        $mostCommon = array_map(
            static fn (string $mime, int $count): string => sprintf('%s (%d)', $mime, $count),
            array_keys(array_slice($byMimeType, 0, $limit, true)),
            array_slice($byMimeType, 0, $limit, true),
        );

        $disallowed = $this->disallowedExtensions();
        $suspicious = [];

        if ($disallowed !== []) {
            $placeholders = implode(', ', array_fill(0, count($disallowed), '?'));
            /** @var list<string> $suspicious */
            $suspicious = $this->connection->fetchFirstColumn(
                "SELECT CONCAT(`path`, filename) FROM assets
                 WHERE `type` <> 'folder' AND LOWER(SUBSTRING_INDEX(filename, '.', -1)) IN ($placeholders)
                 ORDER BY `path`, filename
                 LIMIT 20",
                $disallowed,
            );
        }

        $data = [
            'total' => $total,
            'by_mimetype' => $byMimeType,
            'disallowed_extensions' => $disallowed,
            'suspicious_assets' => $suspicious,
        ];

        if ($suspicious !== []) {
            return $this->warning(
                sprintf(
                    'Found %d assets with a disallowed file extension: %s',
                    count($suspicious),
                    implode(', ', $suspicious),
                ),
                $data,
            );
        }

        return $this->ok(
            $total === 0
                ? 'There are no assets in the system'
                : sprintf('%d assets across %d MIME types, most common: %s', $total, count($byMimeType), implode(', ', $mostCommon)),
            $data,
        );
    }

    /**
     * @return list<string>
     */
    private function disallowedExtensions(): array
    {
        $values = $this->option('disallowed_extensions', []);

        if (!is_array($values)) {
            $values = [$values];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $value): string => strtolower(ltrim(trim((string) $value), '.')), $values),
            static fn (string $value): bool => $value !== '',
        ));
    }
}
