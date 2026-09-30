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

use Basilicom\PimcorePluginHealthCheck\Checks\Audit\DebugModeCheck;
use Basilicom\PimcorePluginHealthCheck\Severity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DebugModeCheckTest extends TestCase
{
    #[Test]
    public function inspect_failsWhenDebugIsOnInProd(): void
    {
        // test / verify
        $this->assertSame(Severity::Failure, (new DebugModeCheck(true, true, 'prod'))->inspect()->severity);
    }

    #[Test]
    public function inspect_isOkWhenDebugIsOffOrTheEnvironmentIsNotProd(): void
    {
        // test / verify
        $this->assertSame(Severity::Ok, (new DebugModeCheck(true, false, 'prod'))->inspect()->severity);
        $this->assertSame(Severity::Ok, (new DebugModeCheck(true, true, 'dev'))->inspect()->severity);
    }
}
