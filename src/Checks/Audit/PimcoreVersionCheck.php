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
use Pimcore\Version;

final readonly class PimcoreVersionCheck extends AbstractReportingCheck
{
    public function identifier(): string
    {
        return 'pimcore:version';
    }

    public function label(): string
    {
        return 'Pimcore Version';
    }

    protected function examine(): Report
    {
        $version = ltrim((string)Version::getVersion(), 'v');

        if ($version === '') {
            return Report::notAvailable('Could not determine the Pimcore version (n/a)');
        }

        return Report::ok(sprintf('The system is running on Pimcore v%s', $version), ['version' => $version, 'major' => Version::getMajorVersion()]);
    }
}
