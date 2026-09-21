<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

use Doctrine\DBAL\Connection;
use Throwable;

final class MessengerMessageCountCheck extends AbstractCheck
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
        return 'maintenance:messenger_message_count';
    }

    public function getLabel(): string
    {
        return 'Messenger Message Count';
    }

    protected function doRun(): CheckResult
    {
        try {
            $count = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM messenger_messages');
        } catch (Throwable $throwable) {
            return $this->na('Could not query messenger_messages table: ' . $throwable->getMessage());
        }

        $data = ['count' => $count];
        $warningThreshold = (int) $this->option('warning_threshold', 500);
        $criticalThreshold = (int) $this->option('critical_threshold', 1000);

        if ($count >= $criticalThreshold) {
            return $this->critical(
                sprintf(
                    'Messenger queue has %d messages (critical threshold: %d). Maintenance job may not be running.',
                    $count,
                    $criticalThreshold,
                ),
                $data,
            );
        }

        if ($count >= $warningThreshold) {
            return $this->warning(
                sprintf('Messenger queue has %d messages (warning threshold: %d).', $count, $warningThreshold),
                $data,
            );
        }

        return $this->ok(sprintf('Messenger queue has %d messages.', $count), $data);
    }
}
