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
use DateTimeImmutable;
use DateTimeInterface;
use Pimcore\Maintenance\ExecutorInterface;

final readonly class MaintenanceLastRunCheck extends AbstractReportingCheck
{
    public function __construct(
        bool $enabled,
        private ?ExecutorInterface $executor,
        private int|float|null $warningMinutes,
        private int|float|null $failureMinutes,
    ) {
        parent::__construct($enabled);
    }

    public function identifier(): string
    {
        return 'maintenance:last_run_age';
    }

    public function label(): string
    {
        return 'Pimcore Maintenance';
    }

    protected function examine(): Report
    {
        if ($this->executor === null) {
            return Report::notAvailable('The Pimcore maintenance executor is not available (n/a)');
        }

        $lastRun = $this->executor->getLastExecution();

        if ($lastRun <= 0) {
            return Report::failure('Pimcore maintenance has never been executed.', ['last_run' => null]);
        }

        $minutes = (int)floor((time() - $lastRun) / 60);

        return Report::graded(
            $minutes,
            $this->warningMinutes,
            $this->failureMinutes,
            sprintf('Pimcore maintenance last run was %d minutes ago.', $minutes),
            ['age_minutes' => $minutes, 'last_run' => (new DateTimeImmutable('@' . $lastRun))->format(DateTimeInterface::ATOM)]
        );
    }
}
