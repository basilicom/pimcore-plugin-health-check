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

use Basilicom\PimcorePluginHealthCheck\Checks\DiskSpaceCheck;
use Basilicom\PimcorePluginHealthCheck\Exception\DiskSpaceLowException;
use Basilicom\PimcorePluginHealthCheck\Severity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DiskSpaceCheckTest extends TestCase
{
    #[Test]
    public function check_doesNotThrowWhenThresholdsAreWellBelowTheActualFreeSpace(): void
    {
        // prepare
        $check = new DiskSpaceCheck(sys_get_temp_dir(), true, 1, 1);

        // test
        $check->check();

        // verify
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function check_throwsAFailureWhenBelowTheFailureThreshold(): void
    {
        // prepare
        $check = new DiskSpaceCheck(sys_get_temp_dir(), true, 100, 99);

        // test
        try {
            $check->check();
            $this->fail('Expected exception was not thrown.');
        } catch (DiskSpaceLowException $exception) {
            // verify
            $this->assertSame(Severity::Failure, $exception->severity());
            $this->assertStringContainsString('99%', $exception->getMessage());
        }
    }

    #[Test]
    public function check_throwsAWarningWhenBelowTheWarningThresholdButNotTheFailureThreshold(): void
    {
        // prepare
        $check = new DiskSpaceCheck(sys_get_temp_dir(), true, 100, 1);

        // test
        try {
            $check->check();
            $this->fail('Expected exception was not thrown.');
        } catch (DiskSpaceLowException $exception) {
            // verify
            $this->assertSame(Severity::Warning, $exception->severity());
            $this->assertStringContainsString('100%', $exception->getMessage());
        }
    }

    #[Test]
    public function check_throwsAFailureWhenTheDirectoryDoesNotExist(): void
    {
        // prepare
        $check = new DiskSpaceCheck(sys_get_temp_dir() . '/does-not-exist-' . bin2hex(random_bytes(8)), true, 20, 5);

        // test
        try {
            $check->check();
            $this->fail('Expected exception was not thrown.');
        } catch (DiskSpaceLowException $exception) {
            // verify
            $this->assertSame(Severity::Failure, $exception->severity());
            $this->assertSame('Free disk space could not be determined.', $exception->getMessage());
        }
    }

    #[Test]
    public function isActive_reflectsTheInjectedFlag(): void
    {
        // test / verify
        $this->assertTrue((new DiskSpaceCheck(sys_get_temp_dir(), true, 20, 5))->isActive());
        $this->assertFalse((new DiskSpaceCheck(sys_get_temp_dir(), false, 20, 5))->isActive());
    }
}
