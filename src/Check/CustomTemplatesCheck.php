<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * Informational check: number of project Twig templates, a code volume metric for the custom UI
 * (PF-88 metric 44).
 */
final class CustomTemplatesCheck extends AbstractCheck
{
    public function getIdentifier(): string
    {
        return 'data:custom_templates';
    }

    public function getLabel(): string
    {
        return 'Custom Templates';
    }

    protected function doRun(): CheckResult
    {
        $directory = (string) $this->option('templates_dir', '');

        if ($directory === '' || !is_dir($directory)) {
            return $this->ok(
                sprintf('Templates directory %s does not exist (0 templates)', $directory),
                ['templates_dir' => $directory, 'count' => 0],
            );
        }

        try {
            $count = 0;
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY,
                RecursiveIteratorIterator::CATCH_GET_CHILD,
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && strtolower($file->getExtension()) === 'twig') {
                    ++$count;
                }
            }
        } catch (Throwable $throwable) {
            return $this->na(sprintf('Could not scan %s: %s (n/a)', $directory, $throwable->getMessage()));
        }

        return $this->ok(
            sprintf('There are %d Twig templates in %s', $count, $directory),
            ['templates_dir' => $directory, 'count' => $count],
        );
    }
}
