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

final readonly class ComposerAuditCheck extends AbstractComposerCheck
{
    public function identifier(): string
    {
        return 'security:composer_audit';
    }

    public function label(): string
    {
        return 'Composer Audit';
    }

    protected function examine(): Report
    {
        [$output, $reason] = $this->composer(['audit', '--format=json']);

        if ($output === null || !array_key_exists('advisories', $output)) {
            return Report::notAvailable('n/a: ' . ($reason !== '' ? $reason : 'composer audit returned an unexpected payload'));
        }

        $advisories = is_array($output['advisories']) ? $output['advisories'] : [];
        $count      = 0;

        foreach ($advisories as $perPackage) {
            $count += is_array($perPackage) ? count($perPackage) : 1;
        }

        $packages = array_map('strval', array_keys($advisories));
        $data     = ['advisory_count' => $count, 'packages' => $packages];

        if ($count > 0) {
            return Report::failure(
                sprintf('Found %d security %s in composer dependencies: %s', $count, $count === 1 ? 'advisory' : 'advisories', implode(', ', $packages)),
                $data
            );
        }

        return Report::ok('No known security advisories in composer dependencies.', $data);
    }
}
