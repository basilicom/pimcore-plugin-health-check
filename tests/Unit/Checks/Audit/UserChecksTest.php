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

namespace Basilicom\PimcorePluginHealthCheck\Tests\Unit\Checks\Audit;

use Basilicom\PimcorePluginHealthCheck\Checks\Audit\InactiveAdminUsersCheck;
use Basilicom\PimcorePluginHealthCheck\Checks\Audit\UsersWithoutTwoFactorCheck;
use Basilicom\PimcorePluginHealthCheck\Severity;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UserChecksTest extends TestCase
{
    #[Test]
    public function inactiveAdmins_namesTheAccountsAndWarnsFromOne(): void
    {
        // prepare
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn(['alice', 'bob']);

        // test
        $report = (new InactiveAdminUsersCheck(true, $connection, 90, 1, null))->inspect();

        // verify
        $this->assertSame(Severity::Warning, $report->severity);
        $this->assertSame('2 admin users have not logged in for 90 days: alice, bob', $report->message);
    }

    #[Test]
    public function inactiveAdmins_isOkWhenEveryoneLoggedInRecently(): void
    {
        // prepare
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn([]);

        // test / verify
        $this->assertSame('All admin users logged in within the last 90 days.', (new InactiveAdminUsersCheck(true, $connection, 90, 1, null))->inspect()->message);
    }

    #[Test]
    public function withoutTwoFactor_recognisesAnEnabledSecretOnly(): void
    {
        // prepare
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['name' => 'secured', 'twoFactorAuthentication' => '{"required":true,"enabled":true,"secret":"ABC","type":"google"}'],
            ['name' => 'disabled', 'twoFactorAuthentication' => '{"required":false,"enabled":false,"secret":"","type":""}'],
            ['name' => 'legacy', 'twoFactorAuthentication' => null],
            ['name' => 'broken', 'twoFactorAuthentication' => 'not json'],
        ]);

        // test
        $report = (new UsersWithoutTwoFactorCheck(true, $connection, 1, null))->inspect();

        // verify
        $this->assertSame(Severity::Warning, $report->severity);
        $this->assertSame(['disabled', 'legacy', 'broken'], $report->data['users']);
        $this->assertSame(4, $report->data['total_admins']);
    }
}
