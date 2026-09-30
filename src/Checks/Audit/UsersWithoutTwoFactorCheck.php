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

/**
 * Pimcore keeps the 2FA settings as JSON in `users.twoFactorAuthentication`, e.g.
 * `{"required":true,"enabled":true,"secret":"...","type":"google"}`.
 */
final readonly class UsersWithoutTwoFactorCheck extends AbstractReportingCheck
{
    public function __construct(
        bool $enabled,
        private Connection $connection,
        private int|float|null $warningThreshold,
        private int|float|null $failureThreshold,
    ) {
        parent::__construct($enabled);
    }

    public function identifier(): string
    {
        return 'security:users_without_2fa';
    }

    public function label(): string
    {
        return 'Admins Without 2FA';
    }

    protected function examine(): Report
    {
        /** @var list<array{name: string|null, twoFactorAuthentication: string|null}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            "SELECT `name`, `twoFactorAuthentication` FROM users WHERE `type` = 'user' AND `admin` = 1 AND `active` = 1 ORDER BY `name`"
        );

        $names = [];

        foreach ($rows as $row) {
            if (!self::hasSecret($row['twoFactorAuthentication'] ?? null)) {
                $names[] = (string)($row['name'] ?? '');
            }
        }

        $count = count($names);

        return Report::graded(
            $count,
            $this->warningThreshold,
            $this->failureThreshold,
            $count === 0
                ? 'All admin users have two factor authentication configured.'
                : sprintf('%d admin %s no two factor authentication configured: %s', $count, $count === 1 ? 'user has' : 'users have', implode(', ', $names)),
            ['count' => $count, 'total_admins' => count($rows), 'users' => $names]
        );
    }

    private static function hasSecret(?string $raw): bool
    {
        $decoded = is_string($raw) && trim($raw) !== '' ? json_decode($raw, true) : null;

        return is_array($decoded)
            && ($decoded['enabled'] ?? false) === true
            && is_string($decoded['secret'] ?? null)
            && $decoded['secret'] !== '';
    }
}
