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

namespace Basilicom\PimcorePluginHealthCheck\Tests\Unit\Audit;

use Basilicom\PimcorePluginHealthCheck\Audit\StoredRun;
use Basilicom\PimcorePluginHealthCheck\Services\CheckResult;
use Basilicom\PimcorePluginHealthCheck\Severity;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class StoredRunTest extends TestCase
{
    #[Test]
    public function overall_isTheWorstProblemAndIgnoresSkippedAndNotAvailable(): void
    {
        // prepare
        $ok      = new StoredRun(new DateTimeImmutable(), [$this->resultWith(Severity::Ok), $this->resultWith(Severity::Skipped), $this->resultWith(Severity::NotAvailable)]);
        $warning = new StoredRun(new DateTimeImmutable(), [$this->resultWith(Severity::Ok), $this->resultWith(Severity::Warning)]);
        $failure = new StoredRun(new DateTimeImmutable(), [$this->resultWith(Severity::Warning), $this->resultWith(Severity::Failure)]);

        // test / verify
        $this->assertSame(Severity::Ok, $ok->overall());
        $this->assertTrue($ok->isHealthy());
        $this->assertSame(Severity::Warning, $warning->overall());
        $this->assertTrue($warning->isHealthy());
        $this->assertSame(Severity::Failure, $failure->overall());
        $this->assertFalse($failure->isHealthy());
    }

    #[Test]
    public function summary_countsEverySeverityWithEveryKeyPresent(): void
    {
        // prepare
        $run = new StoredRun(new DateTimeImmutable(), [$this->resultWith(Severity::Ok), $this->resultWith(Severity::Ok), $this->resultWith(Severity::NotAvailable)]);

        // test / verify
        $this->assertSame(['ok' => 2, 'warning' => 0, 'critical' => 0, 'skipped' => 0, 'na' => 1], $run->summary());
    }

    #[Test]
    public function toArrayAndFromArray_roundTripAndFoldTheExceptionIntoTheMessage(): void
    {
        // prepare
        $generatedAt = new DateTimeImmutable('2026-09-23 10:00:00', new \DateTimeZone('Europe/Berlin'));
        $run         = new StoredRun($generatedAt, [
            new CheckResult('Foo\\PhpVersionCheck', Severity::Ok, null, 'Current PHP version is 8.3.1', ['cycle' => '8.3'], 'system:php_version', 'PHP Version', 5),
            new CheckResult('Foo\\CacheCheck', Severity::Failure, new RuntimeException('cache is down'), '', [], null, null, 7),
        ], 42);

        // test
        $restored = StoredRun::fromArray(json_decode(json_encode($run->toArray(), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR));

        // verify
        $this->assertSame($generatedAt->getTimestamp(), $restored->generatedAt->getTimestamp());
        $this->assertSame(42, $restored->durationMs);
        $this->assertCount(2, $restored->results);
        $this->assertSame('system:php_version', $restored->results[0]->identifier());
        $this->assertSame('PHP Version', $restored->results[0]->label());
        $this->assertSame(['cycle' => '8.3'], $restored->results[0]->data);
        $this->assertSame(5, $restored->results[0]->durationMs);
        $this->assertSame(Severity::Failure, $restored->results[1]->severity);
        $this->assertSame('cache is down', $restored->results[1]->reason());
        $this->assertNull($restored->results[1]->failure);
        $this->assertSame('core:cache', $restored->results[1]->identifier());
    }

    #[Test]
    public function find_returnsTheResultWithTheIdentifierOrNull(): void
    {
        // prepare
        $run = new StoredRun(new DateTimeImmutable(), [
            new CheckResult('Foo\\PhpVersionCheck', Severity::Ok, null, '', [], 'system:php_version'),
            new CheckResult('Foo\\DatabaseAccessibleCheck'),
        ]);

        // test / verify
        $this->assertSame('system:php_version', $run->find('system:php_version')?->identifier());
        $this->assertSame('core:database_accessible', $run->find('core:database_accessible')?->identifier());
        $this->assertNull($run->find('nope'));
    }

    #[Test]
    public function fromArray_ignoresMalformedRowsAndSurvivesAMissingDate(): void
    {
        // test
        $run = StoredRun::fromArray(['checks' => ['not a row', ['no' => 'check key'], ['check' => 'Foo\\Bar', 'status' => 'warning']]]);

        // verify
        $this->assertCount(1, $run->results);
        $this->assertSame(Severity::Warning, $run->results[0]->severity);
        $this->assertInstanceOf(DateTimeImmutable::class, $run->generatedAt);
    }

    private function resultWith(Severity $severity): CheckResult
    {
        return new CheckResult('Foo\\SomeCheck', $severity);
    }
}
