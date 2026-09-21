<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

use Pimcore\Cache;

final class CacheCheck extends AbstractCheck
{
    private const CACHE_KEY = 'basilicom_health_check_cache_test';

    public function getIdentifier(): string
    {
        return 'system:cache';
    }

    public function getLabel(): string
    {
        return 'Cache';
    }

    protected function doRun(): CheckResult
    {
        $expected = 'This is a test for the Pimcore cache.';

        Cache::setForceImmediateWrite(true);
        Cache::save($expected, self::CACHE_KEY, ['check_write'], null, 0, true);
        $actual = Cache::load(self::CACHE_KEY);
        Cache::remove(self::CACHE_KEY);

        if ($expected !== $actual) {
            return $this->critical('Pimcore cache failure - content mismatch.');
        }

        return $this->ok('Pimcore cache is writeable.');
    }
}
