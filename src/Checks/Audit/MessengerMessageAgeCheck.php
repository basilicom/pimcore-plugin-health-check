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

final readonly class MessengerMessageAgeCheck extends AbstractMessengerCheck
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
        return 'maintenance:messenger_message_age';
    }

    public function label(): string
    {
        return 'Messenger Message Age';
    }

    protected function examine(): Report
    {
        [$failed, $params] = $this->failedQueueCondition();

        try {
            // a message parked in a failure queue would otherwise age forever and keep this red
            $oldest = $this->connection->fetchOne(
                "SELECT TIMESTAMPDIFF(MINUTE, MIN(created_at), NOW()) FROM messenger_messages WHERE delivered_at IS NULL AND NOT $failed",
                $params
            );
        } catch (Throwable $exception) {
            return $this->tableIsMissing($exception)
                ? Report::notAvailable('No messenger_messages table - Messenger is not using the Doctrine transport (n/a)')
                : Report::failure('Could not query the messenger_messages table');
        }

        if ($oldest === false || $oldest === null) {
            return Report::ok('No messages in the messenger queue.', ['oldest_minutes' => 0]);
        }

        $minutes = (int)$oldest;
        $data    = ['oldest_minutes' => $minutes];

        if ($this->failureThreshold !== null && $minutes >= $this->failureThreshold) {
            return Report::failure(
                sprintf('Oldest messenger message is %d minutes old (critical threshold: %d min). Maintenance job may be stuck.', $minutes, $this->failureThreshold),
                $data
            );
        }

        if ($this->warningThreshold !== null && $minutes >= $this->warningThreshold) {
            return Report::warning(sprintf('Oldest messenger message is %d minutes old (warning threshold: %d min).', $minutes, $this->warningThreshold), $data);
        }

        return Report::ok(sprintf('Oldest messenger message is %d minutes old.', $minutes), $data);
    }
}
