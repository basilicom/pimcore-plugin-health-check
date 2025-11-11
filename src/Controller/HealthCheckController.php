<?php

namespace Basilicom\PimcorePluginHealthCheck\Controller;

use Basilicom\PimcorePluginHealthCheck\Exception\AbstractHealthCheckException;
use Basilicom\PimcorePluginHealthCheck\Services\HealthCheckService;
use Exception;
use Pimcore\Bundle\ApplicationLoggerBundle\ApplicationLogger;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HealthCheckController
{
    #[Route(
        path: '/health-check-status', // naming to not collide with other routes
        name: 'health_check_status',
    )]
    public function checkAction(HealthCheckService $healthCheckService): Response
    {
        $response = new Response();
        $response->setMaxAge(0);
        $response->headers->set('Cache-Control', 'no-cache, must-revalidate');

        try {
            $healthCheckService->check();

            $response->setContent('SUCCESS');
            $response->setStatusCode(Response::HTTP_OK);
        } catch (AbstractHealthCheckException $exception) {
            $response->setContent('FAILURE: ' . $exception->getMessage());
        } catch (Exception $exception) {
            $id = uniqid();

            try {
                $logger = ApplicationLogger::getInstance('HealthCheck', true);
                $logger->error(
                    "Check Exception $id\n"
                    . 'Code: ' . $exception->getCode() . "\n"
                    . $exception->getMessage() . "\n"
                    . $exception->getTraceAsString() . "\n"
                );
            } catch (Exception | NotFoundExceptionInterface | ContainerExceptionInterface) {
                // no application logger available
            }

            $response->setContent("FAILURE: INTERNAL [$id] - see application log.");
        }

        return $response;
    }
}