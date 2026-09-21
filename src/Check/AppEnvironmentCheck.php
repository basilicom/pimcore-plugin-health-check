<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

final class AppEnvironmentCheck extends AbstractCheck
{
    private const MODES = [
        'prod' => 'production',
        'dev' => 'development',
        'test' => 'test',
    ];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config, private readonly string $currentEnvironment)
    {
        parent::__construct($config);
    }

    public function getIdentifier(): string
    {
        return 'system:app_environment';
    }

    public function getLabel(): string
    {
        return 'App Environment';
    }

    protected function doRun(): CheckResult
    {
        $expected = (string) $this->option('environment', $this->currentEnvironment);
        $data = ['environment' => $this->currentEnvironment, 'expected' => $expected];

        if ($this->currentEnvironment === $expected) {
            return $this->ok(
                sprintf('Application is running in %s mode', $this->mode($this->currentEnvironment)),
                $data,
            );
        }

        return $this->warning(
            sprintf(
                'Application is running in %s mode, expected %s mode',
                $this->mode($this->currentEnvironment),
                $this->mode($expected),
            ),
            $data,
        );
    }

    private function mode(string $environment): string
    {
        return self::MODES[$environment] ?? $environment;
    }
}
