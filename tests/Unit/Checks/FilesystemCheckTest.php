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

namespace Basilicom\PimcorePluginHealthCheck\Tests\Unit\Checks;

use Basilicom\PimcorePluginHealthCheck\Checks\FilesystemCheck;
use Basilicom\PimcorePluginHealthCheck\Exception\FilesystemNotWriteableException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FilesystemCheckTest extends TestCase
{
    private string $writeableDirectory;
    private string $unwriteableDirectory;

    protected function setUp(): void
    {
        $this->writeableDirectory = sys_get_temp_dir() . '/health-check-writeable-' . bin2hex(random_bytes(8));
        mkdir($this->writeableDirectory);

        $this->unwriteableDirectory = sys_get_temp_dir() . '/health-check-unwriteable-' . bin2hex(random_bytes(8));
        mkdir($this->unwriteableDirectory);
        chmod($this->unwriteableDirectory, 0500);
    }

    protected function tearDown(): void
    {
        chmod($this->unwriteableDirectory, 0700);
        $this->removeDirectory($this->writeableDirectory);
        $this->removeDirectory($this->unwriteableDirectory);
    }

    #[Test]
    public function check_doesNotThrowAndCleansUpTheProbeFile(): void
    {
        // prepare
        $check = new FilesystemCheck($this->writeableDirectory, true);

        // test
        $check->check();

        // verify
        $this->assertSame([], glob($this->writeableDirectory . '/*.tmp'));
    }

    #[Test]
    public function check_throwsWhenTheDirectoryDoesNotExist(): void
    {
        // prepare
        $check = new FilesystemCheck($this->writeableDirectory . '/does-not-exist', true);

        // test / verify
        $this->expectException(FilesystemNotWriteableException::class);
        $check->check();
    }

    #[Test]
    public function check_throwsWhenTheDirectoryIsNotWriteable(): void
    {
        if (posix_geteuid() === 0) {
            $this->markTestSkipped('Running as root ignores filesystem permissions.');
        }

        // prepare
        $check = new FilesystemCheck($this->unwriteableDirectory, true);

        // test / verify
        $this->expectException(FilesystemNotWriteableException::class);
        $check->check();
    }

    #[Test]
    public function isActive_reflectsTheInjectedFlag(): void
    {
        // test / verify
        $this->assertTrue((new FilesystemCheck($this->writeableDirectory, true))->isActive());
        $this->assertFalse((new FilesystemCheck($this->writeableDirectory, false))->isActive());
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (glob($directory . '/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($directory);
    }
}
