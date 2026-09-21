<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

final class ComposerAuditCheck extends AbstractComposerCheck
{
    public function getIdentifier(): string
    {
        return 'security:composer_audit';
    }

    public function getLabel(): string
    {
        return 'Composer Audit';
    }

    protected function doRun(): CheckResult
    {
        ['output' => $output, 'reason' => $reason] = $this->runComposer(['audit', '--format=json']);

        if ($output === null || !array_key_exists('advisories', $output)) {
            return $this->na('n/a: ' . ($reason ?? 'composer audit returned an unexpected payload'));
        }

        $advisories = is_array($output['advisories']) ? $output['advisories'] : [];
        $packages = array_keys($advisories);
        $count = 0;

        foreach ($advisories as $packageAdvisories) {
            $count += is_array($packageAdvisories) ? count($packageAdvisories) : 1;
        }

        $data = ['advisory_count' => $count, 'packages' => $packages];

        if ($count > 0) {
            return $this->critical(
                sprintf(
                    'Found %d security %s in composer dependencies: %s',
                    $count,
                    $count === 1 ? 'advisory' : 'advisories',
                    implode(', ', $packages),
                ),
                $data,
            );
        }

        return $this->ok('No known security advisories in composer dependencies.', $data);
    }
}
