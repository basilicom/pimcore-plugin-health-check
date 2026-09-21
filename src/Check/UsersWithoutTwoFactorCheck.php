<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

use Doctrine\DBAL\Connection;

final class UsersWithoutTwoFactorCheck extends AbstractCheck
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
        return 'security:users_without_2fa';
    }

    public function getLabel(): string
    {
        return 'Admins Without 2FA';
    }

    protected function doRun(): CheckResult
    {
        /** @var list<array{name: string|null, twoFactorAuthentication: string|null}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            "SELECT `name`, `twoFactorAuthentication` FROM users
             WHERE `type` = 'user' AND `admin` = 1 AND `active` = 1
             ORDER BY `name`",
        );

        $names = [];

        foreach ($rows as $row) {
            if (!$this->hasTotpSecret($row['twoFactorAuthentication'] ?? null)) {
                $names[] = (string) ($row['name'] ?? '');
            }
        }

        $count = count($names);
        $message = $count === 0
            ? 'All admin users have two factor authentication configured.'
            : sprintf(
                '%d admin %s no two factor authentication configured: %s',
                $count,
                $count === 1 ? 'user has' : 'users have',
                implode(', ', $names),
            );

        return $this->thresholdResult(
            $count,
            $this->threshold('warning_threshold'),
            $this->threshold('critical_threshold'),
            $message,
            ['count' => $count, 'total_admins' => count($rows), 'users' => $names],
        );
    }

    /**
     * Pimcore stores the 2FA settings as a JSON object in `users.twoFactorAuthentication`,
     * e.g. `{"required":true,"enabled":true,"secret":"...","type":"google"}`.
     */
    private function hasTotpSecret(?string $raw): bool
    {
        if ($raw === null || trim($raw) === '') {
            return false;
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            return false;
        }

        return ($decoded['enabled'] ?? false) === true
            && is_string($decoded['secret'] ?? null)
            && $decoded['secret'] !== '';
    }
}
