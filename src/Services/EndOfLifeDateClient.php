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

namespace Basilicom\PimcorePluginHealthCheck\Services;

use Psr\Cache\CacheItemPoolInterface;
use Throwable;

/**
 * Fetches https://endoflife.date/api/<product>.json through the Pimcore cache pool.
 *
 * A failed lookup is cached too, for a shorter time: without that an offline host would pay the
 * connect timeout on every run, and the audit runs on a schedule, not on demand.
 */
final readonly class EndOfLifeDateClient implements EndOfLifeDateClientInterface
{
    private const string BASE_URL = 'https://endoflife.date/api/';

    public function __construct(
        private CacheItemPoolInterface $cache,
        private bool $enabled,
        private int $timeoutSeconds,
        private int $cacheTtl,
        private int $failureCacheTtl,
    ) {
    }

    public function cycles(string $product): ?array
    {
        if (!$this->enabled || preg_match('/^[a-z0-9._-]+$/', $product) !== 1) {
            return null;
        }

        try {
            $item = $this->cache->getItem('basilicom_health_check_eol_' . str_replace('.', '_', $product));

            if ($item->isHit()) {
                $cached = $item->get();

                return is_array($cached) ? $cached : null;
            }

            $cycles = $this->fetch($product);
            $item->set($cycles ?? false);
            $item->expiresAfter($cycles === null ? $this->failureCacheTtl : $this->cacheTtl);
            $this->cache->save($item);

            return $cycles;
        } catch (Throwable) {
            return null;
        }
    }

    /** @return list<array<string, mixed>>|null */
    private function fetch(string $product): ?array
    {
        $context = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'timeout' => $this->timeoutSeconds,
                'header'  => "Accept: application/json\r\nUser-Agent: basilicom/pimcore-plugin-health-check\r\n",
            ],
        ]);

        $body = @file_get_contents(self::BASE_URL . $product . '.json', false, $context);

        if ($body === false) {
            return null;
        }

        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            return null;
        }

        return array_values(array_filter($decoded, 'is_array'));
    }
}
