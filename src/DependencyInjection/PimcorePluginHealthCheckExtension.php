<?php

declare(strict_types=1);

/**
 * This source file is available under the terms of the MIT License.
 * Full copyright and license information is available in
 * LICENSE.txt which is distributed with this source code.
 *
 * @copyright Copyright (c) Basilicom GmbH (https://basilicom.de)
 * @license   MIT
 */

namespace Basilicom\PimcorePluginHealthCheck\DependencyInjection;

use Exception;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class PimcorePluginHealthCheckExtension extends Extension
{
    /** @throws Exception */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('pimcore_plugin_health_check.token', $config['token']);
        $container->setParameter('pimcore_plugin_health_check.timeout_ms', $config['timeout_ms']);

        // flattened so a new check needs a node and a service entry, but no change here
        foreach ($config['checks'] as $check => $settings) {
            foreach ($settings as $key => $value) {
                $container->setParameter(sprintf('pimcore_plugin_health_check.checks.%s.%s', $check, $key), $value);
            }
        }

        // Pimcore's constants win over the defaults, so projects that moved var/ or public/ keep working
        $projectDir = (string)$container->getParameter('kernel.project_dir');
        $container->setParameter(
            'pimcore_plugin_health_check.temp_directory',
            defined('PIMCORE_SYSTEM_TEMP_DIRECTORY') ? PIMCORE_SYSTEM_TEMP_DIRECTORY : $projectDir . '/var/tmp'
        );
        $container->setParameter(
            'pimcore_plugin_health_check.web_root_directory',
            defined('PIMCORE_WEB_ROOT') ? PIMCORE_WEB_ROOT : $projectDir . '/public'
        );

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');
    }
}
