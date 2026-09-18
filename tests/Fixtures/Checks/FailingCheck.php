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
use Basilicom\PimcorePluginHealthCheck\Exception\FilesystemNotWriteableException;

final class FailingCheck implements CheckInterface
{
    public function __construct(private readonly string $reason)
    {
    }

    public function check(): void
    {
        throw new FilesystemNotWriteableException($this->reason);
    }

    public function isActive(): bool
    {
        return true;
    }
}
