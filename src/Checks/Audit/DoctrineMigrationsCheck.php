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
use Doctrine\Migrations\DependencyFactory;

/**
 * The core PendingMigrationsCheck covers the readiness side. This one is the dashboard view: it
 * also reports migrations recorded as executed that no longer exist in the code, which points at
 * a deploy that went backwards.
 */
final readonly class DoctrineMigrationsCheck extends AbstractReportingCheck
{
    public function __construct(bool $enabled, private ?DependencyFactory $dependencyFactory = null)
    {
        parent::__construct($enabled);
    }

    public function identifier(): string
    {
        return 'system:doctrine_migrations';
    }

    public function label(): string
    {
        return 'Doctrine Migrations';
    }

    protected function examine(): Report
    {
        if ($this->dependencyFactory === null) {
            return Report::notAvailable('Doctrine migrations are not available (n/a)');
        }

        $calculator  = $this->dependencyFactory->getMigrationStatusCalculator();
        $pending     = count($calculator->getNewMigrations());
        $unavailable = count($calculator->getExecutedUnavailableMigrations());
        $data        = ['pending' => $pending, 'executed_unavailable' => $unavailable];

        if ($unavailable > 0) {
            return Report::failure('Migrations applied which are not available', $data);
        }

        if ($pending > 0) {
            return Report::warning(sprintf('%d migration%s pending', $pending, $pending === 1 ? ' is' : 's are'), $data);
        }

        return Report::ok('All migrations are applied', $data);
    }
}
