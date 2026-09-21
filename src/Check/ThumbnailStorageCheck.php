<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

use Basilicom\PimcorePluginHealthCheck\Util\Bytes;
use Basilicom\PimcorePluginHealthCheck\Util\DirectorySize;

/**
 * Size of the generated thumbnails, which often dwarfs the original assets (PF-88 metric 27).
 */
final class ThumbnailStorageCheck extends AbstractCheck
{
    public function getIdentifier(): string
    {
        return 'device:thumbnail_storage';
    }

    public function getLabel(): string
    {
        return 'Thumbnail Storage';
    }

    protected function doRun(): CheckResult
    {
        $path = (string) $this->option('path', '');

        if ($path === '' || !is_dir($path)) {
            return $this->ok(
                sprintf('Thumbnail directory %s does not exist yet (0 B)', $path),
                ['path' => $path, 'size_bytes' => 0],
            );
        }

        $size = DirectorySize::calculate($path);

        if ($size === null) {
            return $this->na(sprintf('Could not determine the size of %s (n/a)', $path), ['path' => $path]);
        }

        return $this->thresholdResult(
            $size,
            $this->threshold('warning_threshold'),
            $this->threshold('critical_threshold'),
            sprintf('Thumbnail storage uses %s', Bytes::format($size)),
            ['path' => $path, 'size_bytes' => $size, 'size_human' => Bytes::format($size)],
        );
    }
}
