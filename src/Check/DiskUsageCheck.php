<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

use Basilicom\PimcorePluginHealthCheck\Util\Bytes;

final class DiskUsageCheck extends AbstractCheck
{
    public function getIdentifier(): string
    {
        return 'device:disk_usage';
    }

    public function getLabel(): string
    {
        return 'Disk Usage';
    }

    protected function doRun(): CheckResult
    {
        $path = (string) $this->option('path', '/');
        $total = @disk_total_space($path);
        $free = @disk_free_space($path);

        if ($total === false || $free === false || $total <= 0) {
            return $this->na(sprintf('Could not determine disk usage for %s (n/a)', $path), ['path' => $path]);
        }

        $used = $total - $free;
        $percent = (int) round($used / $total * 100);

        return $this->thresholdResult(
            $percent,
            $this->threshold('warning_threshold'),
            $this->threshold('critical_threshold'),
            sprintf('Disk usage is %d%%', $percent),
            [
                'path' => $path,
                'used_percent' => $percent,
                'used_bytes' => (int) $used,
                'free_bytes' => (int) $free,
                'total_bytes' => (int) $total,
                'free_human' => Bytes::format($free),
                'total_human' => Bytes::format($total),
            ],
        );
    }
}
