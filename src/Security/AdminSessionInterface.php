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

use Symfony\Component\HttpFoundation\Request;

interface AdminSessionInterface
{
    /** True only for a logged in Pimcore backend user with the admin flag - not for any backend user. */
    public function isAdmin(Request $request): bool;
}
