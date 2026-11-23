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

final readonly class DataObjectClassesCheck extends AbstractReportingCheck
{
    public function __construct(bool $enabled, private Connection $connection)
    {
        parent::__construct($enabled);
    }

    public function identifier(): string
    {
        return 'data:dataobject_classes';
    }

    public function label(): string
    {
        return 'DataObject Classes';
    }

    protected function examine(): Report
    {
        $count = (int)$this->connection->fetchOne('SELECT COUNT(*) FROM classes');

        return Report::ok(sprintf('There are %d DataObject classes in the system', $count), ['count' => $count]);
    }
}
