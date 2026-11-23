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

namespace Basilicom\PimcorePluginHealthCheck\Tests\Unit;

use Basilicom\PimcorePluginHealthCheck\Severity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ValueError;

class SeverityTest extends TestCase
{
    #[Test]
    public function label_usesTheInstrideMonitorNames(): void
    {
        // test / verify
        $this->assertSame('check_result_ok', Severity::Ok->label());
        $this->assertSame('check_result_warning', Severity::Warning->label());
        $this->assertSame('check_result_critical', Severity::Failure->label());
        $this->assertSame('check_result_skipped', Severity::Skipped->label());
        $this->assertSame('check_result_na', Severity::NotAvailable->label());
    }

    #[Test]
    public function key_andFromKey_roundTripEveryCase(): void
    {
        foreach (Severity::cases() as $severity) {
            // test / verify
            $this->assertSame($severity, Severity::fromKey($severity->key()));
        }
    }

    #[Test]
    public function fromKey_rejectsAnUnknownKey(): void
    {
        // test / verify
        $this->expectException(ValueError::class);
        Severity::fromKey('meh');
    }
}
