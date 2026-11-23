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

use Basilicom\PimcorePluginHealthCheck\Exception\ReportedProblemException;
use Throwable;

/**
 * Base for the reporting checks. Subclasses implement `examine()` and build their Report with the
 * named constructors; timing, exception handling and the bridge to `check(): void` live here.
 */
abstract readonly class AbstractReportingCheck implements ReportingCheckInterface
{
    public function __construct(private bool $enabled)
    {
    }

    abstract public function identifier(): string;

    abstract public function label(): string;

    public function isActive(): bool
    {
        return $this->enabled;
    }

    /**
     * Never throws. A defect inside the check is reported as a Failure that names the exception
     * class but not its message - the message may carry a DSN or a server path.
     */
    final public function inspect(): Report
    {
        try {
            return $this->examine();
        } catch (Throwable $exception) {
            return Report::failure(
                sprintf('Check could not run: %s', (new \ReflectionClass($exception))->getShortName()),
                ['exception' => $exception::class],
            );
        }
    }

    /** @throws ReportedProblemException */
    final public function check(): void
    {
        $report = $this->inspect();

        // Skipped and NotAvailable are not problems: under the plain contract they count as passed
        if ($report->isProblem()) {
            throw new ReportedProblemException($report);
        }
    }

    /**
     * Reads a numeric threshold option; null, an empty string or anything non numeric disables
     * that level. Lets a project switch off the warning or the failure level without a second
     * flag.
     */
    final protected static function threshold(mixed $value): int|float|null
    {
        return is_numeric($value) ? $value + 0 : null;
    }

    abstract protected function examine(): Report;
}
