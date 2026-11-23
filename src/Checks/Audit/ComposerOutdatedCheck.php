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

namespace Basilicom\PimcorePluginHealthCheck\Checks\Audit;

use Basilicom\PimcorePluginHealthCheck\Checks\Report;

final readonly class ComposerOutdatedCheck extends AbstractComposerCheck
{
    public function __construct(
        bool $enabled,
        string $binary,
        string $workingDirectory,
        int $timeoutSeconds,
        private int|float|null $warningThreshold,
        private int|float|null $failureThreshold,
    ) {
        parent::__construct($enabled, $binary, $workingDirectory, $timeoutSeconds);
    }

    public function identifier(): string
    {
        return 'security:composer_outdated';
    }

    public function label(): string
    {
        return 'Composer Outdated Packages';
    }

    protected function examine(): Report
    {
        [$output, $reason] = $this->composer(['outdated', '--direct', '--format=json']);

        if ($output === null || !is_array($output['installed'] ?? null)) {
            return Report::notAvailable('n/a: ' . ($reason !== '' ? $reason : 'composer outdated returned an unexpected payload'));
        }

        $packages = [];

        foreach ($output['installed'] as $package) {
            if (is_array($package) && isset($package['name'])) {
                $packages[] = (string)$package['name'];
            }
        }

        $count = count($packages);

        return Report::graded(
            $count,
            $this->warningThreshold,
            $this->failureThreshold,
            sprintf('%d direct composer %s outdated.', $count, $count === 1 ? 'dependency is' : 'dependencies are'),
            ['outdated_count' => $count, 'packages' => $packages]
        );
    }
}
