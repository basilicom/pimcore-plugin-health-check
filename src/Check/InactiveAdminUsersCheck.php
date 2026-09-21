<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

use Doctrine\DBAL\Connection;

final class InactiveAdminUsersCheck extends AbstractCheck
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
        return 'security:inactive_admin_users';
    }

    public function getLabel(): string
    {
        return 'Inactive Admin Users';
    }

    protected function doRun(): CheckResult
    {
        $days = (int) $this->option('days', 90);
        $cutoff = time() - $days * 86400;

        /** @var list<string> $names */
        $names = $this->connection->fetchFirstColumn(
            "SELECT `name` FROM users
             WHERE `type` = 'user' AND `admin` = 1 AND `active` = 1
               AND (`lastLogin` IS NULL OR `lastLogin` < :cutoff)
             ORDER BY `name`",
            ['cutoff' => $cutoff],
        );

        $count = count($names);
        $message = $count === 0
            ? sprintf('All admin users logged in within the last %d days.', $days)
            : sprintf(
                '%d admin %s not logged in for %d days: %s',
                $count,
                $count === 1 ? 'user has' : 'users have',
                $days,
                implode(', ', $names),
            );

        return $this->thresholdResult(
            $count,
            $this->threshold('warning_threshold'),
            $this->threshold('critical_threshold'),
            $message,
            ['count' => $count, 'days' => $days, 'users' => $names],
        );
    }
}
