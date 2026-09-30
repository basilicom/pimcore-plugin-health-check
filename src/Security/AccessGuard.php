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

/**
 * Gate for the dashboard and the JSON API. Both show the reasons the monitoring endpoint hides,
 * so they open only to a logged in Pimcore admin or to a caller presenting the API key.
 *
 * The key is a separate secret from the monitoring token on purpose: the token only answers
 * "healthy or not", the key hands out the whole inventory. It travels in a header, never in the
 * query string - query strings end up in access logs, proxy logs and browser history.
 */
final readonly class AccessGuard
{
    public const string API_KEY_HEADER = 'X-Health-Check-Api-Key';

    public function __construct(
        private AdminSessionInterface $adminSession,
        private ?string $apiKey = null,
    ) {
    }

    public function isGranted(Request $request): bool
    {
        // cheap and constant-time first, so an anonymous caller never costs a session lookup
        if ($this->apiKey !== null) {
            $presented = $request->headers->get(self::API_KEY_HEADER);

            if (is_string($presented) && hash_equals($this->apiKey, $presented)) {
                return true;
            }
        }

        return $this->adminSession->isAdmin($request);
    }
}
