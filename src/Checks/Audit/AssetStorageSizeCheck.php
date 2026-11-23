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
use Basilicom\PimcorePluginHealthCheck\Util\Bytes;
use Basilicom\PimcorePluginHealthCheck\Util\DirectorySize;
use Doctrine\DBAL\Connection;

/**
 * Not to be confused with the core AssetStorageCheck, which probes writability. This one counts
 * assets and sizes the storage directory - Pimcore 11+ keeps no file size in the assets table.
 */
final readonly class AssetStorageSizeCheck extends AbstractReportingCheck
{
    public function __construct(
        bool $enabled,
        private Connection $connection,
        private string $storagePath,
        private int|float|null $warningThreshold,
        private int|float|null $failureThreshold,
        private int $duTimeoutSeconds,
    ) {
        parent::__construct($enabled);
    }

    public function identifier(): string
    {
        return 'data:assets_storage';
    }

    public function label(): string
    {
        return 'Assets Storage';
    }

    protected function examine(): Report
    {
        $count = (int)$this->connection->fetchOne("SELECT COUNT(*) FROM assets WHERE `type` <> 'folder'");
        $size  = DirectorySize::measure($this->storagePath, $this->duTimeoutSeconds);
        $data  = ['count' => $count, 'path' => $this->storagePath, 'size_bytes' => $size];

        if ($size === null) {
            return Report::ok(sprintf('There are %d assets in the system (storage size n/a)', $count), $data);
        }

        $data['size_human'] = Bytes::format($size);

        return Report::graded(
            $size,
            $this->warningThreshold,
            $this->failureThreshold,
            sprintf('There are %d assets in the system using %s', $count, Bytes::format($size)),
            $data
        );
    }
}
