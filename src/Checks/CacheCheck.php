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

namespace Basilicom\PimcorePluginHealthCheck\Checks;

use Basilicom\PimcorePluginHealthCheck\Exception\CacheNotWriteableException;
use Psr\Cache\CacheItemPoolInterface;
use Throwable;

final readonly class CacheCheck implements CheckInterface
{
    private const string PAYLOAD = 'pimcore-health-check';

    public function __construct(
        private CacheItemPoolInterface $cache,
        private bool $enabled,
    ) {
    }

    public function check(): void
    {
        // unique per run, so concurrent monitoring requests cannot delete each other's probe
        $key = 'basilicom_health_check_' . bin2hex(random_bytes(8));

        try {
            $item = $this->cache->getItem($key);
            $item->set(self::PAYLOAD);
            $item->expiresAfter(60);
            $this->cache->save($item);

            $stored = $this->cache->getItem($key)->get();
        } catch (Throwable $exception) {
            throw new CacheNotWriteableException('Pimcore cache is not writeable.', previous: $exception);
        } finally {
            try {
                $this->cache->deleteItem($key);
            } catch (Throwable) {
                // throwing here would mask the real failure; the probe expires on its own
            }
        }

        if ($stored !== self::PAYLOAD) {
            throw new CacheNotWriteableException('Pimcore cache returned unexpected content.');
        }
    }

    public function isActive(): bool
    {
        return $this->enabled;
    }
}
