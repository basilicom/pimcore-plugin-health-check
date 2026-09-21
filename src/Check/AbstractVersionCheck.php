<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

use Basilicom\PimcorePluginHealthCheck\Services\EndOfLifeDateClientInterface;
use DateTimeImmutable;

/**
 * Shared logic for version checks that can either compare against a hard coded version
 * or derive the expected version dynamically from endoflife.date.
 */
abstract class AbstractVersionCheck extends AbstractCheck
{
    private const VALID_OPERATORS = ['>=', '>', '=', '==', '<=', '<', '!='];

    /**
     * Compares the running version against the configured one.
     *
     * @param array<string, mixed> $data
     * @param string|null          $display full version string to show instead of the comparable one
     */
    protected function staticResult(
        string $current,
        string $required,
        string $operator,
        string $subject,
        array $data = [],
        ?string $display = null,
    ): CheckResult {
        if (!in_array($operator, self::VALID_OPERATORS, true)) {
            return $this->na(sprintf('Unsupported version operator "%s" configured.', $operator));
        }

        $display ??= $current;
        $data += ['current' => $current, 'required' => $required, 'operator' => $operator];

        if (version_compare($current, $required, $operator)) {
            return $this->ok(sprintf('Current %s version is %s', $subject, $display), $data);
        }

        return $this->critical(
            sprintf('Current %s version is %s, required is %s %s', $subject, $display, $operator, $required),
            $data,
        );
    }

    /**
     * Grades the running version against the release cycles published by endoflife.date.
     *
     * @param array<string, mixed> $data
     */
    protected function endOfLifeResult(
        EndOfLifeDateClientInterface $client,
        string $product,
        string $current,
        string $cycle,
        string $subject,
        array $data = [],
    ): CheckResult {
        $cycles = $client->getCycles($product);
        $data += ['current' => $current, 'cycle' => $cycle, 'product' => $product];

        if ($cycles === null) {
            return $this->na('Could not fetch version data from endoflife.date (n/a)', $data);
        }

        $today = new DateTimeImmutable('today');
        $supported = [];
        $ownCycle = null;

        foreach ($cycles as $entry) {
            $entryCycle = isset($entry['cycle']) ? (string) $entry['cycle'] : null;
            if ($entryCycle === null) {
                continue;
            }

            if ($entryCycle === $cycle) {
                $ownCycle = $entry;
            }

            if (!$this->isEndOfLife($entry['eol'] ?? null, $today)) {
                $supported[] = $entryCycle;
            }
        }

        if ($ownCycle === null) {
            return $this->na(
                sprintf('endoflife.date has no data for %s %s (n/a)', $subject, $cycle),
                $data,
            );
        }

        usort($supported, static fn (string $a, string $b): int => version_compare($b, $a));
        $latestSupported = $supported[0] ?? null;
        $data['latest_supported_cycle'] = $latestSupported;

        if ($this->isEndOfLife($ownCycle['eol'] ?? null, $today)) {
            $eol = is_string($ownCycle['eol'] ?? null) ? $ownCycle['eol'] : 'unknown date';
            $data['eol'] = $eol;

            return $this->critical(
                sprintf('Current %s version is %s, which reached its end of life on %s', $subject, $current, $eol),
                $data,
            );
        }

        if ($latestSupported !== null && $latestSupported !== $cycle) {
            return $this->warning(
                sprintf(
                    'Current %s version is %s, the latest supported release cycle is %s',
                    $subject,
                    $current,
                    $latestSupported,
                ),
                $data,
            );
        }

        return $this->ok(sprintf('Current %s version is %s', $subject, $current), $data);
    }

    /**
     * endoflife.date reports `false` for cycles without an announced EOL date,
     * `true` for cycles that are already out of support and a date string otherwise.
     */
    private function isEndOfLife(mixed $eol, DateTimeImmutable $today): bool
    {
        if ($eol === false || $eol === null) {
            return false;
        }

        if ($eol === true) {
            return true;
        }

        if (!is_string($eol)) {
            return false;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $eol);

        return $date !== false && $date < $today;
    }

    /**
     * Reduces a full version string to its `major.minor` release cycle.
     */
    protected function releaseCycle(string $version): string
    {
        if (preg_match('/^(\d+)\.(\d+)/', $version, $matches) === 1) {
            return $matches[1] . '.' . $matches[2];
        }

        return $version;
    }
}
