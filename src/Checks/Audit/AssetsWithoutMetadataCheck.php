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

/**
 * Without `metadataNames` an asset counts when it carries no metadata at all; with names it counts
 * when at least one of them is missing.
 */
final readonly class AssetsWithoutMetadataCheck extends AbstractReportingCheck
{
    /**
     * @param list<string> $assetTypes
     * @param list<string> $metadataNames
     */
    public function __construct(
        bool $enabled,
        private Connection $connection,
        private array $assetTypes,
        private array $metadataNames,
        private int|float|null $warningThreshold,
        private int|float|null $failureThreshold,
    ) {
        parent::__construct($enabled);
    }

    public function identifier(): string
    {
        return 'data:assets_without_metadata';
    }

    public function label(): string
    {
        return 'Assets Without Metadata';
    }

    protected function examine(): Report
    {
        $types = array_values(array_filter(array_map('strval', $this->assetTypes))) ?: ['image'];
        $names = array_values(array_filter(array_map('strval', $this->metadataNames)));
        $in    = implode(', ', array_fill(0, count($types), '?'));

        $total = (int)$this->connection->fetchOne("SELECT COUNT(*) FROM assets WHERE `type` IN ($in)", $types);

        if ($names === []) {
            $missing = (int)$this->connection->fetchOne(
                "SELECT COUNT(*) FROM assets a WHERE a.type IN ($in)
                 AND NOT EXISTS (SELECT 1 FROM assets_metadata m WHERE m.cid = a.id AND m.data IS NOT NULL AND m.data <> '')",
                $types
            );
            $message = sprintf('%d of %d %s assets have no metadata at all.', $missing, $total, implode('/', $types));
        } else {
            $namesIn = implode(', ', array_fill(0, count($names), '?'));
            $missing = (int)$this->connection->fetchOne(
                "SELECT COUNT(*) FROM assets a WHERE a.type IN ($in)
                 AND (SELECT COUNT(DISTINCT m.name) FROM assets_metadata m
                      WHERE m.cid = a.id AND m.name IN ($namesIn) AND m.data IS NOT NULL AND m.data <> '') < ?",
                [...$types, ...$names, count($names)]
            );
            $message = sprintf('%d of %d %s assets are missing required metadata (%s).', $missing, $total, implode('/', $types), implode(', ', $names));
        }

        return Report::graded(
            $missing,
            $this->warningThreshold,
            $this->failureThreshold,
            $message,
            ['missing' => $missing, 'total' => $total, 'asset_types' => $types, 'metadata_names' => $names]
        );
    }
}
