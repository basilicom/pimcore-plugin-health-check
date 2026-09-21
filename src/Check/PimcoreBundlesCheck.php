<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

use Pimcore\Extension\Bundle\PimcoreBundleManager;

final class PimcoreBundlesCheck extends AbstractCheck
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config, private readonly ?PimcoreBundleManager $bundleManager = null)
    {
        parent::__construct($config);
    }

    public function getIdentifier(): string
    {
        return 'pimcore:bundles';
    }

    public function getLabel(): string
    {
        return 'Pimcore Bundles';
    }

    protected function doRun(): CheckResult
    {
        if ($this->bundleManager === null) {
            return $this->na('The Pimcore bundle manager is not available (n/a)');
        }

        $bundles = $this->bundleManager->getActiveBundles();
        $names = array_values(array_map(
            static fn (object $bundle): string => $bundle::class,
            $bundles,
        ));

        return $this->ok(
            sprintf('There are %d Pimcore bundles in the system', count($names)),
            ['count' => count($names), 'bundles' => $names],
        );
    }
}
