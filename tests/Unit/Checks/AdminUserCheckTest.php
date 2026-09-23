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

use Basilicom\PimcorePluginHealthCheck\Checks\AdminUserCheck;
use Basilicom\PimcorePluginHealthCheck\Exception\AdminUserActiveException;
use Basilicom\PimcorePluginHealthCheck\Exception\DatabaseNotAccessibleException;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class AdminUserCheckTest extends TestCase
{
    #[Test]
    public function check_doesNotThrowWhenNoActiveAdminExists(): void
    {
        // prepare
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(false);

        $check = new AdminUserCheck($connection, true, 'admin');

        // test
        $check->check();

        // verify
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function check_throwsWhenTheDefaultAdminIsActive(): void
    {
        // prepare
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(1);

        $check = new AdminUserCheck($connection, true, 'admin');

        // test / verify
        $this->expectException(AdminUserActiveException::class);
        $check->check();
    }

    #[Test]
    public function check_passesTheConfiguredUserNameAsABindParameter(): void
    {
        // prepare
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->with($this->anything(), ['custom-admin'])
            ->willReturn(false);

        $check = new AdminUserCheck($connection, true, 'custom-admin');

        // test
        $check->check();

        // verify
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function check_theActiveExceptionMessageNamesTheConfiguredUserName(): void
    {
        // prepare
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(1);

        $check = new AdminUserCheck($connection, true, 'custom-admin');

        // test
        try {
            $check->check();
            $this->fail('Expected exception was not thrown.');
        } catch (AdminUserActiveException $exception) {
            // verify
            $this->assertStringContainsString('custom-admin', $exception->getMessage());
        }
    }

    #[Test]
    public function check_wrapsAConnectionFailureWithThePreviousException(): void
    {
        // prepare
        $connection    = $this->createMock(Connection::class);
        $dbalException = new RuntimeException('secret dsn user=root password=hunter2');
        $connection->method('fetchOne')->willThrowException($dbalException);

        $check = new AdminUserCheck($connection, true, 'admin');

        // test
        try {
            $check->check();
            $this->fail('Expected exception was not thrown.');
        } catch (DatabaseNotAccessibleException $exception) {
            // verify
            $this->assertSame($dbalException, $exception->getPrevious());
        }
    }

    #[Test]
    public function isActive_reflectsTheInjectedFlag(): void
    {
        // prepare
        $connection = $this->createMock(Connection::class);

        // test / verify
        $this->assertTrue((new AdminUserCheck($connection, true, 'admin'))->isActive());
        $this->assertFalse((new AdminUserCheck($connection, false, 'admin'))->isActive());
    }
}
