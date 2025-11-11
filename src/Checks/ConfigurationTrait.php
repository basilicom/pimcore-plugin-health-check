<?php

namespace Basilicom\PimcorePluginHealthCheck\Checks;

use Pimcore;

trait ConfigurationTrait
{
    private function getConfiguration(): array
    {
        return Pimcore::getKernel()->getContainer()->getParameter('pimcore_plugin_health_check');
    }

    private function isEnabled(string $configurationKey): bool
    {
        $settings = $this->getConfiguration();
        return $settings[$configurationKey];
    }
}