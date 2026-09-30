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

namespace Basilicom\PimcorePluginHealthCheck\Checks\Audit;

use Basilicom\PimcorePluginHealthCheck\Checks\Report;

final readonly class PhpVersionCheck extends AbstractVersionCheck
{
    public function identifier(): string
    {
        return 'system:php_version';
    }

    public function label(): string
    {
        return 'PHP Version';
    }

    protected function examine(): Report
    {
        return $this->compare('PHP', 'php', PHP_VERSION, PHP_VERSION);
    }
}
