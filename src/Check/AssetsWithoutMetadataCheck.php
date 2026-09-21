<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

use Doctrine\DBAL\Connection;

/**
 * Assets lacking metadata such as alt text or copyright (PF-88 metric 39).
 *
 * Without `metadata_names` the check counts assets that carry no metadata at all; with names
 * configured it counts assets that miss at least one of them.
 */
final class AssetsWithoutMetadataCheck extends AbstractCheck
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
        return 'data:assets_without_metadata';
    }

    public function getLabel(): string
    {
        return 'Assets Without Metadata';
    }

    protected function doRun(): CheckResult
    {
        $types = $this->stringList('asset_types') ?: ['image'];
        $names = $this->stringList('metadata_names');

        $typePlaceholders = implode(', ', array_fill(0, count($types), '?'));

        $total = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM assets WHERE `type` IN ($typePlaceholders)",
            $types,
        );

        if ($names === []) {
            $missing = (int) $this->connection->fetchOne(
                "SELECT COUNT(*) FROM assets a
                 WHERE a.type IN ($typePlaceholders)
                   AND NOT EXISTS (
                       SELECT 1 FROM assets_metadata m
                       WHERE m.cid = a.id AND m.data IS NOT NULL AND m.data <> ''
                   )",
                $types,
            );
            $message = sprintf('%d of %d %s assets have no metadata at all.', $missing, $total, implode('/', $types));
        } else {
            $namePlaceholders = implode(', ', array_fill(0, count($names), '?'));
            $missing = (int) $this->connection->fetchOne(
                "SELECT COUNT(*) FROM assets a
                 WHERE a.type IN ($typePlaceholders)
                   AND (
                       SELECT COUNT(DISTINCT m.name) FROM assets_metadata m
                       WHERE m.cid = a.id AND m.name IN ($namePlaceholders) AND m.data IS NOT NULL AND m.data <> ''
                   ) < ?",
                [...$types, ...$names, count($names)],
            );
            $message = sprintf(
                '%d of %d %s assets are missing required metadata (%s).',
                $missing,
                $total,
                implode('/', $types),
                implode(', ', $names),
            );
        }

        return $this->thresholdResult(
            $missing,
            $this->threshold('warning_threshold'),
            $this->threshold('critical_threshold'),
            $message,
            [
                'missing' => $missing,
                'total' => $total,
                'asset_types' => $types,
                'metadata_names' => $names,
            ],
        );
    }

    /**
     * @return list<string>
     */
    private function stringList(string $key): array
    {
        $values = $this->option($key, []);

        if (!is_array($values)) {
            $values = [$values];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $value): string => trim((string) $value), $values),
            static fn (string $value): bool => $value !== '',
        ));
    }
}
