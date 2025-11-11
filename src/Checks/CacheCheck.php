<?php

namespace Basilicom\PimcorePluginHealthCheck\Checks;

use Basilicom\PimcorePluginHealthCheck\Exception\CacheNotWriteableException;
use Basilicom\PimcorePluginHealthCheck\Services\HealthCheckInterface;
use Pimcore\Cache;

class CacheCheck implements HealthCheckInterface
{
    public function check(): void
    {
        try {
            $putData = 'This is a test for the Pimcore cache.';

            Cache::setForceImmediateWrite(true);
            Cache::save(
                $putData,
                $putData,
                ['check_write'],
                null,
                0,
                true
            );

            $getData = Cache::load($putData);

            Cache::clearTag('check_write');
        } catch (\Exception $exception) {
            throw new CacheNotWriteableException('Pimcore cache error [' . $exception->getMessage() . ']');
        }

        if ($putData !== $getData) {
            throw new CacheNotWriteableException('Pimcore cache failure - content mismatch.');
        }
    }

    public function isActive(): bool
    {
        return true;
    }
}