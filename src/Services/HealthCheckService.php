<?php

namespace Basilicom\PimcorePluginHealthCheck\Services;

use Basilicom\PimcorePluginHealthCheck\Checks\DatabaseAccessibleCheck;
use Basilicom\PimcorePluginHealthCheck\Checks\FilesystemCheck;
use Basilicom\PimcorePluginHealthCheck\Checks\PimcoreConfigurationCheck;
use Basilicom\PimcorePluginHealthCheck\Exception\AbstractHealthCheckException;

readonly class HealthCheckService
{
    public function __construct(
        private PimcoreConfigurationCheck $pimcoreConfigurationCheck,
        private DatabaseAccessibleCheck $databaseAccessibleCheck,
        private FilesystemCheck $filesystemCheck,
    ) {
    }

    /**
     * @throws AbstractHealthCheckException
     */
    public function check(): void
    {
        if ($this->filesystemCheck->isActive()) {
            $this->filesystemCheck->check();
        }
        if ($this->pimcoreConfigurationCheck->isActive()) {
            $this->pimcoreConfigurationCheck->check();
        }
        if ($this->databaseAccessibleCheck->isActive()) {
            $this->databaseAccessibleCheck->check();
        }
    }
}