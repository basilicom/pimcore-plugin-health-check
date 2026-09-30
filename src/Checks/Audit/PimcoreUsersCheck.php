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

final readonly class PimcoreUsersCheck extends AbstractReportingCheck
{
    public function __construct(bool $enabled, private Connection $connection)
    {
        parent::__construct($enabled);
    }

    public function identifier(): string
    {
        return 'pimcore:users';
    }

    public function label(): string
    {
        return 'Pimcore Users';
    }

    protected function examine(): Report
    {
        $total  = (int)$this->connection->fetchOne("SELECT COUNT(*) FROM users WHERE `type` = 'user'");
        $active = (int)$this->connection->fetchOne("SELECT COUNT(*) FROM users WHERE `type` = 'user' AND `active` = 1");

        return Report::ok(sprintf('There are %d Pimcore users in the system', $total), ['count' => $total, 'active' => $active]);
    }
}
