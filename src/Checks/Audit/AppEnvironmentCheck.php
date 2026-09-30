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

final readonly class AppEnvironmentCheck extends AbstractReportingCheck
{
    private const array MODES = ['prod' => 'production', 'dev' => 'development', 'test' => 'test'];

    public function __construct(bool $enabled, private string $environment, private string $expectedEnvironment)
    {
        parent::__construct($enabled);
    }

    public function identifier(): string
    {
        return 'system:app_environment';
    }

    public function label(): string
    {
        return 'App Environment';
    }

    protected function examine(): Report
    {
        $data = ['environment' => $this->environment, 'expected' => $this->expectedEnvironment];

        if ($this->environment === $this->expectedEnvironment) {
            return Report::ok(sprintf('Application is running in %s mode', self::mode($this->environment)), $data);
        }

        return Report::warning(
            sprintf('Application is running in %s mode, expected %s mode', self::mode($this->environment), self::mode($this->expectedEnvironment)),
            $data
        );
    }

    private static function mode(string $environment): string
    {
        return self::MODES[$environment] ?? $environment;
    }
}
