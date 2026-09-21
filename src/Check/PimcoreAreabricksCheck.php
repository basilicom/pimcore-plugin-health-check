<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

use Pimcore\Extension\Document\Areabrick\AreabrickManagerInterface;

final class PimcoreAreabricksCheck extends AbstractCheck
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config, private readonly ?AreabrickManagerInterface $areabrickManager = null)
    {
        parent::__construct($config);
    }

    public function getIdentifier(): string
    {
        return 'pimcore:areabricks';
    }

    public function getLabel(): string
    {
        return 'Pimcore Areabricks';
    }

    protected function doRun(): CheckResult
    {
        if ($this->areabrickManager === null) {
            return $this->na('The Pimcore areabrick manager is not available (n/a)');
        }

        $count = count($this->areabrickManager->getBrickIds());

        return $this->ok(
            sprintf('There are %d Areabricks in the system', $count),
            ['count' => $count],
        );
    }
}
