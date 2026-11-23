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
use Basilicom\PimcorePluginHealthCheck\Util\Bytes;
use Doctrine\DBAL\Connection;

final readonly class DatabaseSizeCheck extends AbstractReportingCheck
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
        return 'device:database_size';
    }

    public function label(): string
    {
        return 'Database Size';
    }

    protected function examine(): Report
    {
        $size = (int)$this->connection->fetchOne(
            'SELECT COALESCE(SUM(data_length + index_length), 0) FROM information_schema.TABLES WHERE table_schema = DATABASE()'
        );

        return Report::graded(
            $size,
            $this->warningThreshold,
            $this->failureThreshold,
            sprintf('Database size is %s', Bytes::format($size)),
            ['size_bytes' => $size, 'size_human' => Bytes::format($size)]
        );
    }
}
