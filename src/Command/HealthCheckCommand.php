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

namespace Basilicom\PimcorePluginHealthCheck\Command;

use Basilicom\PimcorePluginHealthCheck\Services\CheckResult;
use Basilicom\PimcorePluginHealthCheck\Services\HealthCheckService;
use Basilicom\PimcorePluginHealthCheck\Severity;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'basilicom:health-check',
    description: 'Runs the system health checks and reports the result of every single one.',
)]
final class HealthCheckCommand extends Command
{
    public function __construct(
        private readonly HealthCheckService $healthCheckService,
        private readonly ?string $token = null,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->token === null) {
            $io->warning([
                'The health check endpoint is reachable by anyone.',
                'Set pimcore_plugin_health_check.token to require a shared secret;'
                . ' without one the endpoint stays open and answers every caller.',
            ]);
        }

        $results = $this->healthCheckService->run();

        if ($results === []) {
            $io->warning('No health check is active.');

            return Command::SUCCESS;
        }

        $io->table(
            ['Check', 'Result', 'Reason'],
            array_map(
                static fn (CheckResult $result): array => [
                    $result->shortName(),
                    match ($result->severity) {
                        Severity::Ok      => '<fg=green>SUCCESS</>',
                        Severity::Warning => '<fg=yellow>WARNING</>',
                        Severity::Failure => '<fg=red>FAILURE</>',
                    },
                    $result->reason(),
                ],
                $results
            )
        );

        $failures = array_filter($results, static fn (CheckResult $result): bool => $result->hasFailed());

        if ($failures !== []) {
            $io->error(sprintf('%d of %d checks failed.', count($failures), count($results)));

            return Command::FAILURE;
        }

        $warnings = array_filter($results, static fn (CheckResult $result): bool => $result->isWarning());

        if ($warnings !== []) {
            $io->warning(sprintf('%d of %d checks reported a warning.', count($warnings), count($results)));

            return Command::SUCCESS;
        }

        $io->success(sprintf('All %d checks passed.', count($results)));

        return Command::SUCCESS;
    }
}
