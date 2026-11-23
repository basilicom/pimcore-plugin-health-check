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

final readonly class VersionsTableCheck extends AbstractReportingCheck
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
        return 'database:versions_table_bloat';
    }

    public function label(): string
    {
        return 'Versions Table';
    }

    protected function examine(): Report
    {
        $count = (int)$this->connection->fetchOne('SELECT COUNT(*) FROM versions');

        return Report::graded(
            $count,
            $this->warningThreshold,
            $this->failureThreshold,
            sprintf('The versions table holds %d rows.', $count),
            ['count' => $count]
        );
    }
}
