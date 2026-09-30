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

namespace Basilicom\PimcorePluginHealthCheck\Audit;

use JsonException;
use Pimcore\Model\Tool\SettingsStore;
use Throwable;

/**
 * Persists the run in Pimcore's settings store. It lives in the database, so every node of a
 * multi-server setup reads the same result, and a cache clear does not empty the dashboard.
 */
final readonly class SettingsStoreRunStore implements RunStoreInterface
{
    private const string SCOPE = 'pimcore_plugin_health_check';
    private const string ID    = 'audit_run';

    public function save(StoredRun $run): void
    {
        SettingsStore::set(
            self::ID,
            json_encode($run->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            SettingsStore::TYPE_STRING,
            self::SCOPE
        );
    }

    public function load(): ?StoredRun
    {
        try {
            $entry = SettingsStore::get(self::ID, self::SCOPE);
        } catch (Throwable) {
            return null;
        }

        if ($entry === null) {
            return null;
        }

        try {
            $data = json_decode((string)$entry->getData(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($data) ? StoredRun::fromArray($data) : null;
    }
}
