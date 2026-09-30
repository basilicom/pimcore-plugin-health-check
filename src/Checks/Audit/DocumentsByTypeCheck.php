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

final readonly class DocumentsByTypeCheck extends AbstractReportingCheck
{
    public function __construct(bool $enabled, private Connection $connection)
    {
        parent::__construct($enabled);
    }

    public function identifier(): string
    {
        return 'data:documents_by_type';
    }

    public function label(): string
    {
        return 'Documents by Type';
    }

    protected function examine(): Report
    {
        /** @var list<array{type: string, cnt: string|int}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT `type`, COUNT(*) AS cnt FROM documents WHERE `type` IS NOT NULL GROUP BY `type` ORDER BY cnt DESC'
        );

        $byType = [];
        $total  = 0;

        foreach ($rows as $row) {
            $byType[(string)$row['type']] = (int)$row['cnt'];
            $total += (int)$row['cnt'];
        }

        $listed = implode(', ', array_map(static fn (string $t, int $c): string => sprintf('%s (%d)', $t, $c), array_keys($byType), $byType));

        return Report::ok($total === 0 ? 'There are no documents in the system' : sprintf('%d documents: %s', $total, $listed), ['total' => $total, 'by_type' => $byType]);
    }
}
