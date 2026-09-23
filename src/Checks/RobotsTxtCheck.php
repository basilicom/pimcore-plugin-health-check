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

use Basilicom\PimcorePluginHealthCheck\Exception\RobotsTxtNotAvailableException;
use Basilicom\PimcorePluginHealthCheck\Severity;
use Pimcore\Bundle\SeoBundle\Config;

/**
 * A robots.txt is mandatory, but it does not have to be a file: Pimcore answers /robots.txt from
 * its own settings. Its built-in "allow everything" fallback does not count - that one is what a
 * site gets when nobody decided anything.
 *
 * Nothing here takes the endpoint to 503. A robots.txt that is missing or closes the domain off
 * costs reach, not availability, and no load balancer can fix either by moving traffic elsewhere.
 */
final readonly class RobotsTxtCheck implements CheckInterface
{
    public function __construct(
        private string $webRootDirectory,
        private bool $enabled,
    ) {
    }

    public function check(): void
    {
        $contents = $this->servedContents();

        if ($contents === []) {
            throw new RobotsTxtNotAvailableException(
                'No robots.txt is served: there is no file in the web root and none configured in Pimcore.',
                severity: Severity::Warning
            );
        }

        foreach ($contents as $content) {
            $this->rejectFullDisallow($content);
        }
    }

    public function isActive(): bool
    {
        return $this->enabled;
    }

    /**
     * A file in the web root is served by the web server and Pimcore never sees the request, so it
     * is the only source once it exists. Otherwise every site's configured robots.txt is served.
     *
     * @return list<string>
     *
     * @throws RobotsTxtNotAvailableException
     */
    private function servedContents(): array
    {
        $file = rtrim($this->webRootDirectory, '/') . '/robots.txt';

        if (is_file($file)) {
            $content = @file_get_contents($file);

            if ($content === false) {
                throw new RobotsTxtNotAvailableException('robots.txt is not readable.');
            }

            return [$content];
        }

        if (!class_exists(Config::class)) {
            return [];
        }

        // Pimcore ignores a blank entry and serves its fallback instead, so neither counts here
        return array_values(array_filter(Config::getRobotsConfig(), static fn (string $content): bool => trim($content) !== ''));
    }

    /** @throws RobotsTxtNotAvailableException */
    private function rejectFullDisallow(string $content): void
    {
        foreach (preg_split('/\R/', $content) ?: [] as $line) {
            // robots.txt lines carry arbitrary spacing; strip all of it before comparing
            if (strtolower((string)preg_replace('/\s+/', '', $line)) === 'disallow:/') {
                throw new RobotsTxtNotAvailableException(
                    'robots.txt disallows the whole domain.',
                    severity: Severity::Warning
                );
            }
        }
    }
}
