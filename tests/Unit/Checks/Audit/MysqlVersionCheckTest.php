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

namespace Basilicom\PimcorePluginHealthCheck\Tests\Unit\Checks\Audit;

use Basilicom\PimcorePluginHealthCheck\Checks\Audit\MysqlVersionCheck;
use Basilicom\PimcorePluginHealthCheck\Severity;
use Basilicom\PimcorePluginHealthCheck\Tests\Fixtures\StubEndOfLifeDateClient;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MysqlVersionCheckTest extends TestCase
{
    #[Test]
    public function inspect_configuredMode_comparesTheNumericPartAndReportsTheFullServerString(): void
    {
        // prepare
        $check = new MysqlVersionCheck(true, new StubEndOfLifeDateClient(null), $this->connection('10.11.18-MariaDB-0+deb12u1'), '10.5', '>=');

        // test
        $report = $check->inspect();

        // verify
        $this->assertSame(Severity::Ok, $report->severity);
        $this->assertSame('Current MySQL version is 10.11.18-MariaDB-0+deb12u1', $report->message);
        $this->assertSame('MariaDB', $report->data['engine']);
    }

    #[Test]
    public function inspect_dynamicMode_looksUpMysqlForAMysqlServerInsteadOfFailingOnAMariaDbNumber(): void
    {
        // prepare - the old default of ">= 10.5" made every MySQL 8 server critical
        $client = new StubEndOfLifeDateClient([['cycle' => '8.4', 'eol' => '2032-04-30'], ['cycle' => '8.0', 'eol' => '2026-04-30']]);
        $check  = new MysqlVersionCheck(true, $client, $this->connection('8.4.3'), null, '>=');

        // test
        $report = $check->inspect();

        // verify
        $this->assertSame(Severity::Ok, $report->severity);
        $this->assertSame('mysql', $report->data['product']);
        $this->assertSame('MySQL', $report->data['engine']);
    }

    #[Test]
    public function inspect_dynamicMode_looksUpMariaDbForAMariaDbServer(): void
    {
        // prepare
        $client = new StubEndOfLifeDateClient([['cycle' => '11.4', 'eol' => '2029-05-29'], ['cycle' => '10.11', 'eol' => '2028-02-16']]);
        $check  = new MysqlVersionCheck(true, $client, $this->connection('10.11.18-MariaDB'), null, '>=');

        // test
        $report = $check->inspect();

        // verify
        $this->assertSame(Severity::Warning, $report->severity);
        $this->assertSame('mariadb', $report->data['product']);
    }

    private function connection(string $version): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn($version);

        return $connection;
    }
}
