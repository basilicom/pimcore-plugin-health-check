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

use Basilicom\PimcorePluginHealthCheck\Checks\DatabaseLatencyCheck;
use Basilicom\PimcorePluginHealthCheck\Exception\DatabaseNotAccessibleException;
use Basilicom\PimcorePluginHealthCheck\Exception\DatabaseTooSlowException;
use Basilicom\PimcorePluginHealthCheck\Severity;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DatabaseLatencyCheckTest extends TestCase
{
    #[Test]
    public function check_doesNotThrowWhenTheRoundTripStaysUnderTheThreshold(): void
    {
        // prepare
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(1);

        $check = new DatabaseLatencyCheck($connection, true, 5000);

        // test
        $check->check();

        // verify
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function check_wrapsAConnectionFailureWithoutLeakingItsMessage(): void
    {
        // prepare
        $connection    = $this->createMock(Connection::class);
        $dbalException = new RuntimeException('secret dsn user=root password=hunter2');
        $connection->method('fetchOne')->willThrowException($dbalException);

        $check = new DatabaseLatencyCheck($connection, true, 5000);

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
    public function check_throwsWhenTheRoundTripExceedsTheThreshold(): void
    {
        // prepare
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturnCallback(static function () {
            usleep(20_000);

            return 1;
        });

        $check = new DatabaseLatencyCheck($connection, true, 1);

        // test / verify
        $this->expectException(DatabaseTooSlowException::class);
        $check->check();
    }

    #[Test]
    public function check_theTooSlowMessageNamesTheMeasuredDurationAndTheThreshold(): void
    {
        // prepare
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturnCallback(static function () {
            usleep(20_000);

            return 1;
        });

        $check = new DatabaseLatencyCheck($connection, true, 1);

        // test
        try {
            $check->check();
            $this->fail('Expected exception was not thrown.');
        } catch (DatabaseTooSlowException $exception) {
            // verify
            $this->assertMatchesRegularExpression('/took \d+ ms/', $exception->getMessage());
            $this->assertStringContainsString('threshold is 1 ms', $exception->getMessage());
        }
    }

    #[Test]
    public function databaseTooSlowExceptionSeverityIsFailure(): void
    {
        // prepare
        $exception = new DatabaseTooSlowException('too slow');

        // test / verify
        $this->assertSame(Severity::Failure, $exception->severity());
    }

    #[Test]
    public function isActive_reflectsTheInjectedFlag(): void
    {
        // prepare
        $connection = $this->createMock(Connection::class);

        // test / verify
        $this->assertTrue((new DatabaseLatencyCheck($connection, true, 1000))->isActive());
        $this->assertFalse((new DatabaseLatencyCheck($connection, false, 1000))->isActive());
    }
}
