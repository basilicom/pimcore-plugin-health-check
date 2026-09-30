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

final readonly class PimcoreElementCountCheck extends AbstractReportingCheck
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
        return 'pimcore:element_count';
    }

    public function label(): string
    {
        return 'Pimcore Element Count';
    }

    protected function examine(): Report
    {
        // COUNT(*) only, so the Pimcore 10 `o_` column prefix and the 11+ schema both work
        $objects   = (int)$this->connection->fetchOne('SELECT COUNT(*) FROM objects');
        $assets    = (int)$this->connection->fetchOne('SELECT COUNT(*) FROM assets');
        $documents = (int)$this->connection->fetchOne('SELECT COUNT(*) FROM documents');
        $total     = $objects + $assets + $documents;

        return Report::graded(
            $total,
            $this->warningThreshold,
            $this->failureThreshold,
            sprintf('There are %d elements in the system (%d objects, %d assets, %d documents)', $total, $objects, $assets, $documents),
            ['count' => $total, 'objects' => $objects, 'assets' => $assets, 'documents' => $documents]
        );
    }
}
