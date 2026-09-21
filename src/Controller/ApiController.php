<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Controller;

use Basilicom\PimcorePluginHealthCheck\Check\CheckResult;
use Basilicom\PimcorePluginHealthCheck\Services\AccessGuard;
use Basilicom\PimcorePluginHealthCheck\Services\HealthCheckService;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class ApiController
{
    public function __construct(
        private readonly HealthCheckService $healthCheckService,
        private readonly AccessGuard $accessGuard,
        private readonly bool $apiEnabled,
    ) {
    }

    #[Route(
        path: '%pimcore_plugin_health_check.api.path%',
        name: 'basilicom_health_check_api',
        methods: ['GET'],
    )]
    public function apiAction(Request $request): Response
    {
        if (!$this->apiEnabled) {
            throw new NotFoundHttpException('The health check API is disabled.');
        }

        if (!$this->accessGuard->isGranted($request)) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $identifier = $request->query->get('check');
        $identifier = is_string($identifier) && $identifier !== '' ? $identifier : null;

        $results = $this->healthCheckService->runAll($identifier);

        if ($identifier !== null && $results === []) {
            return $this->json(['error' => sprintf('Unknown or disabled check "%s"', $identifier)], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'status' => $this->healthCheckService->getOverallStatus($results)->value,
            'healthy' => $this->healthCheckService->isHealthy($results),
            'generated_at' => (new DateTimeImmutable())->format(DATE_ATOM),
            'summary' => $this->healthCheckService->getSummary($results),
            'checks' => array_map(static fn (CheckResult $result): array => $result->toArray(), $results),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function json(array $payload, int $status = Response::HTTP_OK): JsonResponse
    {
        $response = new JsonResponse($payload, $status);
        $response->setMaxAge(0);
        $response->headers->set('Cache-Control', 'no-cache, must-revalidate');

        return $response;
    }
}
