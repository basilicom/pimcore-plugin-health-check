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

namespace Basilicom\PimcorePluginHealthCheck\Tests\Unit\Services;

use Basilicom\PimcorePluginHealthCheck\Services\CheckResult;
use Basilicom\PimcorePluginHealthCheck\Severity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class CheckResultTest extends TestCase
{
    #[Test]
    public function withoutArgumentsItDefaultsToOkAndReportsSuccess(): void
    {
        // prepare
        $result = new CheckResult('Basilicom\PimcorePluginHealthCheck\Checks\CacheCheck');

        // test / verify
        $this->assertSame(Severity::Ok, $result->severity);
        $this->assertFalse($result->hasFailed());
        $this->assertFalse($result->isWarning());
        $this->assertSame('', $result->reason());
        $this->assertSame('CacheCheck', $result->shortName());
    }

    #[Test]
    public function withFailureSeverityItReportsTheExceptionMessage(): void
    {
        // prepare
        $exception = new RuntimeException('boom');
        $result    = new CheckResult('Basilicom\PimcorePluginHealthCheck\Checks\CacheCheck', Severity::Failure, $exception);

        // test / verify
        $this->assertTrue($result->hasFailed());
        $this->assertFalse($result->isWarning());
        $this->assertSame('boom', $result->reason());
    }

    #[Test]
    public function withWarningSeverityItIsAWarningButHasNotFailedEvenWithAnException(): void
    {
        // prepare
        $exception = new RuntimeException('degraded');
        $result    = new CheckResult('Basilicom\PimcorePluginHealthCheck\Checks\CacheCheck', Severity::Warning, $exception);

        // test / verify
        $this->assertTrue($result->isWarning());
        $this->assertFalse($result->hasFailed());
        $this->assertSame('degraded', $result->reason());
    }

    #[Test]
    public function shortNameReturnsUnqualifiedClassNameAsIs(): void
    {
        // prepare
        $result = new CheckResult('CacheCheck');

        // test / verify
        $this->assertSame('CacheCheck', $result->shortName());
    }
}
