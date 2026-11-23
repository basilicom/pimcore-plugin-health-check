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
 * Thresholds are percentages of all objects.
 */
final readonly class UnpublishedObjectsCheck extends AbstractReportingCheck
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
        return 'data:unpublished_objects';
    }

    public function label(): string
    {
        return 'Unpublished Objects';
    }

    protected function examine(): Report
    {
        $total       = (int)$this->connection->fetchOne("SELECT COUNT(*) FROM objects WHERE `type` IN ('object', 'variant')");
        $unpublished = (int)$this->connection->fetchOne("SELECT COUNT(*) FROM objects WHERE `type` IN ('object', 'variant') AND published = 0");
        $percent     = $total > 0 ? round($unpublished / $total * 100, 1) : 0.0;

        return Report::graded(
            $percent,
            $this->warningThreshold,
            $this->failureThreshold,
            sprintf('%d of %d objects (%s%%) are unpublished.', $unpublished, $total, $percent),
            ['unpublished' => $unpublished, 'total' => $total, 'percent' => $percent]
        );
    }
}
