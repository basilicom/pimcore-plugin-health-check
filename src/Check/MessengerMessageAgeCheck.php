<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

use Doctrine\DBAL\Connection;
use Throwable;

final class MessengerMessageAgeCheck extends AbstractCheck
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
        return 'maintenance:messenger_message_age';
    }

    public function getLabel(): string
    {
        return 'Messenger Message Age';
    }

    protected function doRun(): CheckResult
    {
        try {
            $oldest = $this->connection->fetchOne(
                'SELECT TIMESTAMPDIFF(MINUTE, MIN(created_at), NOW()) FROM messenger_messages',
            );
        } catch (Throwable $throwable) {
            return $this->na('Could not query messenger_messages table: ' . $throwable->getMessage());
        }

        if ($oldest === false || $oldest === null) {
            return $this->ok('No messages in the messenger queue.', ['oldest_minutes' => 0]);
        }

        $oldestMinutes = (int) $oldest;
        $data = ['oldest_minutes' => $oldestMinutes];
        $warningThreshold = (int) $this->option('warning_threshold', 60);
        $criticalThreshold = (int) $this->option('critical_threshold', 180);

        if ($oldestMinutes >= $criticalThreshold) {
            return $this->critical(
                sprintf(
                    'Oldest messenger message is %d minutes old (critical threshold: %d min). '
                    . 'Maintenance job may be stuck.',
                    $oldestMinutes,
                    $criticalThreshold,
                ),
                $data,
            );
        }

        if ($oldestMinutes >= $warningThreshold) {
            return $this->warning(
                sprintf(
                    'Oldest messenger message is %d minutes old (warning threshold: %d min).',
                    $oldestMinutes,
                    $warningThreshold,
                ),
                $data,
            );
        }

        return $this->ok(sprintf('Oldest messenger message is %d minutes old.', $oldestMinutes), $data);
    }
}
