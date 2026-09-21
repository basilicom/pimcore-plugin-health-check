<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

final class FilesystemCheck extends AbstractCheck
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config, private readonly string $temporaryDirectory)
    {
        parent::__construct($config);
    }

    public function getIdentifier(): string
    {
        return 'system:filesystem';
    }

    public function getLabel(): string
    {
        return 'Filesystem';
    }

    protected function doRun(): CheckResult
    {
        $directory = defined('PIMCORE_SYSTEM_TEMP_DIRECTORY')
            ? (string) constant('PIMCORE_SYSTEM_TEMP_DIRECTORY')
            : $this->temporaryDirectory;

        if (!is_dir($directory) || !is_writable($directory)) {
            return $this->critical(
                sprintf('Temporary directory %s is not writeable.', $directory),
                ['directory' => $directory],
            );
        }

        $file = rtrim($directory, '/') . '/health_check_write.tmp';
        $expected = sha1(uniqid('', true));

        file_put_contents($file, $expected);
        $actual = file_get_contents($file);
        unlink($file);

        if ($expected !== $actual) {
            return $this->critical('Error writing/reading a file.', ['directory' => $directory]);
        }

        return $this->ok('Filesystem is writeable.', ['directory' => $directory]);
    }
}
