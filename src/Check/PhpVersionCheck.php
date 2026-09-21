<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

use Basilicom\PimcorePluginHealthCheck\Services\EndOfLifeDateClientInterface;

final class PhpVersionCheck extends AbstractVersionCheck
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config, private readonly EndOfLifeDateClientInterface $endOfLifeDateClient)
    {
        parent::__construct($config);
    }

    public function getIdentifier(): string
    {
        return 'system:php_version';
    }

    public function getLabel(): string
    {
        return 'PHP Version';
    }

    protected function doRun(): CheckResult
    {
        $current = PHP_VERSION;
        $required = $this->option('version');

        if ($required !== null && $required !== '') {
            return $this->staticResult($current, (string) $required, (string) $this->option('operator', '>='), 'PHP');
        }

        return $this->endOfLifeResult(
            $this->endOfLifeDateClient,
            'php',
            $current,
            $this->releaseCycle($current),
            'PHP',
        );
    }
}
