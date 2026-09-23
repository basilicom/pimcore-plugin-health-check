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

namespace Basilicom\PimcorePluginHealthCheck\Checks;

use Basilicom\PimcorePluginHealthCheck\Exception\DiskSpaceLowException;
use Basilicom\PimcorePluginHealthCheck\Severity;

final readonly class DiskSpaceCheck implements CheckInterface
{
    public function __construct(
        private string $directory,
        private bool $enabled,
        private int $warningBelowPercent,
        private int $failureBelowPercent,
    ) {
    }

    public function check(): void
    {
        $free  = @disk_free_space($this->directory);
        $total = @disk_total_space($this->directory);

        if ($free === false || $total === false || $total <= 0.0) {
            throw new DiskSpaceLowException('Free disk space could not be determined.');
        }

        $percent = $free / $total * 100;

        if ($percent < $this->failureBelowPercent) {
            throw new DiskSpaceLowException(
                sprintf('%.1f%% disk space left, below the %d%% failure threshold.', $percent, $this->failureBelowPercent)
            );
        }

        if ($percent < $this->warningBelowPercent) {
            throw new DiskSpaceLowException(
                sprintf('%.1f%% disk space left, below the %d%% warning threshold.', $percent, $this->warningBelowPercent),
                severity: Severity::Warning
            );
        }
    }

    public function isActive(): bool
    {
        return $this->enabled;
    }
}
