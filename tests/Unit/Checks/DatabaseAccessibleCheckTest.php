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

namespace Basilicom\PimcorePluginHealthCheck\Tests\Unit\Checks;

use Basilicom\PimcorePluginHealthCheck\Checks\DatabaseAccessibleCheck;
use Basilicom\PimcorePluginHealthCheck\Exception\DatabaseNotAccessibleException;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DatabaseAccessibleCheckTest extends TestCase
{
    #[Test]
    public function check_doesNotThrowWhenUsersExist(): void
    {
        // prepare
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn('3');

        $check = new DatabaseAccessibleCheck($connection, true);

        // test
        $check->check();

        // verify
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function check_throwsWhenUsersTableIsEmpty(): void
    {
        // prepare
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn('0');

        $check = new DatabaseAccessibleCheck($connection, true);

        // test / verify
        $this->expectException(DatabaseNotAccessibleException::class);
        $check->check();
    }

    #[Test]
    public function check_wrapsTheOriginalExceptionWithoutLeakingItsMessage(): void
    {
        // prepare
        $connection    = $this->createMock(Connection::class);
        $dbalException = new RuntimeException('secret dsn user=root password=hunter2');
        $connection->method('fetchOne')->willThrowException($dbalException);

        $check = new DatabaseAccessibleCheck($connection, true);

        // test
        try {
            $check->check();
            $this->fail('Expected exception was not thrown.');
        } catch (DatabaseNotAccessibleException $exception) {
            // verify
            $this->assertSame($dbalException, $exception->getPrevious());
            $this->assertStringNotContainsString('hunter2', $exception->getMessage());
            $this->assertStringNotContainsString('secret dsn', $exception->getMessage());
        }
    }

    #[Test]
    public function isActive_reflectsTheInjectedFlag(): void
    {
        // prepare
        $connection = $this->createMock(Connection::class);

        // test / verify
        $this->assertTrue((new DatabaseAccessibleCheck($connection, true))->isActive());
        $this->assertFalse((new DatabaseAccessibleCheck($connection, false))->isActive());
    }
}
