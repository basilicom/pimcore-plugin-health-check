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
use Basilicom\PimcorePluginHealthCheck\Services\EndOfLifeDateClientInterface;
use DateTimeImmutable;

/**
 * Compares a running version either against a configured one (hard-coded mode) or against the
 * release cycles endoflife.date publishes (dynamic mode, when no version is configured).
 */
abstract readonly class AbstractVersionCheck extends AbstractReportingCheck
{
    private const array OPERATORS = ['>=', '>', '=', '==', '<=', '<', '!='];

    public function __construct(
        bool $enabled,
        private EndOfLifeDateClientInterface $endOfLife,
        private ?string $requiredVersion,
        private string $operator,
    ) {
        parent::__construct($enabled);
    }

    /** @param array<string, mixed> $data */
    protected function compare(string $subject, string $product, string $current, string $display, array $data = []): Report
    {
        $data += ['current' => $current];

        if ($this->requiredVersion !== null && $this->requiredVersion !== '') {
            return $this->compareConfigured($subject, $current, $display, $data);
        }

        return $this->compareEndOfLife($subject, $product, $current, $display, $data);
    }

    /** @param array<string, mixed> $data */
    private function compareConfigured(string $subject, string $current, string $display, array $data): Report
    {
        $required = (string)$this->requiredVersion;
        $data += ['required' => $required, 'operator' => $this->operator];

        if (!in_array($this->operator, self::OPERATORS, true)) {
            return Report::notAvailable(sprintf('Unsupported version operator "%s" configured.', $this->operator), $data);
        }

        if (version_compare($current, $required, $this->operator)) {
            return Report::ok(sprintf('Current %s version is %s', $subject, $display), $data);
        }

        return Report::failure(
            sprintf('Current %s version is %s, required is %s %s', $subject, $display, $this->operator, $required),
            $data
        );
    }

    /** @param array<string, mixed> $data */
    private function compareEndOfLife(string $subject, string $product, string $current, string $display, array $data): Report
    {
        $cycles = $this->endOfLife->cycles($product);
        $cycle  = self::cycleOf($current);
        $data += ['cycle' => $cycle, 'product' => $product];

        if ($cycles === null) {
            return Report::notAvailable('Could not fetch version data from endoflife.date (n/a)', $data);
        }

        $today     = new DateTimeImmutable('today');
        $own       = null;
        $supported = [];

        foreach ($cycles as $entry) {
            $name = isset($entry['cycle']) ? (string)$entry['cycle'] : null;

            if ($name === null) {
                continue;
            }

            if ($name === $cycle) {
                $own = $entry;
            }

            if (!self::isEndOfLife($entry['eol'] ?? null, $today)) {
                $supported[] = $name;
            }
        }

        if ($own === null) {
            return Report::notAvailable(sprintf('endoflife.date has no data for %s %s (n/a)', $subject, $cycle), $data);
        }

        usort($supported, static fn (string $a, string $b): int => version_compare($b, $a));
        $latest                         = $supported[0] ?? null;
        $data['latest_supported_cycle'] = $latest;

        if (self::isEndOfLife($own['eol'] ?? null, $today)) {
            $eol         = is_string($own['eol'] ?? null) ? $own['eol'] : 'an unknown date';
            $data['eol'] = $eol;

            return Report::failure(
                sprintf('Current %s version is %s, which reached its end of life on %s', $subject, $display, $eol),
                $data
            );
        }

        if ($latest !== null && $latest !== $cycle) {
            return Report::warning(
                sprintf('Current %s version is %s, the latest supported release cycle is %s', $subject, $display, $latest),
                $data
            );
        }

        return Report::ok(sprintf('Current %s version is %s', $subject, $display), $data);
    }

    /** endoflife.date reports false while no EOL date is set, true once it passed, a date otherwise. */
    private static function isEndOfLife(mixed $eol, DateTimeImmutable $today): bool
    {
        if ($eol === true) {
            return true;
        }

        if (!is_string($eol)) {
            return false;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $eol);

        return $date !== false && $date < $today;
    }

    protected static function cycleOf(string $version): string
    {
        return preg_match('/^(\d+)\.(\d+)/', $version, $matches) === 1 ? $matches[1] . '.' . $matches[2] : $version;
    }
}
