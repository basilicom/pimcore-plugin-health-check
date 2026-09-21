<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

/**
 * Immutable result of a single health check run.
 */
final readonly class CheckResult
{
    /**
     * @param array<string, mixed> $data measured values backing the message
     */
    public function __construct(
        public string $identifier,
        public string $label,
        public CheckStatus $status,
        public string $message = '',
        public array $data = [],
        public int $durationMs = 0,
    ) {
    }

    public function withDurationMs(int $durationMs): self
    {
        return new self(
            $this->identifier,
            $this->label,
            $this->status,
            $this->message,
            $this->data,
            $durationMs,
        );
    }

    public function isFailure(CheckStatus ...$failureLevels): bool
    {
        return in_array($this->status, $failureLevels, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'identifier' => $this->identifier,
            'label' => $this->label,
            'status' => $this->status->value,
            'message' => $this->message,
            'data' => (object) $this->data,
            'duration_ms' => $this->durationMs,
        ];
    }
}
