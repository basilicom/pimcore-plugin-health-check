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

use Basilicom\PimcorePluginHealthCheck\Checks\Audit\FailedMessagesCheck;
use Basilicom\PimcorePluginHealthCheck\Checks\Audit\MessengerMessageAgeCheck;
use Basilicom\PimcorePluginHealthCheck\Checks\Audit\MessengerMessageCountCheck;
use Basilicom\PimcorePluginHealthCheck\Severity;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class MessengerChecksTest extends TestCase
{
    #[Test]
    public function count_usesTheDecorUnionWordingPerLevel(): void
    {
        // test / verify
        $this->assertSame('Messenger queue has 1 messages.', (new MessengerMessageCountCheck(true, $this->fetchOne('1'), ['failed'], 500, 1000))->inspect()->message);

        $warning = (new MessengerMessageCountCheck(true, $this->fetchOne('600'), ['failed'], 500, 1000))->inspect();
        $this->assertSame(Severity::Warning, $warning->severity);
        $this->assertSame('Messenger queue has 600 messages (warning threshold: 500).', $warning->message);

        $failure = (new MessengerMessageCountCheck(true, $this->fetchOne('1200'), ['failed'], 500, 1000))->inspect();
        $this->assertSame(Severity::Failure, $failure->severity);
        $this->assertStringContainsString('critical threshold: 1000', $failure->message);
    }

    #[Test]
    public function count_excludesFailedQueuesAndDeliveredMessagesInTheQuery(): void
    {
        // prepare
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchOne')
            ->with(
                $this->logicalAnd(
                    $this->stringContains('delivered_at IS NULL'),
                    $this->stringContains("queue_name LIKE '%\\\\_failed'"),
                    $this->stringContains('queue_name IN (?, ?)')
                ),
                ['failed', 'dead']
            )
            ->willReturn('0');

        // test
        (new MessengerMessageCountCheck(true, $connection, ['failed', 'dead'], 500, 1000))->inspect();

        // verify - the expectation above
    }

    #[Test]
    public function count_isNotAvailableWhenTheTableDoesNotExist(): void
    {
        // prepare
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willThrowException(new RuntimeException("Table 'pimcore.messenger_messages' doesn't exist"));

        // test / verify
        $this->assertSame(Severity::NotAvailable, (new MessengerMessageCountCheck(true, $connection, ['failed'], 500, 1000))->inspect()->severity);
    }

    #[Test]
    public function age_isOkForAnEmptyQueueAndGradesTheOldestMessage(): void
    {
        // test / verify
        $this->assertSame('No messages in the messenger queue.', (new MessengerMessageAgeCheck(true, $this->fetchOne(null), ['failed'], 60, 180))->inspect()->message);

        $warning = (new MessengerMessageAgeCheck(true, $this->fetchOne('139'), ['failed'], 60, 180))->inspect();
        $this->assertSame(Severity::Warning, $warning->severity);
        $this->assertSame('Oldest messenger message is 139 minutes old (warning threshold: 60 min).', $warning->message);

        $this->assertSame(Severity::Failure, (new MessengerMessageAgeCheck(true, $this->fetchOne('200'), ['failed'], 60, 180))->inspect()->severity);
        $this->assertSame(Severity::Ok, (new MessengerMessageAgeCheck(true, $this->fetchOne('3'), ['failed'], 60, 180))->inspect()->severity);
    }

    #[Test]
    public function failed_listsTheQueuesAndGrades(): void
    {
        // prepare
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([['queue_name' => 'failed', 'cnt' => '3'], ['queue_name' => 'pimcore_cdn_purge_failed', 'cnt' => '1']]);

        // test
        $report = (new FailedMessagesCheck(true, $connection, ['failed'], 1, 50))->inspect();

        // verify
        $this->assertSame(Severity::Warning, $report->severity);
        $this->assertSame('4 messages in failure transports: failed (3), pimcore_cdn_purge_failed (1)', $report->message);
    }

    #[Test]
    public function failed_isOkWhenNothingIsParked(): void
    {
        // prepare
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);

        // test / verify
        $this->assertSame('No messages in a failure transport.', (new FailedMessagesCheck(true, $connection, ['failed'], 1, 50))->inspect()->message);
    }

    private function fetchOne(mixed $value): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn($value);

        return $connection;
    }
}
