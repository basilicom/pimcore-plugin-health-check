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
    /**
     * @param class-string         $check
     * @param array<string, mixed> $data measured values behind the message
     */
    public function __construct(
        public string $check,
        public Severity $severity = Severity::Ok,
        public ?Throwable $failure = null,
        public string $message = '',
        public array $data = [],
        public ?string $identifier = null,
        public ?string $label = null,
        public int $durationMs = 0,
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
        return $this->failure?->getMessage() ?? $this->message;
    }

    public function shortName(): string
    {
        $position = strrpos($this->check, '\\');

        return $position === false ? $this->check : substr($this->check, $position + 1);
    }

    /**
     * A plain check has no identifier of its own, so it gets one derived from its class name:
     * `DatabaseAccessibleCheck` becomes `core:database_accessible`.
     */
    public function identifier(): string
    {
        return $this->identifier ?? 'core:' . strtolower((string)preg_replace('/(?<!^)[A-Z]/', '_$0', $this->baseName()));
    }

    /** `DatabaseAccessibleCheck` becomes `Database Accessible`. */
    public function label(): string
    {
        return $this->label ?? (string)preg_replace('/(?<!^)[A-Z]/', ' $0', $this->baseName());
    }

    private function baseName(): string
    {
        $name = $this->shortName();

        return str_ends_with($name, 'Check') && $name !== 'Check' ? substr($name, 0, -5) : $name;
    }
}
