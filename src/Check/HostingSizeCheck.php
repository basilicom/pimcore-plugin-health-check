<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

use Basilicom\PimcorePluginHealthCheck\Util\Bytes;
use Basilicom\PimcorePluginHealthCheck\Util\DirectorySize;

final class HostingSizeCheck extends AbstractCheck
{
    public function getIdentifier(): string
    {
        return 'device:hosting_size';
    }

    public function getLabel(): string
    {
        return 'Hosting Size';
    }

    protected function doRun(): CheckResult
    {
        $path = (string) $this->option('path', '.');
        $size = DirectorySize::calculate($path);

        if ($size === null) {
            return $this->na(sprintf('Could not determine the size of %s (n/a)', $path), ['path' => $path]);
        }

        return $this->thresholdResult(
            $size,
            $this->threshold('warning_threshold'),
            $this->threshold('critical_threshold'),
            sprintf('Hosting size is %s', Bytes::format($size)),
            ['path' => $path, 'size_bytes' => $size, 'size_human' => Bytes::format($size)],
        );
    }
}
