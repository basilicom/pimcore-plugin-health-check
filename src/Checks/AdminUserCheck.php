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

namespace Basilicom\PimcorePluginHealthCheck\Checks;

use Basilicom\PimcorePluginHealthCheck\Exception\AdminUserActiveException;
use Basilicom\PimcorePluginHealthCheck\Exception\DatabaseNotAccessibleException;
use Doctrine\DBAL\Connection;
use Throwable;

final readonly class AdminUserCheck implements CheckInterface
{
    public function __construct(
        private Connection $connection,
        private bool $enabled,
        private string $userName,
    ) {
    }

    public function check(): void
    {
        try {
            $found = $this->connection->fetchOne(
                "SELECT 1 FROM users WHERE `type` = 'user' AND `name` = ? AND `active` = 1",
                [$this->userName]
            );
        } catch (Throwable $exception) {
            throw new DatabaseNotAccessibleException('Database is not accessible.', previous: $exception);
        }

        if ($found !== false) {
            throw new AdminUserActiveException(
                sprintf('The default administrator account "%s" is active.', $this->userName)
            );
        }
    }

    public function isActive(): bool
    {
        return $this->enabled;
    }
}
