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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

/**
 * Renders the last stored audit run. It runs no check itself: the inventory work happens once,
 * from `basilicom:health-check:audit`, and a page view costs one settings-store read.
 */
final readonly class DashboardController
{
    public function __construct(
        private RunStoreInterface $runStore,
        private AccessGuard $accessGuard,
        private Environment $twig,
        private bool $enabled,
    ) {
    }

    #[Route(
        path: '%pimcore_plugin_health_check.dashboard.path%',
        name: 'basilicom_health_check_dashboard',
        methods: ['GET'],
    )]
    public function dashboardAction(Request $request): Response
    {
        // same answer as an unknown route, so an anonymous caller cannot confirm the page exists
        if (!$this->enabled || !$this->accessGuard->isGranted($request)) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $run = $this->runStore->load();

        $response = new Response($this->twig->render('@PimcorePluginHealthCheck/dashboard.html.twig', [
            'run'     => $run,
            'results' => $run->results ?? [],
            'summary' => $run?->summary(),
            'overall' => $run?->overall(),
        ]));
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');

        return $response;
    }
}
