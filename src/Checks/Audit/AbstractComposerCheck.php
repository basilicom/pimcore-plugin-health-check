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
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Composer is not guaranteed to be installed, reachable or authorised on a production host, so
 * every failure mode ends up as NotAvailable, never as a failure.
 */
abstract readonly class AbstractComposerCheck extends AbstractReportingCheck
{
    public function __construct(
        bool $enabled,
        private string $binary,
        private string $workingDirectory,
        private int $timeoutSeconds,
    ) {
        parent::__construct($enabled);
    }

    /**
     * @param list<string> $arguments
     *
     * @return array{0: array<string, mixed>|null, 1: string} decoded JSON, or null plus a short reason
     */
    protected function composer(array $arguments): array
    {
        try {
            $process = new Process(
                [$this->binary, ...$arguments, '--no-interaction'],
                $this->workingDirectory,
                ['COMPOSER_NO_INTERACTION' => '1'],
                null,
                (float)$this->timeoutSeconds
            );
            $process->run();
        } catch (ProcessTimedOutException) {
            return [null, sprintf('composer timed out after %d s', $this->timeoutSeconds)];
        } catch (Throwable $exception) {
            return [null, (new \ReflectionClass($exception))->getShortName()];
        }

        $decoded = json_decode(trim($process->getOutput()), true);

        if (!is_array($decoded)) {
            return [null, self::firstLine($process->getErrorOutput()) ?? 'composer returned no JSON'];
        }

        return [$decoded, ''];
    }

    private static function firstLine(string $output): ?string
    {
        $line = strtok(trim($output), "\n");

        return is_string($line) && trim($line) !== '' ? mb_substr(trim($line), 0, 160) : null;
    }
}
