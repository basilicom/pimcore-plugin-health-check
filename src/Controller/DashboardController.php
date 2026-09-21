<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Controller;

use Basilicom\PimcorePluginHealthCheck\Services\AccessGuard;
use Basilicom\PimcorePluginHealthCheck\Services\HealthCheckService;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

final class DashboardController
{
    public function __construct(
        private readonly HealthCheckService $healthCheckService,
        private readonly AccessGuard $accessGuard,
        private readonly Environment $twig,
        private readonly bool $dashboardEnabled,
    ) {
    }

    #[Route(
        path: '%pimcore_plugin_health_check.dashboard.path%',
        name: 'basilicom_health_check_dashboard',
        methods: ['GET'],
    )]
    public function dashboardAction(Request $request): Response
    {
        if (!$this->dashboardEnabled) {
            throw new NotFoundHttpException('The health check dashboard is disabled.');
        }

        if (!$this->accessGuard->isGranted($request)) {
            return new Response('Unauthorized', Response::HTTP_UNAUTHORIZED, ['Content-Type' => 'text/plain']);
        }

        $results = $this->healthCheckService->runAll();

        $content = $this->twig->render('@PimcorePluginHealthCheck/dashboard.html.twig', [
            'results' => $results,
            'summary' => $this->healthCheckService->getSummary($results),
            'overallStatus' => $this->healthCheckService->getOverallStatus($results),
            'healthy' => $this->healthCheckService->isHealthy($results),
            'generatedAt' => new DateTimeImmutable(),
        ]);

        $response = new Response($content);
        $response->setMaxAge(0);
        $response->headers->set('Cache-Control', 'no-cache, must-revalidate');

        return $response;
    }
}
