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

namespace Basilicom\PimcorePluginHealthCheck\Checks;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Request-bound, so it belongs to the core group: on the CLI there is nothing to inspect.
 * A plain-HTTP request is a warning - it costs security, not availability.
 */
final readonly class HttpsConnectionCheck extends AbstractReportingCheck
{
    public function __construct(bool $enabled, private RequestStack $requestStack)
    {
        parent::__construct($enabled);
    }

    public function identifier(): string
    {
        return 'system:https_connection';
    }

    public function label(): string
    {
        return 'HTTPS Connection';
    }

    protected function examine(): Report
    {
        $request = $this->requestStack->getMainRequest();

        if ($request === null) {
            return Report::skipped('No HTTP request to inspect (CLI)');
        }

        $data = ['scheme' => $request->getScheme()];

        return $request->isSecure()
            ? Report::ok('HTTPS encryption activated', $data)
            : Report::warning('HTTPS encryption not activated', $data);
    }
}
