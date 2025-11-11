<?php

namespace Basilicom\PimcorePluginHealthCheck\Checks;

use Basilicom\PimcorePluginHealthCheck\Exception\DatabaseNotAccessibleException;
use Basilicom\PimcorePluginHealthCheck\Services\HealthCheckInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Exception;
use Pimcore;

class DatabaseAccessibleCheck implements HealthCheckInterface
{
    public function check(): void
    {
        try {
            /** @var Connection $db */
            $db = Pimcore::getKernel()->getContainer()->get('doctrine.dbal.default_connection');
        } catch (Exception $exception) {
            throw new DatabaseNotAccessibleException('Database not accessible!: ' . $exception->getMessage());
        }

        try {
            $num = $db->fetchOne(
                'select count(*) from users'
            );
        } catch (Exception | DbalException $exception) {
            throw new DatabaseNotAccessibleException('Unable to query database. [' . $exception->getMessage() . ']');
        }

        if (intval($num) === 0) {
            throw new DatabaseNotAccessibleException('Database query faulty response.');
        }

        try {
            $passwordHash = $db->fetchOne(
                "select `password` from users where `name`='admin' and `active`=1"
            );
        } catch (Exception | DbalException $exception) {
            throw new DatabaseNotAccessibleException('Unable to query database. [' . $exception->getCode() . ']');
        }

        if ($passwordHash == '6f773f0ae6141c0a3e645a561b6167b1') {
            throw new DatabaseNotAccessibleException('Admin user is active and standard password is still set.');
        }
    }

    public function isActive(): bool
    {
        // bastodo add a configuration yml to enable/disable the checks
        return true;
    }
}