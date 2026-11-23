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
use Doctrine\DBAL\Connection;

final readonly class DocumentsMissingSeoCheck extends AbstractReportingCheck
{
    public function __construct(
        bool $enabled,
        private Connection $connection,
        private bool $publishedOnly,
        private int|float|null $warningThreshold,
        private int|float|null $failureThreshold,
    ) {
        parent::__construct($enabled);
    }

    public function identifier(): string
    {
        return 'data:documents_missing_seo';
    }

    public function label(): string
    {
        return 'Documents Missing SEO Metadata';
    }

    protected function examine(): Report
    {
        $from = "FROM documents d JOIN documents_page p ON p.id = d.id WHERE d.type = 'page'" . ($this->publishedOnly ? ' AND d.published = 1' : '');

        $total   = (int)$this->connection->fetchOne("SELECT COUNT(*) $from");
        $noTitle = (int)$this->connection->fetchOne("SELECT COUNT(*) $from AND (p.title IS NULL OR p.title = '')");
        $noDescr = (int)$this->connection->fetchOne("SELECT COUNT(*) $from AND (p.description IS NULL OR p.description = '')");
        $missing = (int)$this->connection->fetchOne("SELECT COUNT(*) $from AND (p.title IS NULL OR p.title = '' OR p.description IS NULL OR p.description = '')");

        return Report::graded(
            $missing,
            $this->warningThreshold,
            $this->failureThreshold,
            sprintf('%d of %d %spages are missing a title or meta description.', $missing, $total, $this->publishedOnly ? 'published ' : ''),
            ['missing' => $missing, 'missing_title' => $noTitle, 'missing_description' => $noDescr, 'total' => $total, 'published_only' => $this->publishedOnly]
        );
    }
}
