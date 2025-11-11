<?php

namespace Basilicom\PimcorePluginHealthCheck\Checks;

use Basilicom\PimcorePluginHealthCheck\Exception\ConfigurationNotReadableException;
use Basilicom\PimcorePluginHealthCheck\Services\HealthCheckInterface;
use Exception;
use Pimcore\Config;

class PimcoreConfigurationCheck implements HealthCheckInterface
{
    public function check(): void
    {
        try {
            $env = Config::getEnvironment();
            $systemConfiguration = Config::getSystemConfiguration();
        } catch (Exception $exception) {
            throw new ConfigurationNotReadableException('Pimcore environment/system configuration not readable: ' . $exception->getMessage());
        }

        if (empty($env) || empty($systemConfiguration)) {
            throw new ConfigurationNotReadableException('Pimcore environment/system configuration is empty.');
        }
    }

    public function isActive(): bool
    {
        return true;
    }
}