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

use Basilicom\PimcorePluginHealthCheck\Checks\HttpsConnectionCheck;
use Basilicom\PimcorePluginHealthCheck\Severity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class HttpsConnectionCheckTest extends TestCase
{
    #[Test]
    public function inspect_isSkippedWithoutARequest(): void
    {
        // test
        $report = (new HttpsConnectionCheck(true, new RequestStack()))->inspect();

        // verify
        $this->assertSame(Severity::Skipped, $report->severity);
    }

    #[Test]
    public function inspect_isOkOverHttpsAndAWarningOverHttp(): void
    {
        // prepare
        $secure = new RequestStack();
        $secure->push(Request::create('https://example.com/health-check-status'));
        $plain = new RequestStack();
        $plain->push(Request::create('http://example.com/health-check-status'));

        // test / verify
        $this->assertSame(Severity::Ok, (new HttpsConnectionCheck(true, $secure))->inspect()->severity);
        $this->assertSame('HTTPS encryption activated', (new HttpsConnectionCheck(true, $secure))->inspect()->message);
        $this->assertSame(Severity::Warning, (new HttpsConnectionCheck(true, $plain))->inspect()->severity);
    }

    #[Test]
    public function identifierAndLabel(): void
    {
        // prepare
        $check = new HttpsConnectionCheck(true, new RequestStack());

        // test / verify
        $this->assertSame('system:https_connection', $check->identifier());
        $this->assertSame('HTTPS Connection', $check->label());
    }
}
