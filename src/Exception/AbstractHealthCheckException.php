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

namespace Basilicom\PimcorePluginHealthCheck\Exception;

use Basilicom\PimcorePluginHealthCheck\Severity;
use Exception;
use Throwable;

abstract class AbstractHealthCheckException extends Exception
{
    public function __construct(
        string $message = '',
        ?Throwable $previous = null,
        private readonly Severity $severity = Severity::Failure,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function severity(): Severity
    {
        return $this->severity;
    }
}
