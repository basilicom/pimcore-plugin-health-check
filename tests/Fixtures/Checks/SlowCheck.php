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

namespace Basilicom\PimcorePluginHealthCheck\Tests\Fixtures\Checks;

use Basilicom\PimcorePluginHealthCheck\Checks\CheckInterface;

final class SlowCheck implements CheckInterface
{
    public function __construct(private readonly int $sleepMicroseconds)
    {
    }

    public function check(): void
    {
        usleep($this->sleepMicroseconds);
    }

    public function isActive(): bool
    {
        return true;
    }
}
