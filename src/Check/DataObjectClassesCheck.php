<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

use Doctrine\DBAL\Connection;

final class DataObjectClassesCheck extends AbstractCheck
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
        return 'data:dataobject_classes';
    }

    public function getLabel(): string
    {
        return 'DataObject Classes';
    }

    protected function doRun(): CheckResult
    {
        $count = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM classes');

        return $this->ok(
            sprintf('There are %d DataObject classes in the system', $count),
            ['count' => $count],
        );
    }
}
