<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

use Symfony\Component\HttpFoundation\RequestStack;

final class HttpsConnectionCheck extends AbstractCheck
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config, private readonly RequestStack $requestStack)
    {
        parent::__construct($config);
    }

    public function getIdentifier(): string
    {
        return 'system:https_connection';
    }

    public function getLabel(): string
    {
        return 'HTTPS Connection';
    }

    protected function doRun(): CheckResult
    {
        $request = $this->requestStack->getMainRequest();

        if ($request === null) {
            return $this->skipped('No HTTP request available, check skipped (CLI)');
        }

        if ($request->isSecure()) {
            return $this->ok('HTTPS encryption activated', ['scheme' => $request->getScheme()]);
        }

        return $this->critical('HTTPS encryption not activated', ['scheme' => $request->getScheme()]);
    }
}
