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

final readonly class DebugModeCheck extends AbstractReportingCheck
{
    public function __construct(bool $enabled, private bool $debug, private string $environment)
    {
        parent::__construct($enabled);
    }

    public function identifier(): string
    {
        return 'security:debug_mode';
    }

    public function label(): string
    {
        return 'Debug Mode';
    }

    protected function examine(): Report
    {
        $data = ['debug' => $this->debug, 'environment' => $this->environment];

        if ($this->debug && $this->environment === 'prod') {
            return Report::failure('Debug mode is enabled in the production environment.', $data);
        }

        return Report::ok($this->debug ? sprintf('Debug mode is enabled (environment: %s).', $this->environment) : 'Debug mode is disabled.', $data);
    }
}
