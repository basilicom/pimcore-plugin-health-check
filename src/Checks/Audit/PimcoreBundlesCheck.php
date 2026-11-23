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

use Basilicom\PimcorePluginHealthCheck\Checks\AbstractReportingCheck;
use Basilicom\PimcorePluginHealthCheck\Checks\Report;
use Pimcore\Extension\Bundle\PimcoreBundleManager;

final readonly class PimcoreBundlesCheck extends AbstractReportingCheck
{
    public function __construct(bool $enabled, private ?PimcoreBundleManager $bundleManager = null)
    {
        parent::__construct($enabled);
    }

    public function identifier(): string
    {
        return 'pimcore:bundles';
    }

    public function label(): string
    {
        return 'Pimcore Bundles';
    }

    protected function examine(): Report
    {
        if ($this->bundleManager === null) {
            return Report::notAvailable('The Pimcore bundle manager is not available (n/a)');
        }

        $names = array_values(array_map(static fn (object $bundle): string => $bundle::class, $this->bundleManager->getActiveBundles()));

        return Report::ok(sprintf('There are %d Pimcore bundles in the system', count($names)), ['count' => count($names), 'bundles' => $names]);
    }
}
