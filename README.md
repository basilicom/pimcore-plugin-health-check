# Pimcore Plugin Health Check
License: MIT — see [LICENSE.txt](LICENSE.txt)

## Version information

| Bundle Version | PHP  | Pimcore      |
|----------------|------|--------------|
| ^2.0           | ^8.3 | ^11 or ^12.0 |
| ^1.0           | ^8.3 | ^11 or ^12.0 |

## Why?

This Pimcore bundle runs a set of system health checks and exposes the results in three ways:

| Endpoint                            | Purpose                                                       |
|-------------------------------------|---------------------------------------------------------------|
| `GET /health-check-status`          | plain text `SUCCESS` / `FAILURE: …` for Pingdom, StatusCake, … |
| `GET /health-check`                 | HTML overview ("System Health Status") for humans              |
| `GET /health-check/api`             | JSON for monitoring tools and dashboards                       |

Services like Pingdom and StatusCake should detect a specific success state instead of
looking for closing body tags or status codes. The checklist dashboard makes it visible
*which* check failed instead of leaving the endpoint a black box.

## Installation

```
composer require basilicom/pimcore-plugin-health-check
```

### Activate the bundle

```php
// config/bundles.php
return [
    // ...
    \Basilicom\PimcorePluginHealthCheck\PimcorePluginHealthCheckBundle::class => ['all' => true],
];
```

## Endpoints

### `GET /health-check-status` (monitoring)

Unchanged since 1.x: always HTTP 200, no-cache headers, body is either `SUCCESS` or
`FAILURE: <label>: <message> | <label>: <message>`.

A check result of `check_result_critical` makes the endpoint answer `FAILURE`. Warnings and
the informational results do not, so they do not page anyone at night.

### `GET /health-check` (dashboard)

The overview page titled **System Health Status**: one row per check with the columns
`Status`, `Check` and `Message`, coloured red / yellow / green / grey by result. The blue
**Details** button in the top right toggles the measured values and the runtime of every
check (collapsed by default, no JavaScript involved - it is a pure CSS disclosure).

Rows are ordered project checks first, bundle checks second, each group alphabetically.
The page uses inline CSS only and loads no external asset, so it works behind a firewall.
The layout follows the reference screenshot attached to PF-415
(`Bildschirmfoto 2026-08-26 um 14.37.05.png`).

### `GET /health-check/api` (JSON)

```json
{
  "status": "critical",
  "healthy": false,
  "generated_at": "2026-09-14T10:00:00+02:00",
  "summary": {"ok": 15, "warning": 2, "critical": 3, "skipped": 0, "na": 1},
  "checks": [
    {
      "identifier": "logs:application_log_errors",
      "label": "Application Log Errors",
      "status": "critical",
      "message": "Found 2379 errors in application logs in the last 24 hours.",
      "data": {"error_count": 2379, "lookback_hours": 24},
      "duration_ms": 12
    }
  ]
}
```

`?check=<identifier>` restricts the response to a single check.

An authorised request always answers HTTP 200 regardless of the health status - monitoring
tools evaluate the body, not the status code. The only exceptions are HTTP 401 for an
unauthorised request and HTTP 404 for a `?check=` identifier that does not exist or belongs
to a disabled check, so a typo in a monitoring configuration does not silently look healthy.

### Access control

The dashboard and the API are **never public**. A request is authorised when it either

* carries the configured API key in the `X-API-KEY` header or the `api_key` query parameter, or
* comes from a logged in Pimcore admin user (`ROLE_PIMCORE_USER` / an active Pimcore admin session).

Everything else gets HTTP 401. When no API key is configured, only the admin session works.
The key is read from the `HEALTH_CHECK_API_KEY` environment variable by default:

```
HEALTH_CHECK_API_KEY=a-long-random-string
```

## The checks

