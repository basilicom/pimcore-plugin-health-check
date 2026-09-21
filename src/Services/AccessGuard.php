<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Services;

use Pimcore\Tool\Authentication;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Throwable;

/**
 * Guards the dashboard and the JSON API.
 *
 * Access is granted to a request carrying the configured API key (`X-API-KEY` header or
 * `api_key` query parameter) or to a logged in Pimcore admin user. Without a configured
 * key only the admin session grants access - the endpoints are never public.
 */
final class AccessGuard
{
    public const HEADER = 'X-API-KEY';
    public const QUERY_PARAMETER = 'api_key';

    public function __construct(
        private readonly ?string $apiKey = null,
        private readonly ?AuthorizationCheckerInterface $authorizationChecker = null,
    ) {
    }

    public function isGranted(Request $request): bool
    {
        return $this->hasValidApiKey($request) || $this->hasPimcoreUserSession($request);
    }

    public function hasValidApiKey(Request $request): bool
    {
        $configured = (string) ($this->apiKey ?? '');

        if ($configured === '') {
            return false;
        }

        $provided = $request->headers->get(self::HEADER)
            ?? $request->query->get(self::QUERY_PARAMETER);

        return is_string($provided) && $provided !== '' && hash_equals($configured, $provided);
    }

    private function hasPimcoreUserSession(Request $request): bool
    {
        try {
            if ($this->authorizationChecker?->isGranted('ROLE_PIMCORE_USER') === true) {
                return true;
            }
        } catch (Throwable) {
            // no active firewall/token for this request - fall through to the session check
        }

        try {
            if (class_exists(Authentication::class)) {
                return Authentication::authenticateSession($request) !== null;
            }
        } catch (Throwable) {
            // no admin session available
        }

        return false;
    }
}
