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

use Basilicom\PimcorePluginHealthCheck\Audit\RunStoreInterface;
use Basilicom\PimcorePluginHealthCheck\Audit\StoredRun;

final class InMemoryRunStore implements RunStoreInterface
{
    public function __construct(private ?StoredRun $run = null)
    {
    }

    public function save(StoredRun $run): void
    {
        $this->run = $run;
    }

    public function load(): ?StoredRun
    {
        return $this->run;
    }
}
