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

final readonly class MessengerMessageCountCheck extends AbstractMessengerCheck
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
        return 'maintenance:messenger_message_count';
    }

    public function label(): string
    {
        return 'Messenger Message Count';
    }

    protected function examine(): Report
    {
        [$failed, $params] = $this->failedQueueCondition();

        try {
            // only what is still waiting: delivered and dead messages say nothing about the consumer
            $count = (int)$this->connection->fetchOne(
                "SELECT COUNT(*) FROM messenger_messages WHERE delivered_at IS NULL AND NOT $failed",
                $params
            );
        } catch (Throwable $exception) {
            return $this->tableIsMissing($exception)
                ? Report::notAvailable('No messenger_messages table - Messenger is not using the Doctrine transport (n/a)')
                : Report::failure('Could not query the messenger_messages table');
        }

        $data = ['count' => $count];

        if ($this->failureThreshold !== null && $count >= $this->failureThreshold) {
            return Report::failure(
                sprintf('Messenger queue has %d messages (critical threshold: %d). Maintenance job may not be running.', $count, $this->failureThreshold),
                $data
            );
        }

        if ($this->warningThreshold !== null && $count >= $this->warningThreshold) {
            return Report::warning(sprintf('Messenger queue has %d messages (warning threshold: %d).', $count, $this->warningThreshold), $data);
        }

        return Report::ok(sprintf('Messenger queue has %d messages.', $count), $data);
    }
}
