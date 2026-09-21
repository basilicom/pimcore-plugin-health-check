<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

use Basilicom\PimcorePluginHealthCheck\Util\Bytes;
use Basilicom\PimcorePluginHealthCheck\Util\DirectorySize;
use Doctrine\DBAL\Connection;
use Throwable;

/**
 * Informational check on the asset inventory.
 *
 * Pimcore 11/12 dropped the `assets.filesize` column, so the storage size is taken from the
 * configured asset storage directory when the column is not present.
 */
final class AssetsStorageCheck extends AbstractCheck
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config, private readonly Connection $connection)
    {
        parent::__construct($config);
    }

    public function getIdentifier(): string
    {
        return 'data:assets_storage';
    }

    public function getLabel(): string
    {
        return 'Assets Storage';
    }

    protected function doRun(): CheckResult
    {
        $count = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM assets');
        [$size, $source] = $this->determineSize();

        $data = ['count' => $count, 'size_bytes' => $size, 'size_source' => $source];

        if ($size === null) {
            return $this->ok(
                sprintf('There are %d assets in the system (storage size n/a)', $count),
                $data,
            );
        }

        $data['size_human'] = Bytes::format($size);

        return $this->thresholdResult(
            $size,
            $this->threshold('warning_threshold'),
            $this->threshold('critical_threshold'),
            sprintf('There are %d assets in the system using %s', $count, Bytes::format($size)),
            $data,
        );
    }

    /**
     * @return array{0: int|null, 1: string}
     */
    private function determineSize(): array
    {
        if ($this->hasFilesizeColumn()) {
            return [
                (int) $this->connection->fetchOne('SELECT COALESCE(SUM(filesize), 0) FROM assets'),
                'database',
            ];
        }

        $path = (string) $this->option('storage_path', '');

        if ($path === '') {
            return [null, 'unavailable'];
        }

        $size = DirectorySize::calculate($path);

        return [$size, $size === null ? 'unavailable' : 'filesystem'];
    }

    private function hasFilesizeColumn(): bool
    {
        try {
            return $this->connection->fetchOne(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE table_schema = DATABASE() AND table_name = 'assets' AND column_name = 'filesize'",
            ) > 0;
        } catch (Throwable) {
            return false;
        }
    }
}
