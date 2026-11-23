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

use Basilicom\PimcorePluginHealthCheck\Checks\AbstractReportingCheck;
use Basilicom\PimcorePluginHealthCheck\Checks\Report;
use Closure;

final readonly class ReportingFixtureCheck extends AbstractReportingCheck
{
    /** @param Closure(): Report $examine */
    public function __construct(private Closure $examine, bool $enabled = true, private string $identifier = 'test:fixture', private string $label = 'Fixture')
    {
        parent::__construct($enabled);
    }

    public function identifier(): string
    {
        return $this->identifier;
    }

    public function label(): string
    {
        return $this->label;
    }

    protected function examine(): Report
    {
        return ($this->examine)();
    }
}
