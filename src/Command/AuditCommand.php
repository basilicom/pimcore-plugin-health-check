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

use Basilicom\PimcorePluginHealthCheck\Audit\RunStoreInterface;
use Basilicom\PimcorePluginHealthCheck\Audit\StoredRun;
use Basilicom\PimcorePluginHealthCheck\Services\CheckResult;
use Basilicom\PimcorePluginHealthCheck\Services\HealthCheckService;
use Basilicom\PimcorePluginHealthCheck\Severity;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Runs the core checks and the audit checks, stores the combined result for the dashboard and
 * the API, and exits non-zero on a failure. Meant for cron; the inventory checks shell out to
 * composer and size directories, which is nothing a page view should trigger.
 */
#[AsCommand(
    name: 'basilicom:health-check:audit',
    description: 'Runs all health and audit checks, stores the result for the dashboard and the API.',
)]
final class AuditCommand extends Command
{
    public function __construct(
        private readonly HealthCheckService $coreService,
        private readonly HealthCheckService $auditService,
        private readonly RunStoreInterface $runStore,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'table or json', 'table')
            ->addOption('no-store', null, InputOption::VALUE_NONE, 'Print the result without storing it');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $format = (string)$input->getOption('format');

        if (!in_array($format, ['table', 'json'], true)) {
            $io->error(sprintf('Unknown format "%s", use table or json.', $format));

            return Command::INVALID;
        }

        $start   = hrtime(true);
        $results = [...$this->coreService->run(), ...$this->auditService->run()];
        $run     = new StoredRun(new DateTimeImmutable(), $results, intdiv(hrtime(true) - $start, 1_000_000));

        if (!$input->getOption('no-store')) {
            $this->runStore->save($run);
        }

        if ($format === 'json') {
            $output->writeln((string)json_encode(
                ['status' => $run->overall()->key(), 'healthy' => $run->isHealthy(), 'summary' => $run->summary()] + $run->toArray(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            ));

            return $run->isHealthy() ? Command::SUCCESS : Command::FAILURE;
        }

        $io->table(
            ['Check', 'Result', 'Message', 'ms'],
            array_map(static fn (CheckResult $r): array => [
                $r->label(),
                match ($r->severity) {
                    Severity::Ok           => '<fg=green>OK</>',
                    Severity::Warning      => '<fg=yellow>WARNING</>',
                    Severity::Failure      => '<fg=red>CRITICAL</>',
                    Severity::Skipped      => '<fg=gray>SKIPPED</>',
                    Severity::NotAvailable => '<fg=gray>N/A</>',
                },
                $r->reason(),
                $r->durationMs,
            ], $results)
        );

        $summary = implode(', ', array_map(static fn (string $k, int $v): string => "$v $k", array_keys($run->summary()), $run->summary()));

        if (!$run->isHealthy()) {
            $io->error(sprintf('System is NOT healthy (%s).', $summary));

            return Command::FAILURE;
        }

        $io->success(sprintf('System is healthy (%s).', $summary));

        return Command::SUCCESS;
    }
}
