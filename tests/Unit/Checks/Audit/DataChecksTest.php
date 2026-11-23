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

use Basilicom\PimcorePluginHealthCheck\Checks\Audit\AssetStorageSizeCheck;
use Basilicom\PimcorePluginHealthCheck\Checks\Audit\AssetsWithoutMetadataCheck;
use Basilicom\PimcorePluginHealthCheck\Checks\Audit\AssetTypesCheck;
use Basilicom\PimcorePluginHealthCheck\Checks\Audit\CustomTemplatesCheck;
use Basilicom\PimcorePluginHealthCheck\Checks\Audit\DataObjectClassesCheck;
use Basilicom\PimcorePluginHealthCheck\Checks\Audit\DocumentsByTypeCheck;
use Basilicom\PimcorePluginHealthCheck\Checks\Audit\DocumentsMissingSeoCheck;
use Basilicom\PimcorePluginHealthCheck\Checks\Audit\LargestTablesCheck;
use Basilicom\PimcorePluginHealthCheck\Checks\Audit\ObjectsPerClassCheck;
use Basilicom\PimcorePluginHealthCheck\Checks\Audit\StaleObjectsCheck;
use Basilicom\PimcorePluginHealthCheck\Checks\Audit\UnpublishedObjectsCheck;
use Basilicom\PimcorePluginHealthCheck\Checks\Audit\VersionsTableCheck;
use Basilicom\PimcorePluginHealthCheck\Severity;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DataChecksTest extends TestCase
{
    #[Test]
    public function versionsTable_gradesTheRowCount(): void
    {
        // test / verify
        $this->assertSame(Severity::Warning, (new VersionsTableCheck(true, $this->fetchOne('600000'), 500000, 1000000))->inspect()->severity);
        $this->assertSame('The versions table holds 600000 rows.', (new VersionsTableCheck(true, $this->fetchOne('600000'), 500000, 1000000))->inspect()->message);
    }

    #[Test]
    public function largestTables_listsTablesWithFormattedSizes(): void
    {
        // prepare
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([['table_name' => 'versions', 'size' => '1073741824'], ['table_name' => 'assets', 'size' => '1048576']]);

        // test
        $report = (new LargestTablesCheck(true, $connection, 10))->inspect();

        // verify
        $this->assertSame(Severity::Ok, $report->severity);
        $this->assertSame('Largest tables: versions (1 GB), assets (1 MB)', $report->message);
    }

    #[Test]
    public function dataObjectClasses_isInformational(): void
    {
        // test / verify
        $this->assertSame('There are 12 DataObject classes in the system', (new DataObjectClassesCheck(true, $this->fetchOne('12')))->inspect()->message);
    }

    #[Test]
    public function objectsPerClass_listsTheLargestAndCanWarnOnEmptyClasses(): void
    {
        // prepare
        $rows       = [['class_name' => 'Product', 'cnt' => '900'], ['class_name' => 'Category', 'cnt' => '40'], ['class_name' => 'Legacy', 'cnt' => '0']];
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn($rows);

        // test
        $quiet = (new ObjectsPerClassCheck(true, $connection, 2, false))->inspect();
        $loud  = (new ObjectsPerClassCheck(true, $connection, 2, true))->inspect();

        // verify
        $this->assertSame(Severity::Ok, $quiet->severity);
        $this->assertSame('940 objects across 3 classes, largest: Product (900), Category (40)', $quiet->message);
        $this->assertSame(Severity::Warning, $loud->severity);
        $this->assertSame('1 classes have no objects: Legacy', $loud->message);
    }

    #[Test]
    public function documentsByType_sumsAndLists(): void
    {
        // prepare
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([['type' => 'page', 'cnt' => '30'], ['type' => 'snippet', 'cnt' => '5']]);

        // test / verify
        $this->assertSame('35 documents: page (30), snippet (5)', (new DocumentsByTypeCheck(true, $connection))->inspect()->message);
    }

    #[Test]
    public function staleObjects_reportsCountsAndPercent(): void
    {
        // prepare
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturnOnConsecutiveCalls('200', '50');

        // test
        $report = (new StaleObjectsCheck(true, $connection, 365, null, null))->inspect();

        // verify
        $this->assertSame('50 of 200 objects have not been modified in the last 365 days.', $report->message);
        $this->assertSame(25.0, $report->data['percent']);
    }

    #[Test]
    public function unpublishedObjects_gradesThePercentage(): void
    {
        // prepare
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturnOnConsecutiveCalls('200', '90');

        // test
        $report = (new UnpublishedObjectsCheck(true, $connection, 40, 60))->inspect();

        // verify
        $this->assertSame(Severity::Warning, $report->severity);
        $this->assertSame('90 of 200 objects (45%) are unpublished.', $report->message);
    }

    #[Test]
    public function assetsWithoutMetadata_countsAssetsWithNoMetadataAtAllByDefault(): void
    {
        // prepare
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturnOnConsecutiveCalls('100', '7');

        // test
        $report = (new AssetsWithoutMetadataCheck(true, $connection, ['image'], [], null, null))->inspect();

        // verify
        $this->assertSame('7 of 100 image assets have no metadata at all.', $report->message);
    }

    #[Test]
    public function assetsWithoutMetadata_bindsTypesNamesAndCountForRequiredNames(): void
    {
        // prepare
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturnCallback(static function (string $sql, array $params = []): string {
            if (str_contains($sql, 'COUNT(DISTINCT m.name)')) {
                self::assertSame(['image', 'alt', 'copyright', 2], $params);

                return '3';
            }

            return '10';
        });

        // test
        $report = (new AssetsWithoutMetadataCheck(true, $connection, ['image'], ['alt', 'copyright'], null, null))->inspect();

        // verify
        $this->assertSame('3 of 10 image assets are missing required metadata (alt, copyright).', $report->message);
    }

    #[Test]
    public function assetTypes_warnsOnDisallowedExtensionsAndListsMimeTypesOtherwise(): void
    {
        // prepare
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([['mimetype' => 'image/jpeg', 'cnt' => '80'], ['mimetype' => null, 'cnt' => '2']]);
        $connection->method('fetchFirstColumn')->willReturn(['/uploads/evil.exe']);
        $clean = $this->createMock(Connection::class);
        $clean->method('fetchAllAssociative')->willReturn([['mimetype' => 'image/jpeg', 'cnt' => '80']]);
        $clean->method('fetchFirstColumn')->willReturn([]);

        // test
        $warning = (new AssetTypesCheck(true, $connection, 10, ['exe']))->inspect();
        $ok      = (new AssetTypesCheck(true, $clean, 10, ['exe']))->inspect();

        // verify
        $this->assertSame(Severity::Warning, $warning->severity);
        $this->assertSame('Found 1 assets with a disallowed file extension: /uploads/evil.exe', $warning->message);
        $this->assertSame('80 assets across 1 MIME types, most common: image/jpeg (80)', $ok->message);
    }

    #[Test]
    public function customTemplates_countsTwigFilesAndTreatsAMissingDirectoryAsZero(): void
    {
        // prepare
        $directory = sys_get_temp_dir() . '/health-check-tpl-' . bin2hex(random_bytes(6));
        mkdir($directory . '/sub', 0o777, true);
        file_put_contents($directory . '/a.html.twig', '');
        file_put_contents($directory . '/sub/b.twig', '');
        file_put_contents($directory . '/c.php', '');

        // test
        $report  = (new CustomTemplatesCheck(true, $directory))->inspect();
        $missing = (new CustomTemplatesCheck(true, $directory . '/nope'))->inspect();

        // verify
        array_map('unlink', [$directory . '/a.html.twig', $directory . '/sub/b.twig', $directory . '/c.php']);
        rmdir($directory . '/sub');
        rmdir($directory);
        $this->assertSame(2, $report->data['count']);
        $this->assertSame(0, $missing->data['count']);
        $this->assertSame(Severity::Ok, $missing->severity);
    }

    #[Test]
    public function documentsMissingSeo_reportsThePublishedPagesWithoutTitleOrDescription(): void
    {
        // prepare
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturnOnConsecutiveCalls('40', '3', '5', '6');

        // test
        $report = (new DocumentsMissingSeoCheck(true, $connection, true, 1, null))->inspect();

        // verify
        $this->assertSame(Severity::Warning, $report->severity);
        $this->assertSame('6 of 40 published pages are missing a title or meta description.', $report->message);
        $this->assertSame(3, $report->data['missing_title']);
        $this->assertSame(5, $report->data['missing_description']);
    }

    #[Test]
    public function assetStorageSize_countsAssetsAndReportsNaSizeWhenTheDirectoryIsMissing(): void
    {
        // test
        $report = (new AssetStorageSizeCheck(true, $this->fetchOne('321'), sys_get_temp_dir() . '/nope-' . bin2hex(random_bytes(4)), null, null, 5))->inspect();

        // verify
        $this->assertSame(Severity::Ok, $report->severity);
        $this->assertSame('There are 321 assets in the system (storage size n/a)', $report->message);
    }

    private function fetchOne(mixed $value): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn($value);

        return $connection;
    }
}
