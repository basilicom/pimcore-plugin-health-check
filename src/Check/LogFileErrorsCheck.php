<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

use DateTimeImmutable;
use Throwable;

final class LogFileErrorsCheck extends AbstractCheck
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config, private readonly string $defaultLogDir)
    {
        parent::__construct($config);
    }

    public function getIdentifier(): string
    {
        return 'logs:log_file_errors';
    }

    public function getLabel(): string
    {
        return 'Log File Errors (prod.log & php.log)';
    }

    protected function doRun(): CheckResult
    {
        $logDir = rtrim((string) ($this->option('log_dir') ?: $this->defaultLogDir), '/');
        $lookbackHours = (int) $this->option('lookback_hours', 24);
        $maxBytes = (int) $this->option('max_bytes', 5 * 1024 * 1024);

        /** @var list<string> $files */
        $files = $this->option('files', ['prod.log', 'php.log']);

        $totalErrors = 0;
        $details = [];

        foreach ($files as $file) {
            $path = $logDir . '/' . ltrim($file, '/');

            if (!is_file($path) || !is_readable($path)) {
                $details[$file] = 'File not found or not readable';

                continue;
            }

            $count = $this->countRecentErrors($path, $lookbackHours, $maxBytes);
            $details[$file] = $count;
            $totalErrors += $count;
        }

        $message = sprintf('Found %d errors in log files in the last %d hours.', $totalErrors, $lookbackHours);

        return $this->thresholdResult(
            $totalErrors,
            $this->threshold('warning_threshold'),
            $this->threshold('critical_threshold'),
            $message,
            [
                'total_errors' => $totalErrors,
                'lookback_hours' => $lookbackHours,
                'files' => $details,
            ],
        );
    }

    /**
     * Counts error level entries newer than the lookback window.
     *
     * Only the last `$maxBytes` of a file are scanned to keep the check fast on large logs.
     */
    private function countRecentErrors(string $path, int $lookbackHours, int $maxBytes): int
    {
        $cutoff = new DateTimeImmutable(sprintf('-%d hours', $lookbackHours));
        $count = 0;

        $handle = @fopen($path, 'r');
        if ($handle === false) {
            return 0;
        }

        try {
            if ($maxBytes > 0 && (int) filesize($path) > $maxBytes) {
                fseek($handle, -$maxBytes, SEEK_END);
                // discard the partial line the seek landed in
                fgets($handle);
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
