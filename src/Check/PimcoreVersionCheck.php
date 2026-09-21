<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

use Pimcore\Version;

final class PimcoreVersionCheck extends AbstractCheck
{
    public function getIdentifier(): string
    {
        return 'pimcore:version';
    }

    public function getLabel(): string
    {
        return 'Pimcore Version';
    }

    protected function doRun(): CheckResult
    {
        $version = ltrim((string) Version::getVersion(), 'v');

        if ($version === '') {
            return $this->na('Could not determine the Pimcore version (n/a)');
        }

        return $this->ok(
            sprintf('The system is running on Pimcore v%s', $version),
            ['version' => $version, 'major_version' => Version::getMajorVersion()],
        );
    }
}
