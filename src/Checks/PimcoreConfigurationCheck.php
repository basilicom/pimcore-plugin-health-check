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

namespace Basilicom\PimcorePluginHealthCheck\Checks;

use Basilicom\PimcorePluginHealthCheck\Exception\ConfigurationNotReadableException;
use Pimcore\Config;
use Throwable;

final class PimcoreConfigurationCheck implements CheckInterface
{
    public function check(): void
    {
        try {
            $environment         = Config::getEnvironment();
            $systemConfiguration = Config::getSystemConfiguration();
        } catch (Throwable $exception) {
            throw new ConfigurationNotReadableException(
                'Pimcore environment/system configuration is not readable.',
                previous: $exception
            );
        }

        if ($environment === '' || empty($systemConfiguration)) {
            throw new ConfigurationNotReadableException('Pimcore environment/system configuration is empty.');
        }
    }

    public function isActive(): bool
    {
        return true;
    }
}
