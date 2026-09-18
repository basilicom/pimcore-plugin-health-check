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

use Basilicom\PimcorePluginHealthCheck\Checks\RobotsTxtCheck;
use Basilicom\PimcorePluginHealthCheck\Exception\RobotsTxtNotAvailableException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RobotsTxtCheckTest extends TestCase
{
    private string $webRoot;

    protected function setUp(): void
    {
        $this->webRoot = sys_get_temp_dir() . '/health-check-webroot-' . bin2hex(random_bytes(8));
        mkdir($this->webRoot);
    }

    protected function tearDown(): void
    {
        if (is_file($this->webRoot . '/robots.txt')) {
            chmod($this->webRoot . '/robots.txt', 0644);
            unlink($this->webRoot . '/robots.txt');
        }
        rmdir($this->webRoot);
    }

    public static function disallowsWholeDomainProvider(): array
    {
        return [
            'trailing newline'    => ["User-agent: *\nDisallow: /\n"],
            'CRLF line endings'   => ["User-agent: *\r\nDisallow: /\r\n"],
            'extra whitespace'    => ["User-agent: *\nDisallow:   /\n"],
            'no trailing newline' => ["User-agent: *\nDisallow: /"],
        ];
    }

    #[Test]
    #[DataProvider('disallowsWholeDomainProvider')]
    public function check_throwsWhenRobotsTxtDisallowsTheWholeDomain(string $content): void
    {
        // prepare
        file_put_contents($this->webRoot . '/robots.txt', $content);
        $check = new RobotsTxtCheck($this->webRoot, true);

        // test
        try {
            $check->check();
            $this->fail('Expected exception was not thrown.');
        } catch (RobotsTxtNotAvailableException $exception) {
            // verify
            $this->assertSame('robots.txt disallows the whole domain.', $exception->getMessage());
        }
    }

    #[Test]
    public function check_doesNotThrowForAHarmlessRobotsTxt(): void
    {
        // prepare
        file_put_contents($this->webRoot . '/robots.txt', "User-agent: *\nDisallow: /admin\nAllow: /\n");
        $check = new RobotsTxtCheck($this->webRoot, true);

        // test
        $check->check();

        // verify
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function check_throwsWhenRobotsTxtIsMissing(): void
    {
        // prepare
        $check = new RobotsTxtCheck($this->webRoot, true);

        // test / verify
        $this->expectException(RobotsTxtNotAvailableException::class);
        $check->check();
    }

    #[Test]
    public function check_throwsWhenRobotsTxtIsNotReadable(): void
    {
        if (posix_geteuid() === 0) {
            $this->markTestSkipped('Running as root ignores filesystem permissions.');
        }

        // prepare
        file_put_contents($this->webRoot . '/robots.txt', "User-agent: *\nDisallow: /admin\n");
        chmod($this->webRoot . '/robots.txt', 0000);
        $check = new RobotsTxtCheck($this->webRoot, true);

        // test / verify
        $this->expectException(RobotsTxtNotAvailableException::class);
        $check->check();
    }

    #[Test]
    public function isActive_reflectsTheInjectedFlag(): void
    {
        // test / verify
        $this->assertTrue((new RobotsTxtCheck($this->webRoot, true))->isActive());
        $this->assertFalse((new RobotsTxtCheck($this->webRoot, false))->isActive());
    }
}
