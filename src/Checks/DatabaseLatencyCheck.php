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
use Basilicom\PimcorePluginHealthCheck\Exception\DatabaseTooSlowException;
use Doctrine\DBAL\Connection;
use Throwable;

/**
 * The threshold answers "is the database broken", not "is it fast" - a tight one turns every load
 * spike into an outage.
 */
final readonly class DatabaseLatencyCheck implements CheckInterface
{
    public function __construct(
        private Connection $connection,
        private bool $enabled,
        private int $thresholdMilliseconds,
    ) {
    }

    public function check(): void
    {
        $start = hrtime(true);

        try {
            $this->connection->fetchOne('SELECT 1');
        } catch (Throwable $exception) {
            throw new DatabaseNotAccessibleException('Database is not accessible.', previous: $exception);
        }

        $elapsed = intdiv(hrtime(true) - $start, 1_000_000);

        if ($elapsed > $this->thresholdMilliseconds) {
            throw new DatabaseTooSlowException(
                sprintf('Database round trip took %d ms, threshold is %d ms.', $elapsed, $this->thresholdMilliseconds)
            );
        }
    }

    public function isActive(): bool
    {
        return $this->enabled;
    }
}
