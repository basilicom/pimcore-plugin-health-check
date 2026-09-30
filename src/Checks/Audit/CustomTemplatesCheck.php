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
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final readonly class CustomTemplatesCheck extends AbstractReportingCheck
{
    public function __construct(bool $enabled, private string $templatesDirectory)
    {
        parent::__construct($enabled);
    }

    public function identifier(): string
    {
        return 'data:custom_templates';
    }

    public function label(): string
    {
        return 'Custom Templates';
    }

    protected function examine(): Report
    {
        $data = ['templates_dir' => $this->templatesDirectory];

        if (!is_dir($this->templatesDirectory)) {
            return Report::ok('The templates directory does not exist (0 templates)', $data + ['count' => 0]);
        }

        $count    = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->templatesDirectory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
            RecursiveIteratorIterator::CATCH_GET_CHILD
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'twig') {
                ++$count;
            }
        }

        return Report::ok(sprintf('There are %d Twig templates in the project', $count), $data + ['count' => $count]);
    }
}
