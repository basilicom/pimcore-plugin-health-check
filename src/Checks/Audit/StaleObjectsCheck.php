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

final readonly class StaleObjectsCheck extends AbstractReportingCheck
{
    public function __construct(
        bool $enabled,
        private Connection $connection,
        private int $days,
        private int|float|null $warningThreshold,
        private int|float|null $failureThreshold,
    ) {
        parent::__construct($enabled);
    }

    public function identifier(): string
    {
        return 'data:stale_objects';
    }

    public function label(): string
    {
        return 'Stale Objects';
    }

    protected function examine(): Report
    {
        $total = (int)$this->connection->fetchOne("SELECT COUNT(*) FROM objects WHERE `type` IN ('object', 'variant')");
        $stale = (int)$this->connection->fetchOne(
            "SELECT COUNT(*) FROM objects WHERE `type` IN ('object', 'variant') AND modificationDate < ?",
            [time() - max(1, $this->days) * 86400]
        );

        return Report::graded(
            $stale,
            $this->warningThreshold,
            $this->failureThreshold,
            sprintf('%d of %d objects have not been modified in the last %d days.', $stale, $total, $this->days),
            ['stale' => $stale, 'total' => $total, 'percent' => $total > 0 ? round($stale / $total * 100, 1) : 0.0, 'days' => $this->days]
        );
    }
}
