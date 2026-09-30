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
use DateTimeImmutable;
use Throwable;

final readonly class LogFileErrorsCheck extends AbstractReportingCheck
{
    /** @param list<string> $files file names relative to the log directory */
    public function __construct(
        bool $enabled,
        private string $logDirectory,
        private array $files,
        private int $lookbackHours,
        private int|float|null $warningThreshold,
        private int|float|null $failureThreshold,
        private int $maxBytes,
    ) {
        parent::__construct($enabled);
    }

    public function identifier(): string
    {
        return 'logs:log_file_errors';
    }

    public function label(): string
    {
        return 'Log File Errors (prod.log & php.log)';
    }

    protected function examine(): Report
    {
        $total   = 0;
        $details = [];

        foreach ($this->files as $file) {
            $path = rtrim($this->logDirectory, '/') . '/' . ltrim($file, '/');

            if (!is_file($path) || !is_readable($path)) {
                $details[$file] = 'File not found or not readable';

                continue;
            }

            $count          = $this->countRecentErrors($path);
            $details[$file] = $count;
            $total += $count;
        }

        return Report::graded(
            $total,
            $this->warningThreshold,
            $this->failureThreshold,
            sprintf('Found %d errors in log files in the last %d hours.', $total, $this->lookbackHours),
            ['total_errors' => $total, 'lookback_hours' => $this->lookbackHours, 'files' => $details]
        );
    }

    /** Only the tail of a large file is scanned, so the check stays cheap on a busy log. */
    private function countRecentErrors(string $path): int
    {
        $cutoff = new DateTimeImmutable(sprintf('-%d hours', $this->lookbackHours));
        $handle = @fopen($path, 'r');
        $count  = 0;

        if ($handle === false) {
            return 0;
        }

        try {
            if ($this->maxBytes > 0 && (int)filesize($path) > $this->maxBytes) {
                fseek($handle, -$this->maxBytes, SEEK_END);
                fgets($handle); // discard the partial line the seek landed in
            }

            while (($line = fgets($handle)) !== false) {
                if (preg_match('/\[(\d{4}-\d{2}-\d{2}[\sT]\d{2}:\d{2}:\d{2})/', $line, $matches) !== 1) {
                    continue;
                }

                try {
                    if (new DateTimeImmutable($matches[1]) < $cutoff) {
                        continue;
                    }
                } catch (Throwable) {
                    continue;
                }

                if (preg_match('/\.(ERROR|CRITICAL|ALERT|EMERGENCY)\b/i', $line) === 1
                    || preg_match('/\b(Fatal error|Parse error|PHP Fatal|PHP Warning|PHP Error)\b/i', $line) === 1
                ) {
                    ++$count;
                }
            }
        } finally {
            fclose($handle);
        }

        return $count;
    }
}
