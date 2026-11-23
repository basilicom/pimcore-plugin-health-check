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
use Doctrine\DBAL\Connection;

final readonly class InactiveAdminUsersCheck extends AbstractReportingCheck
{
    public function __construct(
        bool $enabled,
        private Connection $connection,
        private int $days,
        private int|float|null $warningThreshold,
        private int|float|null $failureThreshold,
    ) {
        parent::__construct($enabled);
    }

    public function identifier(): string
    {
        return 'security:inactive_admin_users';
    }

    public function label(): string
    {
        return 'Inactive Admin Users';
    }

    protected function examine(): Report
    {
        /** @var list<string> $names */
        $names = $this->connection->fetchFirstColumn(
            "SELECT `name` FROM users
             WHERE `type` = 'user' AND `admin` = 1 AND `active` = 1 AND (`lastLogin` IS NULL OR `lastLogin` < ?)
             ORDER BY `name`",
            [time() - $this->days * 86400]
        );

        $count = count($names);

        return Report::graded(
            $count,
            $this->warningThreshold,
            $this->failureThreshold,
            $count === 0
                ? sprintf('All admin users logged in within the last %d days.', $this->days)
                : sprintf('%d admin %s not logged in for %d days: %s', $count, $count === 1 ? 'user has' : 'users have', $this->days, implode(', ', $names)),
            ['count' => $count, 'days' => $this->days, 'users' => $names]
        );
    }
}
