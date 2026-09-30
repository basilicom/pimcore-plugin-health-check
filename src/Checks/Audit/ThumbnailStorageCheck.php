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
use Basilicom\PimcorePluginHealthCheck\Util\Bytes;
use Basilicom\PimcorePluginHealthCheck\Util\DirectorySize;

final readonly class ThumbnailStorageCheck extends AbstractReportingCheck
{
    public function __construct(
        bool $enabled,
        private string $path,
        private int|float|null $warningThreshold,
        private int|float|null $failureThreshold,
        private int $duTimeoutSeconds,
    ) {
        parent::__construct($enabled);
    }

    public function identifier(): string
    {
        return 'device:thumbnail_storage';
    }

    public function label(): string
    {
        return 'Thumbnail Storage';
    }

    protected function examine(): Report
    {
        if (!is_dir($this->path)) {
            return Report::ok('No thumbnails have been generated yet (0 B)', ['path' => $this->path, 'size_bytes' => 0]);
        }

        $size = DirectorySize::measure($this->path, $this->duTimeoutSeconds);

        if ($size === null) {
            return Report::notAvailable('Could not determine the thumbnail storage size (n/a)', ['path' => $this->path]);
        }

        return Report::graded(
            $size,
            $this->warningThreshold,
            $this->failureThreshold,
            sprintf('Thumbnail storage uses %s', Bytes::format($size)),
            ['path' => $this->path, 'size_bytes' => $size, 'size_human' => Bytes::format($size)]
        );
    }
}
