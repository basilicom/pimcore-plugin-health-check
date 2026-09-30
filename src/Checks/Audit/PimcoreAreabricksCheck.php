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

use Basilicom\PimcorePluginHealthCheck\Checks\AbstractReportingCheck;
use Basilicom\PimcorePluginHealthCheck\Checks\Report;
use Pimcore\Extension\Document\Areabrick\AreabrickManagerInterface;

final readonly class PimcoreAreabricksCheck extends AbstractReportingCheck
{
    public function __construct(bool $enabled, private ?AreabrickManagerInterface $areabrickManager = null)
    {
        parent::__construct($enabled);
    }

    public function identifier(): string
    {
        return 'pimcore:areabricks';
    }

    public function label(): string
    {
        return 'Pimcore Areabricks';
    }

    protected function examine(): Report
    {
        if ($this->areabrickManager === null) {
            return Report::notAvailable('The Pimcore areabrick manager is not available (n/a)');
        }

        $count = count($this->areabrickManager->getBrickIds());

        return Report::ok(sprintf('There are %d Areabricks in the system', $count), ['count' => $count]);
    }
}
