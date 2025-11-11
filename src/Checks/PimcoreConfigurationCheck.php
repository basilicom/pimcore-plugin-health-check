<?php

namespace Basilicom\PimcorePluginHealthCheck\Checks;

use Basilicom\PimcorePluginHealthCheck\Exception\ConfigurationNotReadableException;
use Exception;
use Pimcore\Config;

class PimcoreConfigurationCheck implements CheckInterface
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
        // cannot be disabled, because it is required for disabling the other checks
        return true;
    }
}