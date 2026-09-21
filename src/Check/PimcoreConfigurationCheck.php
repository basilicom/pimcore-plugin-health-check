<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

use Pimcore\Config;

final class PimcoreConfigurationCheck extends AbstractCheck
{
    public function getIdentifier(): string
    {
        return 'system:pimcore_configuration';
    }

    public function getLabel(): string
    {
        return 'Pimcore Configuration';
    }

    protected function doRun(): CheckResult
    {
        $environment = Config::getEnvironment();
        $systemConfiguration = Config::getSystemConfiguration();

        if (empty($environment) || empty($systemConfiguration)) {
            return $this->critical('Pimcore environment/system configuration is empty.');
        }

        return $this->ok(
            sprintf('Pimcore configuration is readable (environment: %s)', $environment),
            ['environment' => $environment],
        );
    }
}
