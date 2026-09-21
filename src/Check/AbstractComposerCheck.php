<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

use JsonException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Shared plumbing for the checks that shell out to composer.
 *
 * Composer is not guaranteed to be installed, reachable or authorised on a production
 * host, so every failure mode ends up as `NotAvailable` instead of a critical result.
 */
abstract class AbstractComposerCheck extends AbstractCheck
{
    /**
     * @param list<string> $arguments
     *
     * @return array{output: array<string, mixed>|null, reason: string|null}
     */
    protected function runComposer(array $arguments): array
    {
        if (!class_exists(Process::class)) {
            return ['output' => null, 'reason' => 'symfony/process is not installed'];
        }

        $binary = (string) $this->option('composer_binary', 'composer');
        $workingDirectory = (string) $this->option('working_directory', getcwd() ?: '.');
        $timeout = (float) $this->option('timeout', 60);

        try {
            $process = new Process(
                [$binary, ...$arguments, '--no-interaction'],
                $workingDirectory,
                ['COMPOSER_NO_INTERACTION' => '1'],
                null,
                $timeout,
            );
            $process->run();
        } catch (ProcessTimedOutException) {
            return ['output' => null, 'reason' => sprintf('composer timed out after %ds', (int) $timeout)];
        } catch (Throwable $throwable) {
            return ['output' => null, 'reason' => $throwable->getMessage()];
        }

        $stdout = trim($process->getOutput());

        if ($stdout === '') {
            return [
                'output' => null,
                'reason' => $this->shortReason($process->getErrorOutput()) ?? 'composer produced no output',
            ];
        }

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [
                'output' => null,
                'reason' => $this->shortReason($process->getErrorOutput()) ?? 'composer returned no valid JSON',
            ];
        }

        return ['output' => $decoded, 'reason' => null];
    }

    private function shortReason(string $stderr): ?string
    {
        $stderr = trim($stderr);

        if ($stderr === '') {
            return null;
        }

        $firstLine = strtok($stderr, "\n") ?: $stderr;

        return mb_substr(trim($firstLine), 0, 160);
    }
}
