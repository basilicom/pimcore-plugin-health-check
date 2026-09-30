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

namespace Basilicom\PimcorePluginHealthCheck\Tests\Unit\Security;

use Basilicom\PimcorePluginHealthCheck\Security\AccessGuard;
use Basilicom\PimcorePluginHealthCheck\Security\AdminSessionInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class AccessGuardTest extends TestCase
{
    private const string KEY = 'a-valid-api-key-of-16-chars-or-more';

    #[Test]
    public function isGranted_acceptsTheCorrectHeaderKeyWithoutTouchingTheSession(): void
    {
        // prepare
        $session = $this->createMock(AdminSessionInterface::class);
        $session->expects($this->never())->method('isAdmin');
        $guard   = new AccessGuard($session, self::KEY);
        $request = new Request();
        $request->headers->set(AccessGuard::API_KEY_HEADER, self::KEY);

        // test / verify
        $this->assertTrue($guard->isGranted($request));
    }

    #[Test]
    public function isGranted_neverReadsTheKeyFromTheQueryString(): void
    {
        // prepare
        $session = $this->createMock(AdminSessionInterface::class);
        $session->method('isAdmin')->willReturn(false);
        $guard = new AccessGuard($session, self::KEY);

        // test / verify
        $this->assertFalse($guard->isGranted(new Request(['api_key' => self::KEY])));
    }

    #[Test]
    public function isGranted_fallsBackToTheAdminSessionForAWrongOrMissingKey(): void
    {
        // prepare
        $session = $this->createMock(AdminSessionInterface::class);
        $session->method('isAdmin')->willReturn(true);
        $guard   = new AccessGuard($session, self::KEY);
        $request = new Request();
        $request->headers->set(AccessGuard::API_KEY_HEADER, 'wrong');

        // test / verify
        $this->assertTrue($guard->isGranted($request));
        $this->assertTrue($guard->isGranted(new Request()));
    }

    #[Test]
    public function isGranted_isFalseWithoutKeyAndWithoutAdminSession(): void
    {
        // prepare
        $session = $this->createMock(AdminSessionInterface::class);
        $session->method('isAdmin')->willReturn(false);

        // test / verify
        $this->assertFalse((new AccessGuard($session, self::KEY))->isGranted(new Request()));
        $this->assertFalse((new AccessGuard($session, null))->isGranted(new Request()));
    }

    #[Test]
    public function isGranted_ignoresTheHeaderWhenNoKeyIsConfigured(): void
    {
        // prepare
        $session = $this->createMock(AdminSessionInterface::class);
        $session->method('isAdmin')->willReturn(false);
        $request = new Request();
        $request->headers->set(AccessGuard::API_KEY_HEADER, 'anything');

        // test / verify
        $this->assertFalse((new AccessGuard($session, null))->isGranted($request));
    }
}
