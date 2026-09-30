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

namespace Basilicom\PimcorePluginHealthCheck\Services;

interface EndOfLifeDateClientInterface
{
    /**
     * Release cycles of a product as published by endoflife.date, newest first, or null when
     * the data is not available - lookups disabled, offline, or an unparseable answer.
     *
     * @return list<array<string, mixed>>|null
     */
    public function cycles(string $product): ?array;
}
