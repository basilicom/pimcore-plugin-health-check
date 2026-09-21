<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\DependencyInjection;

use Exception;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class PimcorePluginHealthCheckExtension extends Extension
{
    /**
     * @inheritdoc
     *
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);
        $config = $this->applyLegacyFlags($config);

        // kept for backwards compatibility - the whole configuration as a single parameter
        $container->setParameter('pimcore_plugin_health_check', $config);

        $container->setParameter('pimcore_plugin_health_check.dashboard.enabled', $config['dashboard']['enabled']);
        $container->setParameter('pimcore_plugin_health_check.dashboard.path', $config['dashboard']['path']);
        $container->setParameter('pimcore_plugin_health_check.api.enabled', $config['api']['enabled']);
        $container->setParameter('pimcore_plugin_health_check.api.path', $config['api']['path']);
        $container->setParameter('pimcore_plugin_health_check.api.api_key', $config['api']['api_key']);
        $container->setParameter(
            'pimcore_plugin_health_check.external_lookups.enabled',
            $config['external_lookups']['enabled'],
        );
        $container->setParameter(
            'pimcore_plugin_health_check.external_lookups.timeout',
            $config['external_lookups']['timeout'],
        );
        $container->setParameter(
            'pimcore_plugin_health_check.external_lookups.cache_ttl',
            $config['external_lookups']['cache_ttl'],
        );

        foreach ($config['checks'] as $checkKey => $checkConfig) {
            $container->setParameter('pimcore_plugin_health_check.checks.' . $checkKey, $checkConfig);
        }

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');
    }

    /**
     * Maps the `*_check_enabled` flags of bundle version 1.x onto the `checks.<name>.enabled`
     * nodes. A flag that is explicitly set wins over the new node.
     *
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function applyLegacyFlags(array $config): array
    {
        foreach (Configuration::LEGACY_FLAGS as $flag => $checkKey) {
            if (($config[$flag] ?? null) !== null) {
                $config['checks'][$checkKey]['enabled'] = (bool) $config[$flag];
            }

            $config[$flag] = (bool) $config['checks'][$checkKey]['enabled'];
        }

        return $config;
    }
}
