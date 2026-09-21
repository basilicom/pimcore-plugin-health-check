<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

final class RobotsTxtCheck extends AbstractCheck
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config, private readonly string $defaultPath)
    {
        parent::__construct($config);
    }

    public function getIdentifier(): string
    {
        return 'system:robots_txt';
    }

    public function getLabel(): string
    {
        return 'Robots.txt';
    }

    protected function doRun(): CheckResult
    {
        $path = (string) ($this->option('path') ?: $this->defaultPath);

        if (!is_file($path) || !is_readable($path)) {
            $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
            $fallback = is_string($documentRoot) && $documentRoot !== ''
                ? rtrim($documentRoot, '/') . '/robots.txt'
                : null;

            if ($fallback === null || !is_file($fallback) || !is_readable($fallback)) {
                return $this->critical(
                    'robots.txt is not available or not readable.',
                    ['path' => $path],
                );
            }

            $path = $fallback;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (strtolower(str_replace(' ', '', $line)) === 'disallow:/') {
                return $this->critical('robots.txt disallows whole domain.', ['path' => $path]);
            }
        }

        return $this->ok('robots.txt is available and does not disallow the whole domain.', ['path' => $path]);
    }
}