| Identifier | Label | Notes |
|---|---|---|
| `system:pimcore_configuration` | Pimcore Configuration | environment and system configuration are readable |
| `system:database_accessible` | Database Accessible | database reachable, default `admin` user not active |
| `system:filesystem` | Filesystem | write/read round trip in the Pimcore temp directory |
| `system:cache` | Cache | write/read round trip through the Pimcore cache |
| `system:robots_txt` | Robots.txt | exists and does not `Disallow: /` |
| `system:app_environment` | App Environment | ok when the running environment equals the expected one |
| `system:php_version` | PHP Version | hard coded comparison or dynamic endoflife.date rule |
| `system:mysql_version` | MySQL Version | hard coded comparison (default `>= 10.5`) or dynamic rule |
| `system:https_connection` | HTTPS Connection | skipped on CLI, critical for a plain HTTP request |
| `system:doctrine_migrations` | Doctrine Migrations | critical on pending or executed-but-missing migrations |
| `device:disk_usage` | Disk Usage | percentage used on the project partition |
| `device:hosting_size` | Hosting Size | size of the project directory |
| `device:database_size` | Database Size | `data_length + index_length` of the current schema |
| `device:database_table_size` | Database Table Size | per table, message lists the offending tables |
| `device:thumbnail_storage` | Thumbnail Storage | size of the generated thumbnails |
| `pimcore:version` | Pimcore Version | informational |
| `pimcore:bundles` | Pimcore Bundles | number of active Pimcore bundles |
| `pimcore:areabricks` | Pimcore Areabricks | number of registered areabricks |
| `pimcore:users` | Pimcore Users | number of Pimcore users |
| `pimcore:element_count` | Pimcore Element Count | objects + assets + documents |
| `maintenance:last_run_age` | Pimcore Maintenance | age of the last maintenance run |
| `maintenance:messenger_message_count` | Messenger Message Count | rows in `messenger_messages` |
| `maintenance:messenger_message_age` | Messenger Message Age | age of the oldest queued message |
| `maintenance:failed_messages` | Failed Messenger Messages | messages in a `failed` / `*_failed` transport |
| `logs:log_file_errors` | Log File Errors (prod.log & php.log) | error lines in the last N hours |
| `logs:application_log_errors` | Application Log Errors | error rows in `application_logs` |
| `security:composer_audit` | Composer Audit | `composer audit`, `n/a` when composer is unavailable |
| `security:composer_outdated` | Composer Outdated Packages | `composer outdated --direct` |
| `security:inactive_admin_users` | Inactive Admin Users | admins without a login in the last N days |
| `security:users_without_2fa` | Admins Without 2FA | admins without a TOTP secret |
| `security:debug_mode` | Debug Mode | critical when `kernel.debug` is on in `prod` |
| `database:versions_table_bloat` | Versions Table | rows in `versions` |
| `database:largest_tables` | Largest Tables | informational top 10 |
| `data:dataobject_classes` | DataObject Classes | informational |
| `data:assets_storage` | Assets Storage | asset count and storage size |
| `data:objects_per_class` | DataObjects per Class | informational, optional warning on empty classes |
| `data:documents_by_type` | Documents by Type | informational |
| `data:stale_objects` | Stale Objects | objects not modified for N days (default 365) |
| `data:unpublished_objects` | Unpublished Objects | unpublished ratio, thresholds in percent |
| `data:assets_without_metadata` | Assets Without Metadata | image assets without (required) metadata |
| `data:asset_types` | Asset Types | MIME distribution, warning on disallowed extensions |
| `data:custom_templates` | Custom Templates | number of Twig templates in `templates/` |
| `data:documents_missing_seo` | Documents Missing SEO Metadata | published pages without title or description |

Every check reports one of five statuses, rendered with the labels known from
`instride/pimcore-monitor`: `check_result_ok`, `check_result_warning`,
`check_result_critical`, `check_result_skipped`, `check_result_na`.

### Checks that shell out or leave the server

Three checks are slower or need connectivity. They never turn a network problem into a
critical result (they report `check_result_na` instead), but you may still want to disable
them on a locked down host:

* `security:composer_audit` and `security:composer_outdated` run the `composer` binary via
  `symfony/process` (default timeout 60 s each).
* `system:php_version` (and `system:mysql_version` when its `version` is set to `null`)
  query `https://endoflife.date`. Responses are cached in `cache.app` for
  `external_lookups.cache_ttl` seconds. Set `external_lookups.enabled: false` to switch
  the lookups off entirely.
* `device:hosting_size`, `device:thumbnail_storage`, `data:assets_storage` and
  `data:custom_templates` walk a directory tree (using `du` when available).

### Dynamic version rule

When `checks.php_version.version` is `null` (the default), the check fetches
`https://endoflife.date/api/php.json` and grades the running version:

