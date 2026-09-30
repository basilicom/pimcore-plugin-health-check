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

use Basilicom\PimcorePluginHealthCheck\Checks\AbstractReportingCheck;
use Doctrine\DBAL\Connection;

/**
 * Pimcore routes its maintenance through Symfony Messenger's Doctrine transport, so the
 * `messenger_messages` table is where a stuck maintenance shows. The table appears on first use,
 * and a failure transport is named per project - both are configuration, not failures.
 */
abstract readonly class AbstractMessengerCheck extends AbstractReportingCheck
{
    /** @param list<string> $failedQueueNames queue names that hold dead messages, `*_failed` always counts */
    public function __construct(
        bool $enabled,
        protected Connection $connection,
        protected array $failedQueueNames,
    ) {
        parent::__construct($enabled);
    }

    /** @return array{0: string, 1: list<string>} SQL fragment matching a failure queue and its parameters */
    protected function failedQueueCondition(): array
    {
        $names = array_values(array_filter(array_map('strval', $this->failedQueueNames), static fn (string $n): bool => $n !== ''));
        $sql   = "queue_name LIKE '%\\\\_failed'";

        if ($names !== []) {
            $sql .= ' OR queue_name IN (' . implode(', ', array_fill(0, count($names), '?')) . ')';
        }

        return ['(' . $sql . ')', $names];
    }

    protected function tableIsMissing(\Throwable $exception): bool
    {
        return str_contains(strtolower($exception->getMessage()), 'messenger_messages');
    }
}
