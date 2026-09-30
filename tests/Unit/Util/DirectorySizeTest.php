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

use Basilicom\PimcorePluginHealthCheck\Util\DirectorySize;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DirectorySizeTest extends TestCase
{
    #[Test]
    public function measure_returnsNullForAMissingDirectory(): void
    {
        // test / verify
        $this->assertNull(DirectorySize::measure(sys_get_temp_dir() . '/does-not-exist-' . bin2hex(random_bytes(6)), 5));
    }

    #[Test]
    public function measure_returnsNullWithoutATimeBudgetInsteadOfScanningForever(): void
    {
        // test / verify
        $this->assertNull(DirectorySize::measure(sys_get_temp_dir(), 0));
    }

    #[Test]
    public function measure_sizesADirectoryWithDu(): void
    {
        // prepare
        $directory = sys_get_temp_dir() . '/health-check-size-' . bin2hex(random_bytes(6));
        mkdir($directory);
        file_put_contents($directory . '/a.bin', str_repeat('x', 10_000));

        // test
        $size = DirectorySize::measure($directory, 10);

        // verify
        unlink($directory . '/a.bin');
        rmdir($directory);
        if ($size === null) {
            $this->markTestSkipped('du is not available on this platform');
        }
        $this->assertGreaterThanOrEqual(4096, $size);
    }
}
