<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Util;

/**
 * @internal
 */
final class Bytes
{
    private const UNITS = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

    public static function format(int|float $bytes, int $precision = 2): string
    {
        $bytes = max(0, (float) $bytes);
        $index = 0;

        while ($bytes >= 1024 && $index < count(self::UNITS) - 1) {
            $bytes /= 1024;
            ++$index;
        }

        $formatted = number_format($bytes, $index === 0 ? 0 : $precision, '.', '');

        if (str_contains($formatted, '.')) {
            $formatted = rtrim(rtrim($formatted, '0'), '.');
        }

        return $formatted . ' ' . self::UNITS[$index];
    }
}
