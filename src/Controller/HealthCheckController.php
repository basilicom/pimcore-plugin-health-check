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

use Basilicom\PimcorePluginHealthCheck\Services\CheckResult;
use Basilicom\PimcorePluginHealthCheck\Services\HealthCheckService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final readonly class HealthCheckController
{
    private const string TOKEN_HEADER = 'X-Health-Check-Token';

    public function __construct(
        private HealthCheckService $healthCheckService,
        private LoggerInterface $logger,
        private ?string $token = null,
    ) {
    }

    #[Route(
        path: '/health-check-status', // naming to not collide with other routes
        name: 'health_check_status',
        methods: ['GET', 'HEAD'],
    )]
    public function checkAction(Request $request): Response
    {
        // before any check runs, so an anonymous caller cannot make the server do the work
        if (!$this->isAuthorised($request)) {
            return $this->reject();
        }

        // ties this response to its log entries, which hold the actual reason
        $id = bin2hex(random_bytes(8));

        try {
            $results = $this->healthCheckService->run();
        } catch (Throwable $exception) {
            $this->logger->error(sprintf('Health check could not run [%s]', $id), ['exception' => $exception]);

            return $this->plainText(sprintf('FAILURE: [%s]', $id), Response::HTTP_SERVICE_UNAVAILABLE);
        }

        foreach ($results as $result) {
            if ($result->hasFailed()) {
                $this->logger->error(
                    sprintf('Health check "%s" failed [%s]: %s', $result->shortName(), $id, $result->reason()),
                    ['exception' => $result->failure]
                );
            } elseif ($result->isWarning()) {
                $this->logger->warning(
                    sprintf('Health check "%s" warned [%s]: %s', $result->shortName(), $id, $result->reason()),
                    ['exception' => $result->failure]
                );
            }
        }

        // a warning means degraded, not broken - it must never take the node out of the load balancer
        if (array_filter($results, static fn (CheckResult $result): bool => $result->hasFailed()) === []) {
            return $this->plainText('SUCCESS', Response::HTTP_OK);
        }

        // the token is optional, so the body must stay safe for an anonymous caller either way
        return $this->plainText(sprintf('FAILURE: [%s]', $id), Response::HTTP_SERVICE_UNAVAILABLE);
    }

    /**
     * Readiness answers "should this node get traffic". Liveness answers "should this process be
     * restarted", and no external dependency can be fixed by a restart - so this deliberately runs
     * no check. Reaching it already proves the process is up, PHP responds and the kernel routed.
     */
    #[Route(
        path: '/health-check-live',
        name: 'health_check_live',
        methods: ['GET', 'HEAD'],
    )]
    public function liveAction(Request $request): Response
    {
        if (!$this->isAuthorised($request)) {
            return $this->reject();
        }

        return $this->plainText('SUCCESS', Response::HTTP_OK);
    }

    private function reject(): Response
    {
        $this->logger->notice('Health check request rejected: missing or wrong token.');

        // a 404 is indistinguishable from an unknown route, a 401 would confirm the endpoint
        return $this->plainText('', Response::HTTP_NOT_FOUND);
    }

    private function isAuthorised(Request $request): bool
    {
        if ($this->token === null) {
            return true;
        }

        // query->get() throws BadRequestException on ?token[]=x, which the kernel turns into a 400
        // and would confirm the route exists; all() returns the raw value and never throws
        $presented = $request->headers->get(self::TOKEN_HEADER) ?? $request->query->all()['token'] ?? null;

        return is_string($presented) && hash_equals($this->token, $presented);
    }

    private function plainText(string $body, int $status): Response
    {
        $response = new Response($body, $status);
        $response->headers->set('Content-Type', 'text/plain; charset=UTF-8');
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');

        return $response;
    }
}
