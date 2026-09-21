<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

use Doctrine\DBAL\Connection;
use Throwable;

final class DatabaseAccessibleCheck extends AbstractCheck
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config, private readonly Connection $connection)
    {
        parent::__construct($config);
    }

    public function getIdentifier(): string
    {
        return 'system:database_accessible';
    }

    public function getLabel(): string
    {
        return 'Database Accessible';
    }

    protected function doRun(): CheckResult
    {
        try {
            $userCount = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM users');
        } catch (Throwable $throwable) {
            return $this->critical('Unable to query database. [' . $throwable->getMessage() . ']');
        }

        if ($userCount === 0) {
            return $this->critical('Database query faulty response.', ['user_count' => 0]);
        }

        $defaultAdminActive = (bool) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM users WHERE `name` = 'admin' AND `active` = 1 "
            . "AND `password` IS NOT NULL AND `password` != ''",
        );

        if ($defaultAdminActive) {
            return $this->critical('Admin user is active.', ['user_count' => $userCount]);
        }

        return $this->ok('Database is accessible.', ['user_count' => $userCount]);
    }
}
