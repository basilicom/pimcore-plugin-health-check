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

namespace Basilicom\PimcorePluginHealthCheck\Util;

use Symfony\Component\Process\Process;
use Throwable;

/**
 * Sizes a directory tree with `du`, bounded by a timeout. There is deliberately no PHP fallback:
 * a recursive scan of a 50 GB project has no upper bound, and "n/a" is the honest answer when
 * `du` cannot finish in time.
 */
final class DirectorySize
{
    /** @return int|null bytes, or null when the directory is missing or `du` did not finish */
    public static function measure(string $path, int $timeoutSeconds): ?int
    {
        if (!is_dir($path) || $timeoutSeconds <= 0) {
            return null;
        }

        try {
            $process = new Process(['du', '-sk', $path], null, null, null, (float)$timeoutSeconds);
            $process->run();

            if (!$process->isSuccessful() || preg_match('/^(\d+)/', trim($process->getOutput()), $matches) !== 1) {
                return null;
            }

            return (int)$matches[1] * 1024;
        } catch (Throwable) {
            return null;
        }
    }
}
