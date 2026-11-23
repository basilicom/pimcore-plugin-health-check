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

final readonly class ObjectsPerClassCheck extends AbstractReportingCheck
{
    public function __construct(bool $enabled, private Connection $connection, private int $limit, private bool $warnOnEmptyClasses)
    {
        parent::__construct($enabled);
    }

    public function identifier(): string
    {
        return 'data:objects_per_class';
    }

    public function label(): string
    {
        return 'DataObjects per Class';
    }

    protected function examine(): Report
    {
        /** @var list<array{class_name: string, cnt: string|int}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            "SELECT c.name AS class_name, COUNT(o.id) AS cnt
             FROM classes c LEFT JOIN objects o ON o.classId = c.id AND o.type IN ('object', 'variant')
             GROUP BY c.id, c.name ORDER BY cnt DESC, c.name"
        );

        if ($rows === []) {
            return Report::ok('No DataObject classes defined.', ['total_objects' => 0, 'per_class' => []]);
        }

        $perClass = $empty = [];
        $total    = 0;

        foreach ($rows as $row) {
            $count                                = (int)$row['cnt'];
            $perClass[(string)$row['class_name']] = $count;
            $total += $count;

            if ($count === 0) {
                $empty[] = (string)$row['class_name'];
            }
        }

        $top  = array_slice($perClass, 0, max(1, min(50, $this->limit)), true);
        $data = ['total_objects' => $total, 'class_count' => count($perClass), 'per_class' => $perClass, 'empty_classes' => $empty];

        if ($this->warnOnEmptyClasses && $empty !== []) {
            return Report::warning(sprintf('%d classes have no objects: %s', count($empty), implode(', ', $empty)), $data);
        }

        $listed = implode(', ', array_map(static fn (string $n, int $c): string => sprintf('%s (%d)', $n, $c), array_keys($top), $top));

        return Report::ok(sprintf('%d objects across %d classes, largest: %s', $total, count($perClass), $listed), $data);
    }
}
