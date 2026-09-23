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

use Basilicom\PimcorePluginHealthCheck\Exception\DatabaseNotAccessibleException;
use Doctrine\DBAL\Connection;
use Throwable;

final readonly class DatabaseAccessibleCheck implements CheckInterface
{
    public function __construct(
        private Connection $connection,
        private bool $enabled,
    ) {
    }

    public function check(): void
    {
        try {
            $userCount = $this->connection->fetchOne('SELECT COUNT(*) FROM users');
        } catch (Throwable $exception) {
            throw new DatabaseNotAccessibleException('Database is not accessible.', previous: $exception);
        }

        if ((int)$userCount === 0) {
            throw new DatabaseNotAccessibleException('Database is reachable but the users table is empty.');
        }
    }

    public function isActive(): bool
    {
        return $this->enabled;
    }
}
