<?php

namespace Basilicom\PimcorePluginHealthCheck\Services;

use Basilicom\PimcorePluginHealthCheck\Exception\AbstractHealthCheckException;

interface HealthCheckInterface
{
    /**
     * @throws AbstractHealthCheckException
     * @return void
     */
    public function check(): void;

    public function isActive(): bool;
}