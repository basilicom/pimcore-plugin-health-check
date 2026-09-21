<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

final class ComposerOutdatedCheck extends AbstractComposerCheck
{
    public function getIdentifier(): string
    {
        return 'security:composer_outdated';
    }

    public function getLabel(): string
    {
        return 'Composer Outdated Packages';
    }

    protected function doRun(): CheckResult
    {
        ['output' => $output, 'reason' => $reason] = $this->runComposer(['outdated', '--direct', '--format=json']);

        if ($output === null || !isset($output['installed']) || !is_array($output['installed'])) {
            return $this->na('n/a: ' . ($reason ?? 'composer outdated returned an unexpected payload'));
        }

        $packages = [];
        foreach ($output['installed'] as $package) {
            if (is_array($package) && isset($package['name'])) {
                $packages[] = (string) $package['name'];
            }
        }

        $count = count($packages);

        return $this->thresholdResult(
            $count,
            $this->threshold('warning_threshold'),
            $this->threshold('critical_threshold'),
            sprintf('%d direct composer %s outdated.', $count, $count === 1 ? 'dependency is' : 'dependencies are'),
            ['outdated_count' => $count, 'packages' => $packages],
        );
    }
}
