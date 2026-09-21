<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

use Doctrine\DBAL\Connection;
use Throwable;

/**
 * Counts messages that ended up in a Symfony Messenger failure transport (PF-88 metric 18).
 *
 * Pimcore 11/12 runs its maintenance tasks through Messenger; a task that keeps failing is
 * retried and finally moved to the configured failure transport (`failed`, or a transport
 * named `<name>_failed`), so this is where failed jobs become visible in the database.
 */
final class FailedMessagesCheck extends AbstractCheck
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config, private readonly Connection $connection)
    {
        parent::__construct($config);
    }

    public function getIdentifier(): string
    {
        return 'maintenance:failed_messages';
    }

    public function getLabel(): string
    {
        return 'Failed Messenger Messages';
    }

    protected function doRun(): CheckResult
    {
        try {
            /** @var list<array{queue_name: string, cnt: string|int}> $rows */
            $rows = $this->connection->fetchAllAssociative(
                "SELECT queue_name, COUNT(*) AS cnt
                 FROM messenger_messages
                 WHERE queue_name = 'failed' OR queue_name LIKE '%\\_failed'
                 GROUP BY queue_name
                 ORDER BY cnt DESC",
            );
        } catch (Throwable $throwable) {
            return $this->na('Could not query messenger_messages table: ' . $throwable->getMessage());
        }

        $byQueue = [];
        $total = 0;

        foreach ($rows as $row) {
            $byQueue[(string) $row['queue_name']] = (int) $row['cnt'];
            $total += (int) $row['cnt'];
        }

        $formatted = array_map(
            static fn (string $queue, int $count): string => sprintf('%s (%d)', $queue, $count),
            array_keys($byQueue),
            $byQueue,
        );

        return $this->thresholdResult(
            $total,
            $this->threshold('warning_threshold'),
            $this->threshold('critical_threshold'),
            $total === 0
                ? 'No messages in a failure transport.'
                : sprintf('%d messages in failure transports: %s', $total, implode(', ', $formatted)),
            ['total' => $total, 'by_queue' => $byQueue],
        );
    }
}
