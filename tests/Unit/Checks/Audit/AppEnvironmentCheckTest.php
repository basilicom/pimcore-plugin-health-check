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

namespace Basilicom\PimcorePluginHealthCheck\Tests\Unit\Checks\Audit;

use Basilicom\PimcorePluginHealthCheck\Checks\Audit\AppEnvironmentCheck;
use Basilicom\PimcorePluginHealthCheck\Severity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AppEnvironmentCheckTest extends TestCase
{
    #[Test]
    public function inspect_isOkWhenTheEnvironmentMatches(): void
    {
        // test
        $report = (new AppEnvironmentCheck(true, 'prod', 'prod'))->inspect();

        // verify
        $this->assertSame(Severity::Ok, $report->severity);
        $this->assertSame('Application is running in production mode', $report->message);
    }

    #[Test]
    public function inspect_warnsWhenTheEnvironmentDiffers(): void
    {
        // test
        $report = (new AppEnvironmentCheck(true, 'dev', 'prod'))->inspect();

        // verify
        $this->assertSame(Severity::Warning, $report->severity);
        $this->assertSame('Application is running in development mode, expected production mode', $report->message);
    }
}
