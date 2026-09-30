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

namespace Basilicom\PimcorePluginHealthCheck\Security;

use Pimcore\Tool\Authentication;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

final readonly class PimcoreAdminSession implements AdminSessionInterface
{
    public function isAdmin(Request $request): bool
    {
        try {
            return Authentication::authenticateSession($request)?->isAdmin() === true;
        } catch (Throwable) {
            // no session, no admin firewall on this route, or Pimcore not booted - all mean "no"
            return false;
        }
    }
}
