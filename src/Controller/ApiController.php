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

namespace Basilicom\PimcorePluginHealthCheck\Controller;

use Basilicom\PimcorePluginHealthCheck\Audit\RunStoreInterface;
use Basilicom\PimcorePluginHealthCheck\Security\AccessGuard;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The stored audit run as JSON, one entry per check, for monitoring tools that want the detail
 * the plain endpoint deliberately withholds. Authorised requests always answer 200 - the body
 * carries the verdict - except 404 for an unknown `?check=`, so a typo cannot look healthy.
 */
final readonly class ApiController
{
    public function __construct(
        private RunStoreInterface $runStore,
        private AccessGuard $accessGuard,
        private bool $enabled,
    ) {
    }

    #[Route(
        path: '%pimcore_plugin_health_check.dashboard.api_path%',
        name: 'basilicom_health_check_api',
        methods: ['GET'],
    )]
    public function apiAction(Request $request): Response
    {
        if (!$this->enabled || !$this->accessGuard->isGranted($request)) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $run = $this->runStore->load();

        if ($run === null) {
            return $this->json(['status' => 'na', 'healthy' => null, 'generated_at' => null, 'summary' => null, 'checks' => [],
                'message'                => 'No audit run stored yet. Run bin/console basilicom:health-check:audit.']);
        }

        $identifier = $request->query->all()['check'] ?? null;
        $payload    = $run->toArray();

        if (is_string($identifier) && $identifier !== '') {
            $result = $run->find($identifier);

            if ($result === null) {
                return $this->json(['error' => sprintf('Unknown check "%s"', $identifier)], Response::HTTP_NOT_FOUND);
            }

            $payload['checks'] = array_values(array_filter($payload['checks'], static fn (array $c): bool => $c['identifier'] === $identifier));
        }

        return $this->json([
            'status'       => $run->overall()->key(),
            'healthy'      => $run->isHealthy(),
            'generated_at' => $payload['generated_at'],
            'duration_ms'  => $payload['duration_ms'],
            'summary'      => $run->summary(),
            'checks'       => $payload['checks'],
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function json(array $payload, int $status = Response::HTTP_OK): JsonResponse
    {
        $response = new JsonResponse($payload, $status);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');

        return $response;
    }
}
