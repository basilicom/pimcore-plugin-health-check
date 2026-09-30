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

final readonly class HostingSizeCheck extends AbstractReportingCheck
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
        return 'device:hosting_size';
    }

    public function label(): string
    {
        return 'Hosting Size';
    }

    protected function examine(): Report
    {
        $size = DirectorySize::measure($this->path, $this->duTimeoutSeconds);

        if ($size === null) {
            return Report::notAvailable('Could not determine the project size (n/a)', ['path' => $this->path]);
        }

        return Report::graded(
            $size,
            $this->warningThreshold,
            $this->failureThreshold,
            sprintf('Hosting size is %s', Bytes::format($size)),
            ['path' => $this->path, 'size_bytes' => $size, 'size_human' => Bytes::format($size)]
        );
    }
}
