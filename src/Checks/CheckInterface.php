<?php

namespace Basilicom\PimcorePluginHealthCheck\Checks;

use Basilicom\PimcorePluginHealthCheck\Exception\AbstractHealthCheckException;

interface CheckInterface
{
    /**
     * @throws AbstractHealthCheckException
     * @return void
     */
    public function check(): void;

    public function isActive(): bool;
}