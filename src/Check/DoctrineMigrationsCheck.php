<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

use Doctrine\Migrations\DependencyFactory;

final class DoctrineMigrationsCheck extends AbstractCheck
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config, private readonly ?DependencyFactory $dependencyFactory = null)
    {
        parent::__construct($config);
    }

    public function getIdentifier(): string
    {
        return 'system:doctrine_migrations';
    }

    public function getLabel(): string
    {
        return 'Doctrine Migrations';
    }

    protected function doRun(): CheckResult
    {
        if ($this->dependencyFactory === null) {
            return $this->na('Doctrine migrations are not available (n/a)');
        }

        $calculator = $this->dependencyFactory->getMigrationStatusCalculator();
        $pending = count($calculator->getNewMigrations()->getItems());
        $unavailable = count($calculator->getExecutedUnavailableMigrations()->getItems());
        $data = ['pending' => $pending, 'executed_unavailable' => $unavailable];

        if ($unavailable > 0) {
            return $this->critical('Migrations applied which are not available', $data);
        }

        if ($pending > 0) {
            return $this->critical(
                sprintf('%d migration%s pending', $pending, $pending === 1 ? ' is' : 's are'),
                $data,
            );
        }

        return $this->ok('All migrations are applied', $data);
    }
}
