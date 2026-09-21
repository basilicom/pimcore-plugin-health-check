<?php

declare(strict_types=1);

namespace Basilicom\PimcorePluginHealthCheck\Check;

use Basilicom\PimcorePluginHealthCheck\Services\EndOfLifeDateClientInterface;
use Doctrine\DBAL\Connection;

final class MysqlVersionCheck extends AbstractVersionCheck
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        array $config,
        private readonly Connection $connection,
        private readonly EndOfLifeDateClientInterface $endOfLifeDateClient,
    ) {
        parent::__construct($config);
    }

    public function getIdentifier(): string
    {
        return 'system:mysql_version';
    }

    public function getLabel(): string
    {
        return 'MySQL Version';
    }

    protected function doRun(): CheckResult
    {
        $reported = (string) $this->connection->fetchOne('SELECT VERSION()');
        $numeric = $this->numericVersion($reported);
        $isMariaDb = stripos($reported, 'mariadb') !== false;
        $data = ['reported' => $reported, 'server' => $isMariaDb ? 'MariaDB' : 'MySQL'];

        $required = $this->option('version');

        if ($required !== null && $required !== '') {
            // the message reports the full server string, e.g. "10.11.18-MariaDB-0+deb12u1",
            // while the comparison uses the leading numeric part only
            return $this->staticResult(
                $numeric,
                (string) $required,
                (string) $this->option('operator', '>='),
                'MySQL',
                $data,
                $reported,
            );
        }

        return $this->endOfLifeResult(
            $this->endOfLifeDateClient,
            $isMariaDb ? 'mariadb' : 'mysql',
            $reported,
            $this->releaseCycle($numeric),
            'MySQL',
            $data,
        );
    }

    private function numericVersion(string $reported): string
    {
        if (preg_match('/^\d+(\.\d+)*/', $reported, $matches) === 1) {
            return $matches[0];
        }

        return $reported;
    }
}
