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

namespace Basilicom\PimcorePluginHealthCheck;

enum Severity
{
    case Ok;

    /**
     * Degraded but still able to serve requests, so the endpoint stays green.
     */
    case Warning;

    case Failure;
}
