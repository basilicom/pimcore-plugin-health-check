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

namespace Basilicom\PimcorePluginHealthCheck\Services;

use Basilicom\PimcorePluginHealthCheck\Severity;
use Throwable;

final readonly class CheckResult
{
    /** @param class-string $check */
    public function __construct(
        public string $check,
        public Severity $severity = Severity::Ok,
        public ?Throwable $failure = null,
    ) {
    }

    public function hasFailed(): bool
    {
        return $this->severity === Severity::Failure;
    }

    public function isWarning(): bool
    {
        return $this->severity === Severity::Warning;
    }

    public function reason(): string
    {
        return $this->failure?->getMessage() ?? '';
    }

    public function shortName(): string
    {
        $position = strrpos($this->check, '\\');

        return $position === false ? $this->check : substr($this->check, $position + 1);
    }
}
