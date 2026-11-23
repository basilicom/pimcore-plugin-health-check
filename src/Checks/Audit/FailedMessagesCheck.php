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
use Doctrine\DBAL\Connection;
use Throwable;

final readonly class FailedMessagesCheck extends AbstractMessengerCheck
{
    /** @param list<string> $failedQueueNames */
    public function __construct(
        bool $enabled,
        Connection $connection,
        array $failedQueueNames,
        private int|float|null $warningThreshold,
        private int|float|null $failureThreshold,
    ) {
        parent::__construct($enabled, $connection, $failedQueueNames);
    }

    public function identifier(): string
    {
        return 'maintenance:failed_messages';
    }

    public function label(): string
    {
        return 'Failed Messenger Messages';
    }

    protected function examine(): Report
    {
        [$failed, $params] = $this->failedQueueCondition();

        try {
            /** @var list<array{queue_name: string, cnt: string|int}> $rows */
            $rows = $this->connection->fetchAllAssociative(
                "SELECT queue_name, COUNT(*) AS cnt FROM messenger_messages WHERE $failed GROUP BY queue_name ORDER BY cnt DESC",
                $params
            );
        } catch (Throwable $exception) {
            return $this->tableIsMissing($exception)
                ? Report::notAvailable('No messenger_messages table - Messenger is not using the Doctrine transport (n/a)')
                : Report::failure('Could not query the messenger_messages table');
        }

        $byQueue = [];
        $total   = 0;

        foreach ($rows as $row) {
            $byQueue[(string)$row['queue_name']] = (int)$row['cnt'];
            $total += (int)$row['cnt'];
        }

        $listed = implode(', ', array_map(static fn (string $q, int $c): string => sprintf('%s (%d)', $q, $c), array_keys($byQueue), $byQueue));

        return Report::graded(
            $total,
            $this->warningThreshold,
            $this->failureThreshold,
            $total === 0 ? 'No messages in a failure transport.' : sprintf('%d messages in failure transports: %s', $total, $listed),
            ['total' => $total, 'by_queue' => $byQueue]
        );
    }
}
