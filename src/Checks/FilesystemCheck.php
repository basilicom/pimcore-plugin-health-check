<?php

namespace Basilicom\PimcorePluginHealthCheck\Checks;

use Basilicom\PimcorePluginHealthCheck\Exception\FilesystemNotWriteableException;
use Basilicom\PimcorePluginHealthCheck\Services\HealthCheckInterface;
use Exception;

class FilesystemCheck implements HealthCheckInterface
{
    public function check(): void
    {
        try {
            $putData = sha1(time());
            file_put_contents(PIMCORE_SYSTEM_TEMP_DIRECTORY . '/check_write.tmp', $putData);
            $getData = file_get_contents(PIMCORE_SYSTEM_TEMP_DIRECTORY . '/check_write.tmp');
            unlink(PIMCORE_SYSTEM_TEMP_DIRECTORY . '/check_write.tmp');
        } catch (Exception $exception) {
            throw new FilesystemNotWriteableException('Unable to read/write a file. [' . $exception->getMessage() . ']');
        }

        if ($putData !== $getData) {
            throw new FilesystemNotWriteableException('Error writing/reading a file.');
        }
    }

    public function isActive(): bool
    {
        return true;
    }
}