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

use Basilicom\PimcorePluginHealthCheck\Exception\RobotsTxtNotAvailableException;

final readonly class RobotsTxtCheck implements CheckInterface
{
    public function __construct(
        private string $webRootDirectory,
        private bool $enabled,
    ) {
    }

    public function check(): void
    {
        $file = rtrim($this->webRootDirectory, '/') . '/robots.txt';

        if (!is_file($file) || !is_readable($file)) {
            throw new RobotsTxtNotAvailableException('robots.txt is not available or not readable.');
        }

        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            throw new RobotsTxtNotAvailableException('robots.txt is not available or not readable.');
        }

        foreach ($lines as $line) {
            // robots.txt lines carry their line break and arbitrary spacing; strip all of it before comparing
            if (strtolower((string)preg_replace('/\s+/', '', $line)) === 'disallow:/') {
                throw new RobotsTxtNotAvailableException('robots.txt disallows the whole domain.');
            }
        }
    }

    public function isActive(): bool
    {
        return $this->enabled;
    }
}
