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

use Basilicom\PimcorePluginHealthCheck\Exception\AssetStorageNotWriteableException;
use League\Flysystem\FilesystemOperator;
use Pimcore\Tool\Storage;
use Throwable;

/**
 * Asset storage is usually S3 or a network mount, so it fails independently of the local disk.
 */
final readonly class AssetStorageCheck implements CheckInterface
{
    private const string STORAGE_NAME = 'asset';
    private const string PAYLOAD      = 'pimcore-health-check';

    public function __construct(
        private Storage $storage,
        private bool $enabled,
    ) {
    }

    public function check(): void
    {
        $path    = 'health-check/' . bin2hex(random_bytes(8)) . '.tmp';
        $storage = null;

        try {
            $storage = $this->storage->getStorage(self::STORAGE_NAME);
            $storage->write($path, self::PAYLOAD);
            $stored = $storage->read($path);
        } catch (Throwable $exception) {
            throw new AssetStorageNotWriteableException('Pimcore asset storage is not writeable.', previous: $exception);
        } finally {
            $this->discard($storage, $path);
        }

        if ($stored !== self::PAYLOAD) {
            throw new AssetStorageNotWriteableException('Pimcore asset storage returned unexpected content.');
        }
    }

    public function isActive(): bool
    {
        return $this->enabled;
    }

    private function discard(?FilesystemOperator $storage, string $path): void
    {
        try {
            $storage?->delete($path);
        } catch (Throwable) {
            // throwing here would mask the real failure
        }
    }
}
