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

use Basilicom\PimcorePluginHealthCheck\Checks\Audit\ComposerAuditCheck;
use Basilicom\PimcorePluginHealthCheck\Checks\Audit\ComposerOutdatedCheck;
use Basilicom\PimcorePluginHealthCheck\Severity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ComposerChecksTest extends TestCase
{
    /** @var list<string> */
    private array $stubs = [];

    protected function tearDown(): void
    {
        foreach ($this->stubs as $stub) {
            @unlink($stub);
        }
    }

    #[Test]
    public function audit_isNotAvailableWhenComposerIsMissing(): void
    {
        // test
        $report = (new ComposerAuditCheck(true, '/nonexistent/composer', sys_get_temp_dir(), 5))->inspect();

        // verify
        $this->assertSame(Severity::NotAvailable, $report->severity);
        $this->assertStringStartsWith('n/a: ', $report->message);
    }

    #[Test]
    public function audit_isNotAvailableOnAnAuthenticationFailureAndShowsTheFirstStderrLine(): void
    {
        // prepare
        $binary = $this->stub('echo "Could not authenticate against repo.example.com" >&2; exit 1');

        // test
        $report = (new ComposerAuditCheck(true, $binary, sys_get_temp_dir(), 5))->inspect();

        // verify
        $this->assertSame(Severity::NotAvailable, $report->severity);
        $this->assertSame('n/a: Could not authenticate against repo.example.com', $report->message);
    }

    #[Test]
    public function audit_isNotAvailableOnATimeout(): void
    {
        // test
        $report = (new ComposerAuditCheck(true, $this->stub('sleep 5'), sys_get_temp_dir(), 1))->inspect();

        // verify
        $this->assertSame(Severity::NotAvailable, $report->severity);
        $this->assertSame('n/a: composer timed out after 1 s', $report->message);
    }

    #[Test]
    public function audit_isOkWithoutAdvisoriesAndFailsWithSome(): void
    {
        // prepare
        $clean = $this->stubJson('{"advisories":{},"abandoned":{}}');
        $dirty = $this->stubJson('{"advisories":{"acme/foo":[{"cve":"CVE-1"},{"cve":"CVE-2"}],"acme/bar":[{"cve":"CVE-3"}]}}', 2);

        // test
        $ok      = (new ComposerAuditCheck(true, $clean, sys_get_temp_dir(), 5))->inspect();
        $failure = (new ComposerAuditCheck(true, $dirty, sys_get_temp_dir(), 5))->inspect();

        // verify
        $this->assertSame(Severity::Ok, $ok->severity);
        $this->assertSame(Severity::Failure, $failure->severity);
        $this->assertSame(3, $failure->data['advisory_count']);
        $this->assertSame(['acme/foo', 'acme/bar'], $failure->data['packages']);
    }

    #[Test]
    public function outdated_countsDirectDependenciesAndGrades(): void
    {
        // prepare
        $binary = $this->stubJson('{"installed":[{"name":"a/b","version":"1.0"},{"name":"c/d","version":"2.0"}]}');

        // test
        $report = (new ComposerOutdatedCheck(true, $binary, sys_get_temp_dir(), 5, 1, 25))->inspect();

        // verify
        $this->assertSame(Severity::Warning, $report->severity);
        $this->assertSame('2 direct composer dependencies are outdated.', $report->message);
        $this->assertSame(['a/b', 'c/d'], $report->data['packages']);
    }

    private function stubJson(string $json, int $exitCode = 0): string
    {
        return $this->stub(sprintf("printf '%%s' %s; exit %d", escapeshellarg($json), $exitCode));
    }

    private function stub(string $body): string
    {
        $path = sys_get_temp_dir() . '/health-check-composer-' . bin2hex(random_bytes(6)) . '.sh';
        file_put_contents($path, "#!/bin/sh\n" . $body . "\n");
        chmod($path, 0o755);
        $this->stubs[] = $path;

        return $path;
    }
}