* **critical** when the running release cycle is past its EOL date,
* **warning** when a newer supported release cycle exists,
* **ok** when the running cycle is the latest supported one,
* **n/a** on any network or parse error.

Setting `version` to a concrete value switches to the hard coded comparison using `operator`.
The same applies to `checks.mysql_version` (product `mariadb` or `mysql`, chosen from the
reported server version); its default is the hard coded `>= 10.5`.

## Configuration

Full set of defaults:

```yaml
pimcore_plugin_health_check:
    dashboard:
        enabled: true
        path: /health-check
    api:
        enabled: true
        path: /health-check/api
        api_key: '%env(default::HEALTH_CHECK_API_KEY)%'
    external_lookups:
        enabled: true
        timeout: 3
        cache_ttl: 86400
    checks:
        pimcore_configuration:
            enabled: true
        database_accessible:
            enabled: true
        filesystem:
            enabled: true
        cache:
            enabled: true
        robots_txt:
            enabled: true
            path: ~
        app_environment:
            enabled: true
            environment: '%kernel.environment%'
        php_version:
            enabled: true
            version: ~
            operator: '>='
        mysql_version:
            enabled: true
            version: '10.5'
            operator: '>='
        https_connection:
            enabled: true
        doctrine_migrations:
            enabled: true
        disk_usage:
            enabled: true
            warning_threshold: 90
            critical_threshold: 95
            path: '%kernel.project_dir%'
        hosting_size:
            enabled: true
            warning_threshold: 48318382080
            critical_threshold: 53687091200
            path: '%kernel.project_dir%'
        database_size:
            enabled: true
            warning_threshold: 9878424780
            critical_threshold: 10737418240
        database_table_size:
            enabled: true
            warning_threshold: 943718400
            critical_threshold: 1073741824
        thumbnail_storage:
            enabled: true
            warning_threshold: ~
            critical_threshold: ~
            path: '%kernel.project_dir%/public/var/tmp/thumbnails'
        pimcore_version:
            enabled: true
        pimcore_bundles:
            enabled: true
        pimcore_areabricks:
            enabled: true
        pimcore_users:
            enabled: true
        pimcore_element_count:
            enabled: true
            warning_threshold: 100000
            critical_threshold: 150000
        maintenance_last_run:
            enabled: true
            warning_threshold: 120
            critical_threshold: 1440
        messenger_message_count:
            enabled: true
            warning_threshold: 500
            critical_threshold: 1000
        messenger_message_age:
            enabled: true
            warning_threshold: 60
            critical_threshold: 180
        failed_messages:
            enabled: true
            warning_threshold: 1
            critical_threshold: 50
        log_file_errors:
            enabled: true
            log_dir: '%kernel.logs_dir%'
            files:
                - prod.log
                - php.log
            lookback_hours: 24
            warning_threshold: 10
            critical_threshold: 50
            max_bytes: 5242880
        application_log_errors:
            enabled: true
            lookback_hours: 24
            warning_threshold: 10
            critical_threshold: 50
        composer_audit:
            enabled: true
            timeout: 60
            composer_binary: composer
            working_directory: '%kernel.project_dir%'
        composer_outdated:
            enabled: true
            timeout: 60
            composer_binary: composer
            working_directory: '%kernel.project_dir%'
            warning_threshold: 10
            critical_threshold: 25
        inactive_admin_users:
            enabled: true
            days: 90
            warning_threshold: 1
            critical_threshold: ~
        users_without_2fa:
            enabled: true
            warning_threshold: 1
            critical_threshold: ~
        debug_mode:
            enabled: true
        versions_table_bloat:
            enabled: true
            warning_threshold: 500000
            critical_threshold: 1000000
        largest_tables:
            enabled: true
            limit: 10
        dataobject_classes:
            enabled: true
        assets_storage:
            enabled: true
            warning_threshold: ~
            critical_threshold: ~
            storage_path: '%kernel.project_dir%/var/assets'
        objects_per_class:
            enabled: true
            limit: 10
            warn_on_empty_classes: false
        documents_by_type:
            enabled: true
        stale_objects:
            enabled: true
            days: 365
            warning_threshold: ~
            critical_threshold: ~
        unpublished_objects:
            enabled: true
            warning_threshold: ~
            critical_threshold: ~
        assets_without_metadata:
            enabled: true
            asset_types:
                - image
            metadata_names: {  }
            warning_threshold: ~
            critical_threshold: ~
        asset_types:
            enabled: true
            limit: 10
            disallowed_extensions:
                - exe
                - bat
                - cmd
                - com
                - msi
                - dll
                - sh
                - ps1
                - scr
        custom_templates:
            enabled: true
            templates_dir: '%kernel.project_dir%/templates'
        documents_missing_seo:
            enabled: true
            published_only: true
            warning_threshold: ~
            critical_threshold: ~
```

