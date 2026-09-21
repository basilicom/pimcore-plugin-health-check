<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

final class DebugModeCheck extends AbstractCheck
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        array $config,
        private readonly bool $debug,
        private readonly string $environment,
    ) {
        parent::__construct($config);
    }

    public function getIdentifier(): string
    {
        return 'security:debug_mode';
    }

    public function getLabel(): string
    {
        return 'Debug Mode';
    }

    protected function doRun(): CheckResult
    {
        $data = ['debug' => $this->debug, 'environment' => $this->environment];

        if ($this->debug && $this->environment === 'prod') {
            return $this->critical('Debug mode is enabled in the production environment.', $data);
        }

        if ($this->debug) {
            return $this->ok(
                sprintf('Debug mode is enabled (environment: %s).', $this->environment),
                $data,
            );
        }

        return $this->ok('Debug mode is disabled.', $data);
    }
}
