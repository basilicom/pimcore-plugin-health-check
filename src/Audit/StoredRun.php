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

namespace Basilicom\PimcorePluginHealthCheck\Audit;

use Basilicom\PimcorePluginHealthCheck\Services\CheckResult;
use Basilicom\PimcorePluginHealthCheck\Severity;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * One completed audit run as the dashboard and the API see it. Exceptions are folded into the
 * message when a run is stored - a Throwable does not survive JSON, and the reason is what matters.
 */
final readonly class StoredRun
{
    /** @param list<CheckResult> $results */
    public function __construct(
        public DateTimeImmutable $generatedAt,
        public array $results,
        public int $durationMs = 0,
    ) {
    }

    /** Ok and Skipped/NotAvailable are not problems; the worst of the rest wins. */
    public function overall(): Severity
    {
        $worst = Severity::Ok;

        foreach ($this->results as $result) {
            if ($result->hasFailed()) {
                return Severity::Failure;
            }

            if ($result->isWarning()) {
                $worst = Severity::Warning;
            }
        }

        return $worst;
    }

    public function isHealthy(): bool
    {
        return $this->overall() !== Severity::Failure;
    }

    /** @return array<string, int> keyed by Severity::key(), every key present */
    public function summary(): array
    {
        $summary = [];

        foreach (Severity::cases() as $severity) {
            $summary[$severity->key()] = 0;
        }

        foreach ($this->results as $result) {
            ++$summary[$result->severity->key()];
        }

        return $summary;
    }

    public function find(string $identifier): ?CheckResult
    {
        foreach ($this->results as $result) {
            if ($result->identifier() === $identifier) {
                return $result;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'generated_at' => $this->generatedAt->format(DateTimeInterface::ATOM),
            'duration_ms'  => $this->durationMs,
            'checks'       => array_map(static fn (CheckResult $result): array => [
                'check'       => $result->check,
                'identifier'  => $result->identifier(),
                'label'       => $result->label(),
                'status'      => $result->severity->key(),
                'message'     => $result->reason(),
                'data'        => $result->data,
                'duration_ms' => $result->durationMs,
            ], $this->results),
        ];
    }

    /** @param array<string, mixed> $data as produced by toArray() */
    public static function fromArray(array $data): self
    {
        $results = [];

        foreach (is_array($data['checks'] ?? null) ? $data['checks'] : [] as $row) {
            if (!is_array($row) || !is_string($row['check'] ?? null)) {
                continue;
            }

            /** @var class-string $check */
            $check     = $row['check'];
            $results[] = new CheckResult(
                $check,
                Severity::fromKey((string)($row['status'] ?? 'ok')),
                null,
                (string)($row['message'] ?? ''),
                is_array($row['data'] ?? null) ? $row['data'] : [],
                isset($row['identifier']) ? (string)$row['identifier'] : null,
                isset($row['label']) ? (string)$row['label'] : null,
                (int)($row['duration_ms'] ?? 0),
            );
        }

        $generatedAt = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, (string)($data['generated_at'] ?? ''));

        return new self($generatedAt instanceof DateTimeImmutable ? $generatedAt : new DateTimeImmutable(), $results, (int)($data['duration_ms'] ?? 0));
    }
}
