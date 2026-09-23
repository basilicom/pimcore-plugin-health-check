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

use Basilicom\PimcorePluginHealthCheck\Exception\FilesystemNotWriteableException;

final readonly class FilesystemCheck implements CheckInterface
{
    private const string PAYLOAD = 'pimcore-health-check';

    public function __construct(
        private string $temporaryDirectory,
        private bool $enabled,
    ) {
    }

    public function check(): void
    {
        // unique per run, so concurrent monitoring requests cannot delete each other's probe
        $file = rtrim($this->temporaryDirectory, '/') . '/health-check-' . bin2hex(random_bytes(8)) . '.tmp';

        if (@file_put_contents($file, self::PAYLOAD) === false) {
            throw new FilesystemNotWriteableException('Pimcore temporary directory is not writeable.');
        }

        try {
            if (@file_get_contents($file) !== self::PAYLOAD) {
                throw new FilesystemNotWriteableException('Pimcore temporary directory returned unexpected content.');
            }
        } finally {
            @unlink($file);
        }
    }

    public function isActive(): bool
    {
        return $this->enabled;
    }
}
