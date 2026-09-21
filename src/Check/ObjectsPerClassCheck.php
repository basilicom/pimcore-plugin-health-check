<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

use Doctrine\DBAL\Connection;

/**
 * Informational check: how many DataObjects each class holds. Identifies hot classes and
 * abandoned ones (PF-88 metric 10).
 */
final class ObjectsPerClassCheck extends AbstractCheck
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
        return 'data:objects_per_class';
    }

    public function getLabel(): string
    {
        return 'DataObjects per Class';
    }

    protected function doRun(): CheckResult
    {
        /** @var list<array{class_name: string, cnt: string|int}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            "SELECT c.name AS class_name, COUNT(o.id) AS cnt
             FROM classes c
             LEFT JOIN objects o ON o.classId = c.id AND o.type IN ('object', 'variant')
             GROUP BY c.id, c.name
             ORDER BY cnt DESC, c.name",
        );

        if ($rows === []) {
            return $this->ok('No DataObject classes defined.', ['total_objects' => 0, 'per_class' => []]);
        }

        $perClass = [];
        $empty = [];
        $total = 0;

        foreach ($rows as $row) {
            $count = (int) $row['cnt'];
            $perClass[(string) $row['class_name']] = $count;
            $total += $count;

            if ($count === 0) {
                $empty[] = (string) $row['class_name'];
            }
        }

        $limit = max(1, min(50, (int) $this->option('limit', 10)));
        $largest = array_map(
            static fn (string $name, int $count): string => sprintf('%s (%d)', $name, $count),
            array_keys(array_slice($perClass, 0, $limit, true)),
            array_slice($perClass, 0, $limit, true),
        );

        $data = [
            'total_objects' => $total,
            'class_count' => count($perClass),
            'per_class' => $perClass,
            'empty_classes' => $empty,
        ];

        if ((bool) $this->option('warn_on_empty_classes', false) && $empty !== []) {
            return $this->warning(
                sprintf('%d classes have no objects: %s', count($empty), implode(', ', $empty)),
                $data,
            );
        }

        return $this->ok(
            sprintf('%d objects across %d classes, largest: %s', $total, count($perClass), implode(', ', $largest)),
            $data,
        );
    }
}
