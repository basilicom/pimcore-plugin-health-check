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

use Basilicom\PimcorePluginHealthCheck\Exception\PendingMigrationsException;
use Basilicom\PimcorePluginHealthCheck\Severity;
use Doctrine\Migrations\DependencyFactory;
use Throwable;

/**
 * A warning, not a failure: in a rolling deploy the new code is up before the migration ran.
 */
final readonly class PendingMigrationsCheck implements CheckInterface
{
    public function __construct(
        private DependencyFactory $dependencyFactory,
        private bool $enabled,
    ) {
    }

    public function check(): void
    {
        try {
            $pending = count($this->dependencyFactory->getMigrationStatusCalculator()->getNewMigrations());
        } catch (Throwable $exception) {
            throw new PendingMigrationsException('Migration status could not be determined.', previous: $exception);
        }

        if ($pending > 0) {
            throw new PendingMigrationsException(
                sprintf('%d migration(s) have not been executed.', $pending),
                severity: Severity::Warning
            );
        }
    }

    public function isActive(): bool
    {
        return $this->enabled;
    }
}
