<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

use DateTimeImmutable;
use Pimcore\Maintenance\ExecutorInterface;

/**
 * Replaces both the `pimcore:maintenance` check of instride/pimcore-monitor and the
 * `maintenance:last_run_age` metric from PF-88 - they measure the very same thing.
 */
final class MaintenanceLastRunCheck extends AbstractCheck
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config, private readonly ?ExecutorInterface $executor = null)
    {
        parent::__construct($config);
    }

    public function getIdentifier(): string
    {
        return 'maintenance:last_run_age';
    }

    public function getLabel(): string
    {
        return 'Pimcore Maintenance';
    }

    protected function doRun(): CheckResult
    {
        if ($this->executor === null) {
            return $this->na('The Pimcore maintenance executor is not available (n/a)');
        }

        $lastExecution = $this->executor->getLastExecution();

        if ($lastExecution <= 0) {
            return $this->critical('Pimcore maintenance has never been executed.', ['last_run' => null]);
        }

        $ageMinutes = (int) floor((time() - $lastExecution) / 60);

        return $this->thresholdResult(
            $ageMinutes,
            $this->threshold('warning_threshold'),
            $this->threshold('critical_threshold'),
            sprintf('Pimcore maintenance last run was %d minutes ago.', $ageMinutes),
            [
                'age_minutes' => $ageMinutes,
                'last_run' => (new DateTimeImmutable('@' . $lastExecution))->format(DATE_ATOM),
            ],
        );
    }
}
