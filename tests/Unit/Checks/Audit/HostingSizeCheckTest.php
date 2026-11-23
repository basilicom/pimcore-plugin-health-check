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

namespace Basilicom\PimcorePluginHealthCheck\Tests\Unit\Checks\Audit;

use Basilicom\PimcorePluginHealthCheck\Checks\Audit\HostingSizeCheck;
use Basilicom\PimcorePluginHealthCheck\Checks\Audit\ThumbnailStorageCheck;
use Basilicom\PimcorePluginHealthCheck\Severity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class HostingSizeCheckTest extends TestCase
{
    #[Test]
    public function inspect_isNotAvailableWhenTheDirectoryCannotBeSized(): void
    {
        // prepare - a zero budget is the deterministic way to make du "not finish"
        $report = (new HostingSizeCheck(true, sys_get_temp_dir(), null, null, 0))->inspect();

        // verify
        $this->assertSame(Severity::NotAvailable, $report->severity);
        $this->assertStringNotContainsString(sys_get_temp_dir(), $report->message);
    }

    #[Test]
    public function inspect_gradesAnExistingDirectory(): void
    {
        // test
        $report = (new HostingSizeCheck(true, __DIR__, 1, 2, 10))->inspect();

        // verify
        if ($report->severity === Severity::NotAvailable) {
            $this->markTestSkipped('du is not available on this platform');
        }
        $this->assertSame(Severity::Failure, $report->severity);
        $this->assertStringStartsWith('Hosting size is', $report->message);
    }

    #[Test]
    public function thumbnailStorage_isOkWhenNoThumbnailsExistYet(): void
    {
        // test
        $report = (new ThumbnailStorageCheck(true, sys_get_temp_dir() . '/nope-' . bin2hex(random_bytes(4)), null, null, 10))->inspect();

        // verify
        $this->assertSame(Severity::Ok, $report->severity);
        $this->assertSame(0, $report->data['size_bytes']);
    }
}
