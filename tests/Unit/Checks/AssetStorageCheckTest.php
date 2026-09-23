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

use Basilicom\PimcorePluginHealthCheck\Checks\AssetStorageCheck;
use Basilicom\PimcorePluginHealthCheck\Exception\AssetStorageNotWriteableException;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pimcore\Tool\Storage;
use RuntimeException;

class AssetStorageCheckTest extends TestCase
{
    #[Test]
    public function check_doesNotThrowAndDeletesTheProbeWhenTheStoredValueRoundTrips(): void
    {
        // prepare
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects($this->once())->method('write')->with($this->isType('string'), 'pimcore-health-check');
        $filesystem->method('read')->willReturn('pimcore-health-check');
        $filesystem->expects($this->once())->method('delete');

        $storage = $this->createMock(Storage::class);
        $storage->method('getStorage')->with('asset')->willReturn($filesystem);

        $check = new AssetStorageCheck($storage, true);

        // test
        $check->check();

        // verify
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function check_throwsWhenTheReadBackValueDiffers(): void
    {
        // prepare
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->method('write');
        $filesystem->method('read')->willReturn('something-else');

        $storage = $this->createMock(Storage::class);
        $storage->method('getStorage')->willReturn($filesystem);

        $check = new AssetStorageCheck($storage, true);

        // test / verify
        $this->expectException(AssetStorageNotWriteableException::class);
        $check->check();
    }

    #[Test]
    public function check_wrapsAWriteFailureWithoutLeakingItsMessage(): void
    {
        // prepare
        $storageException = new RuntimeException('S3 credentials rejected for bucket assets-prod');

        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->method('write')->willThrowException($storageException);
        $filesystem->expects($this->once())->method('delete');

        $storage = $this->createMock(Storage::class);
        $storage->method('getStorage')->willReturn($filesystem);

        $check = new AssetStorageCheck($storage, true);

        // test
        try {
            $check->check();
            $this->fail('Expected exception was not thrown.');
        } catch (AssetStorageNotWriteableException $exception) {
            // verify
            $this->assertSame($storageException, $exception->getPrevious());
            $this->assertStringNotContainsString('S3 credentials rejected', $exception->getMessage());
            $this->assertStringNotContainsString('assets-prod', $exception->getMessage());
        }
    }

    #[Test]
    public function check_throwsWithoutAFatalErrorWhenGetStorageFails(): void
    {
        // prepare
        $storage = $this->createMock(Storage::class);
        $storage->method('getStorage')->willThrowException(new RuntimeException('storage locator misconfigured'));

        $check = new AssetStorageCheck($storage, true);

        // test / verify
        $this->expectException(AssetStorageNotWriteableException::class);
        $check->check();
    }

    #[Test]
    public function check_aFailingDeleteDoesNotMaskAnOtherwiseSuccessfulCheck(): void
    {
        // prepare
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->method('write');
        $filesystem->method('read')->willReturn('pimcore-health-check');
        $filesystem->method('delete')->willThrowException(new RuntimeException('delete failed'));

        $storage = $this->createMock(Storage::class);
        $storage->method('getStorage')->willReturn($filesystem);

        $check = new AssetStorageCheck($storage, true);

        // test
        $check->check();

        // verify
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function isActive_reflectsTheInjectedFlag(): void
    {
        // prepare
        $storage = $this->createMock(Storage::class);

        // test / verify
        $this->assertTrue((new AssetStorageCheck($storage, true))->isActive());
        $this->assertFalse((new AssetStorageCheck($storage, false))->isActive());
    }
}
