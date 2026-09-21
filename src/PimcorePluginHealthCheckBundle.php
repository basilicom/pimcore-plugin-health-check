<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck;

use Basilicom\PimcorePluginHealthCheck\Check\CheckInterface;
use Pimcore\Extension\Bundle\AbstractPimcoreBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class PimcorePluginHealthCheckBundle extends AbstractPimcoreBundle
{
    public const CHECK_TAG = 'pimcore_plugin_health_check.check';

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // any service implementing CheckInterface is picked up by the HealthCheckService
        $container->registerForAutoconfiguration(CheckInterface::class)->addTag(self::CHECK_TAG);
    }
}
