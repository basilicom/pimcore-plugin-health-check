<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Util;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Calculates the size of a directory tree.
 *
 * Uses `du` when available because it is an order of magnitude faster than iterating
 * the tree in PHP, and falls back to a recursive scan otherwise.
 *
 * @internal
 */
final class DirectorySize
{
    public static function calculate(string $path, int $timeout = 30): ?int
    {
        if (!is_dir($path)) {
            return null;
        }

        return self::viaDu($path, $timeout) ?? self::viaIterator($path);
    }

    private static function viaDu(string $path, int $timeout): ?int
    {
        if (!class_exists(Process::class) || stripos(PHP_OS_FAMILY, 'Windows') === 0) {
            return null;
        }

        try {
            $process = new Process(['du', '-sk', $path], null, null, null, $timeout);
            $process->run();

            if (!$process->isSuccessful()) {
                return null;
            }

            if (preg_match('/^(\d+)/', trim($process->getOutput()), $matches) !== 1) {
                return null;
            }

            return ((int) $matches[1]) * 1024;
        } catch (Throwable) {
            return null;
        }
    }

    private static function viaIterator(string $path): ?int
    {
        try {
            $size = 0;
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY,
                RecursiveIteratorIterator::CATCH_GET_CHILD,
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && !$file->isLink()) {
                    $size += $file->getSize();
                }
            }

            return $size;
        } catch (Throwable) {
            return null;
        }
    }
}
