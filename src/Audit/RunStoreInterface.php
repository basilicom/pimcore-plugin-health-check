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

namespace Basilicom\PimcorePluginHealthCheck\Audit;

/**
 * Where the audit command puts its result and where the dashboard and the API read it from.
 * Neither of them runs a check: the expensive inventory work happens once, from cron.
 */
interface RunStoreInterface
{
    public function save(StoredRun $run): void;

    public function load(): ?StoredRun;
}