## Adding your own check

Implement `CheckInterface` (or extend `AbstractCheck`) and register the class as a service.
`registerForAutoconfiguration()` tags it with `pimcore_plugin_health_check.check` automatically,
so nothing else is needed - it shows up on the dashboard, in the API, in the console command
and, if it fails, in `/health-check-status`.

```php
<?php

declare(strict_types=1);

namespace App\Check;

use Basilicom\PimcorePluginHealthCheck\Check\AbstractCheck;
use Basilicom\PimcorePluginHealthCheck\Check\CheckResult;
use Doctrine\DBAL\Connection;

final class ImportQueueCheck extends AbstractCheck
{
    public function __construct(
        array $config,
        private readonly Connection $connection,
    ) {
        parent::__construct($config);
    }

    public function getIdentifier(): string
    {
        return 'import:queue_length';
    }

    public function getLabel(): string
    {
        return 'Import Queue';
    }

    protected function doRun(): CheckResult
    {
        $count = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM my_import_queue');

        return $this->thresholdResult(
            $count,
            $this->threshold('warning_threshold'),
            $this->threshold('critical_threshold'),
            sprintf('Import queue has %d entries.', $count),
            ['count' => $count],
        );
    }
}
```

```yaml
# config/services.yaml
services:
    App\Check\ImportQueueCheck:
        arguments:
            $config:
                enabled: true
                warning_threshold: 500
                critical_threshold: 1000
```

Helpers available in `AbstractCheck`:

| Helper | Result |
|---|---|
| `ok($message, $data)` | `check_result_ok` |
| `warning($message, $data)` | `check_result_warning` |
| `critical($message, $data)` | `check_result_critical` |
| `skipped($message, $data)` | `check_result_skipped` |
| `na($message, $data)` | `check_result_na` |
| `thresholdResult($value, $warning, $critical, $message, $data)` | graded against both thresholds |
| `option($key, $default)` / `threshold($key)` | read from the injected `$config` |

`thresholdResult()` treats the thresholds as inclusive. When the critical threshold is
*lower* than the warning threshold it grades the other way round ("less is worse"), which
is handy for free space or remaining quota.

`doRun()` may throw - `AbstractCheck::run()` catches everything and turns it into a critical
result, so a broken check can never take the monitoring endpoint down.

## Migrating from 1.x

**Breaking:** `Basilicom\PimcorePluginHealthCheck\Checks\CheckInterface` (with
`check(): void` / `isActive(): bool`) has been replaced by
`Basilicom\PimcorePluginHealthCheck\Check\CheckInterface`. The old `Checks\` namespace, the
`ConfigurationTrait` and the per-check exception classes are gone. Projects that implemented
the old interface have to migrate to the new contract (see above).

The four boolean flags of 1.x keep working and are aliases for the new nodes:

| 1.x flag | Equivalent node |
|---|---|
| `cache_check_enabled` | `checks.cache.enabled` |
| `database_check_enabled` | `checks.database_accessible.enabled` |
| `filesystem_check_enabled` | `checks.filesystem.enabled` |
| `robots_txt_check_enabled` | `checks.robots_txt.enabled` |

A flag that is set explicitly wins over the corresponding `checks.*.enabled` node.

`/health-check-status` is unchanged - but note that the bundle now ships many more checks
than 1.x, all enabled by default. Review the configuration before upgrading a monitored
production system, or disable what you do not want:

```yaml
pimcore_plugin_health_check:
    checks:
        composer_audit:
            enabled: false
        composer_outdated:
            enabled: false
        hosting_size:
            enabled: false
```

## Pimcore admin menu entry

Not included. Pimcore 11 needs a JS extension point of the optional
`pimcore/admin-ui-classic-bundle`, while Pimcore 12 renders its UI with Pimcore Studio, which
uses a completely different extension mechanism. Supporting both would mean depending on an
optional bundle, so the dashboard is reached by URL (`/health-check`) or by a link added in
the project.
