<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

use Doctrine\DBAL\Connection;

final class PimcoreElementCountCheck extends AbstractCheck
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
        return 'pimcore:element_count';
    }

    public function getLabel(): string
    {
        return 'Pimcore Element Count';
    }

    protected function doRun(): CheckResult
    {
        // plain COUNT(*) keeps this compatible with the Pimcore 10 (`o_` prefixed) and
        // the Pimcore 11/12 schema alike
        $objects = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM objects');
        $assets = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM assets');
        $documents = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM documents');
        $total = $objects + $assets + $documents;

        return $this->thresholdResult(
            $total,
            $this->threshold('warning_threshold'),
            $this->threshold('critical_threshold'),
            sprintf(
                'There are %d elements in the system (%d objects, %d assets, %d documents)',
                $total,
                $objects,
                $assets,
                $documents,
            ),
            [
                'count' => $total,
                'objects' => $objects,
                'assets' => $assets,
                'documents' => $documents,
            ],
        );
    }
}
