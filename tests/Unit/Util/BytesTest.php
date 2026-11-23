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

namespace Basilicom\PimcorePluginHealthCheck\Tests\Unit\Util;

use Basilicom\PimcorePluginHealthCheck\Util\Bytes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BytesTest extends TestCase
{
    #[Test]
    #[DataProvider('values')]
    public function format_isHumanReadable(int|float $bytes, string $expected): void
    {
        // test / verify
        $this->assertSame($expected, Bytes::format($bytes));
    }

    /** @return iterable<string, array{int|float, string}> */
    public static function values(): iterable
    {
        yield 'zero' => [0, '0 B'];
        yield 'bytes keep trailing zeros' => [100, '100 B'];
        yield 'one kilobyte' => [1024, '1 KB'];
        yield 'kilobytes' => [1536, '1.5 KB'];
        yield 'megabytes' => [943718400, '900 MB'];
        yield 'gigabytes' => [8150000000, '7.59 GB'];
        yield 'terabytes' => [1099511627776, '1 TB'];
        yield 'negative is clamped' => [-5, '0 B'];
    }
}
