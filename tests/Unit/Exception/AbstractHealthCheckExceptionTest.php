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

namespace Basilicom\PimcorePluginHealthCheck\Tests\Unit\Exception;

use Basilicom\PimcorePluginHealthCheck\Exception\AbstractHealthCheckException;
use Basilicom\PimcorePluginHealthCheck\Severity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class AbstractHealthCheckExceptionTest extends TestCase
{
    #[Test]
    public function severity_defaultsToFailure(): void
    {
        // prepare
        $exception = new class ('boom') extends AbstractHealthCheckException {
        };

        // test / verify
        $this->assertSame(Severity::Failure, $exception->severity());
    }

    #[Test]
    public function severity_returnsTheExplicitlyPassedSeverity(): void
    {
        // prepare
        $exception = new class ('degraded', null, Severity::Warning) extends AbstractHealthCheckException {
        };

        // test / verify
        $this->assertSame(Severity::Warning, $exception->severity());
    }

    #[Test]
    public function constructor_passesThroughMessageAndPrevious(): void
    {
        // prepare
        $previous  = new RuntimeException('root cause');
        $exception = new class ('boom', $previous) extends AbstractHealthCheckException {
        };

        // test / verify
        $this->assertSame('boom', $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
    }
}
