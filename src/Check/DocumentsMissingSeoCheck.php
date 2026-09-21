<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

use Doctrine\DBAL\Connection;

/**
 * Pages without a title or meta description (PF-88 metric 46).
 */
final class DocumentsMissingSeoCheck extends AbstractCheck
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
        return 'data:documents_missing_seo';
    }

    public function getLabel(): string
    {
        return 'Documents Missing SEO Metadata';
    }

    protected function doRun(): CheckResult
    {
        $publishedOnly = (bool) $this->option('published_only', true);
        $scope = "d.type = 'page'" . ($publishedOnly ? ' AND d.published = 1' : '');
        $from = "FROM documents d JOIN documents_page p ON p.id = d.id WHERE $scope";

        $total = (int) $this->connection->fetchOne("SELECT COUNT(*) $from");
        $missingTitle = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) $from AND (p.title IS NULL OR p.title = '')",
        );
        $missingDescription = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) $from AND (p.description IS NULL OR p.description = '')",
        );
        $missing = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) $from AND (p.title IS NULL OR p.title = '' OR p.description IS NULL OR p.description = '')",
        );

        return $this->thresholdResult(
            $missing,
            $this->threshold('warning_threshold'),
            $this->threshold('critical_threshold'),
            sprintf(
                '%d of %d %spages are missing a title or meta description.',
                $missing,
                $total,
                $publishedOnly ? 'published ' : '',
            ),
            [
                'missing' => $missing,
                'missing_title' => $missingTitle,
                'missing_description' => $missingDescription,
                'total' => $total,
                'published_only' => $publishedOnly,
            ],
        );
    }
}
