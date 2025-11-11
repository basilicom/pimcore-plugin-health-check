<?php

namespace Basilicom\PimcorePluginHealthCheck\Services;

use Basilicom\PimcorePluginHealthCheck\Checks\CacheCheck;
use Basilicom\PimcorePluginHealthCheck\Checks\DatabaseAccessibleCheck;
use Basilicom\PimcorePluginHealthCheck\Checks\FilesystemCheck;
use Basilicom\PimcorePluginHealthCheck\Checks\PimcoreConfigurationCheck;
use Basilicom\PimcorePluginHealthCheck\Checks\RobotsTxtCheck;
use Basilicom\PimcorePluginHealthCheck\Exception\AbstractHealthCheckException;

readonly class HealthCheckService
{
    public function __construct(
        private PimcoreConfigurationCheck $pimcoreConfigurationCheck,
        private DatabaseAccessibleCheck $databaseAccessibleCheck,
        private FilesystemCheck $filesystemCheck,
        private CacheCheck $cacheCheck,
        private RobotsTxtCheck $robotsTxtCheck,
    ) {
    }

    /**
     * @throws AbstractHealthCheckException
     */
    public function check(): void
    {
        if ($this->pimcoreConfigurationCheck->isActive()) {
            // needs to be first, because the other checks rely on configuration
            $this->pimcoreConfigurationCheck->check();
        }
        if ($this->databaseAccessibleCheck->isActive()) {
            $this->databaseAccessibleCheck->check();
        }
        if ($this->filesystemCheck->isActive()) {
            $this->filesystemCheck->check();
        }
        if ($this->cacheCheck->isActive()) {
            $this->cacheCheck->check();
        }
        if ($this->robotsTxtCheck->isActive()) {
            $this->robotsTxtCheck->check();
        }
    }
}