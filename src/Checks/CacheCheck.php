<?php

namespace Basilicom\PimcorePluginHealthCheck\Checks;

use Basilicom\PimcorePluginHealthCheck\Exception\CacheNotWriteableException;
use Pimcore\Cache;

class CacheCheck implements CheckInterface
{
    use ConfigurationTrait;

    public function check(): void
    {
        try {
            $putData = 'This is a test for the Pimcore cache.';
            $cacheKey = 'cache_test';

            Cache::setForceImmediateWrite(true);
            Cache::save(
                $putData,
                $cacheKey,
                ['check_write'],
                null,
                0,
                true
            );

            $getData = Cache::load($cacheKey);

            Cache::remove($cacheKey);
        } catch (\Exception $exception) {
            throw new CacheNotWriteableException('Pimcore cache error [' . $exception->getMessage() . ']');
        }

        if ($putData !== $getData) {
            throw new CacheNotWriteableException('Pimcore cache failure - content mismatch.');
        }
    }

    public function isActive(): bool
    {
        return $this->isEnabled('cache_check_enabled');
    }
}