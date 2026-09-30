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

namespace Basilicom\PimcorePluginHealthCheck\Tests\Fixtures;

use Basilicom\PimcorePluginHealthCheck\Services\EndOfLifeDateClientInterface;

final class StubEndOfLifeDateClient implements EndOfLifeDateClientInterface
{
    /** @param list<array<string, mixed>>|null $cycles */
    public function __construct(private readonly ?array $cycles)
    {
    }

    public function cycles(string $product): ?array
    {
        return $this->cycles;
    }
}
