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

use Basilicom\PimcorePluginHealthCheck\Checks\CacheCheck;
use Basilicom\PimcorePluginHealthCheck\Exception\CacheNotWriteableException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use RuntimeException;

class CacheCheckTest extends TestCase
{
    #[Test]
    public function check_doesNotThrowWhenTheStoredValueRoundTrips(): void
    {
        // prepare
        $item = $this->createMock(CacheItemInterface::class);
        $item->expects($this->once())->method('set')->with('pimcore-health-check')->willReturnSelf();
        $item->expects($this->once())->method('expiresAfter')->with(60)->willReturnSelf();
        $item->method('get')->willReturn('pimcore-health-check');

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($item);
        $cache->expects($this->once())->method('save')->with($item)->willReturn(true);
        $cache->expects($this->once())->method('deleteItem')->willReturn(true);

        $check = new CacheCheck($cache, true);

        // test
        $check->check();

        // verify
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function check_throwsWhenTheReadBackValueDiffers(): void
    {
        // prepare
        $item = $this->createMock(CacheItemInterface::class);
        $item->method('set')->willReturnSelf();
        $item->method('expiresAfter')->willReturnSelf();
        $item->method('get')->willReturn('something-else');

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($item);
        $cache->method('save')->willReturn(true);
        $cache->expects($this->once())->method('deleteItem')->willReturn(true);

        $check = new CacheCheck($cache, true);

        // test / verify
        $this->expectException(CacheNotWriteableException::class);
        $check->check();
    }

    #[Test]
    public function check_stillDeletesTheProbeWhenGetItemThrows(): void
    {
        // prepare
        $cacheException = new RuntimeException('cache backend unreachable');

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willThrowException($cacheException);
        $cache->expects($this->once())->method('deleteItem')->willReturn(true);

        $check = new CacheCheck($cache, true);

        // test
        try {
            $check->check();
            $this->fail('Expected exception was not thrown.');
        } catch (CacheNotWriteableException $exception) {
            // verify
            $this->assertSame($cacheException, $exception->getPrevious());
        }
    }

    #[Test]
    public function isActive_reflectsTheInjectedFlag(): void
    {
        // prepare
        $cache = $this->createMock(CacheItemPoolInterface::class);

        // test / verify
        $this->assertTrue((new CacheCheck($cache, true))->isActive());
        $this->assertFalse((new CacheCheck($cache, false))->isActive());
    }
}
