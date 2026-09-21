<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

use Doctrine\DBAL\Connection;

/**
 * Informational check: document count per type (PF-88 metric 12).
 */
final class DocumentsByTypeCheck extends AbstractCheck
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
        return 'data:documents_by_type';
    }

    public function getLabel(): string
    {
        return 'Documents by Type';
    }

    protected function doRun(): CheckResult
    {
        /** @var list<array{type: string, cnt: string|int}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT `type`, COUNT(*) AS cnt FROM documents WHERE `type` IS NOT NULL GROUP BY `type` ORDER BY cnt DESC',
        );

        $byType = [];
        $total = 0;

        foreach ($rows as $row) {
            $byType[(string) $row['type']] = (int) $row['cnt'];
            $total += (int) $row['cnt'];
        }

        $formatted = array_map(
            static fn (string $type, int $count): string => sprintf('%s (%d)', $type, $count),
            array_keys($byType),
            $byType,
        );

        return $this->ok(
            $total === 0
                ? 'There are no documents in the system'
                : sprintf('%d documents: %s', $total, implode(', ', $formatted)),
            ['total' => $total, 'by_type' => $byType],
        );
    }
}
