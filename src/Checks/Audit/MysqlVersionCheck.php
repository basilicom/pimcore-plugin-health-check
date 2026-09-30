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

use Basilicom\PimcorePluginHealthCheck\Checks\Report;
use Basilicom\PimcorePluginHealthCheck\Services\EndOfLifeDateClientInterface;
use Doctrine\DBAL\Connection;

/**
 * MySQL and MariaDB number their releases differently (8.0 vs 10.11), so there is no version
 * that is right for both. Without a configured version the engine is detected and looked up.
 */
final readonly class MysqlVersionCheck extends AbstractVersionCheck
{
    public function __construct(
        bool $enabled,
        EndOfLifeDateClientInterface $endOfLife,
        private Connection $connection,
        ?string $requiredVersion,
        string $operator,
    ) {
        parent::__construct($enabled, $endOfLife, $requiredVersion, $operator);
    }

    public function identifier(): string
    {
        return 'system:mysql_version';
    }

    public function label(): string
    {
        return 'MySQL Version';
    }

    protected function examine(): Report
    {
        $reported  = (string)$this->connection->fetchOne('SELECT VERSION()');
        $numeric   = preg_match('/^\d+(\.\d+)*/', $reported, $matches) === 1 ? $matches[0] : $reported;
        $isMariaDb = stripos($reported, 'mariadb') !== false;

        return $this->compare(
            'MySQL',
            $isMariaDb ? 'mariadb' : 'mysql',
            $numeric,
            $reported,
            ['reported' => $reported, 'engine' => $isMariaDb ? 'MariaDB' : 'MySQL']
        );
    }
}
