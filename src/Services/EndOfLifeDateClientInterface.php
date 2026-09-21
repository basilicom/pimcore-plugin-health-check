<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Services;

interface EndOfLifeDateClientInterface
{
    /**
     * Returns the release cycles of a product as published by endoflife.date,
     * or null when the data could not be retrieved (disabled, network or parse error).
     *
     * @return list<array<string, mixed>>|null
     */
    public function getCycles(string $product): ?array;
}
