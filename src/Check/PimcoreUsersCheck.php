<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

use Doctrine\DBAL\Connection;

final class PimcoreUsersCheck extends AbstractCheck
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
        return 'pimcore:users';
    }

    public function getLabel(): string
    {
        return 'Pimcore Users';
    }

    protected function doRun(): CheckResult
    {
        $total = (int) $this->connection->fetchOne("SELECT COUNT(*) FROM users WHERE `type` = 'user'");
        $active = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM users WHERE `type` = 'user' AND `active` = 1",
        );

        return $this->ok(
            sprintf('There are %d Pimcore users in the system', $total),
            ['count' => $total, 'active' => $active],
        );
    }
}
