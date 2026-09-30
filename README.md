# Pimcore Plugin Health Check

[![CI](https://github.com/basilicom/pimcore-plugin-health-check/actions/workflows/ci.yml/badge.svg)](https://github.com/basilicom/pimcore-plugin-health-check/actions/workflows/ci.yml)

License: MIT — see [LICENSE.txt](LICENSE.txt)

## Version information

| Bundle Version | PHP   | Pimcore                    |
|----------------|-------|----------------------------|
| ^2.1           | ^8.3  | ^11.0, ^12.0 or ^2026.0    |
| ^2.0           | ^8.3  | ^11.0, ^12.0 or ^2026.0    |
| ^1.0           | ^8.3  | ^11.0                      |

## Why?

This Pimcore plugin provides an endpoint where upon access a couple of checks regarding system
health are performed. Output is SUCCESS or FAILURE. This is suitable for continuous monitoring via
StatusCake, Pingdom or a similar service.

The URL to trigger the check is:

    [domain]/health-check-status

There is a second route for Kubernetes-style probes:

    [domain]/health-check-live

It runs **no check at all** and always answers `SUCCESS`. That is the point: liveness answers
"should this process be restarted", and no external dependency can be fixed by a restart. Reaching
it already proves the process is up, PHP responds and the kernel routed. Point your liveness probe
at it and your readiness probe at `/health-check-status`. Both honour the token.

A healthy system answers `200 OK` with the body `SUCCESS`. A failing one answers
`503 Service Unavailable` with `FAILURE: [<id>]`, so both status-code and string matching work.

The failure body deliberately names neither the check nor the reason: the route is public and
unauthenticated, and an anonymous caller must not be able to map the response back to the state of
your system. The `<id>` is a random correlation id; the actual reason is written to the log under
the `health_check` Monolog channel with that same id.

Where that ends up is the host project's decision. With Pimcore's own Monolog defaults the `prod`
handler writes `var/log/prod-error.log` at level `error` and filters no channels, so failures land
there without any setup — but the warning and the rejected-token notice are below that level and
are dropped, not buffered. To keep them, give the channel its own handler:

```yaml
monolog:
    handlers:
        health_check:
            type: stream
            path: '%kernel.logs_dir%/health_check.log'
            level: notice
            channels: [health_check]
```

Errors then appear in both files; add `'!health_check'` to the other handlers if that bothers you.

To see the reasons directly, run the console command — it has the full picture:

```
bin/console basilicom:health-check
```

It prints one row per active check and exits non-zero if any of them failed, which makes it usable
as a deployment gate.

Since 2.1 the bundle also answers the question the monitoring endpoint deliberately does not: *what*
is wrong, and what does the system look like. See [System Health Status](#system-health-status-dashboard-and-api).

## System Health Status: dashboard and API

The monitoring endpoint is a binary gate for machines. The dashboard is the same picture for people,
plus an inventory of the system - versions, sizes, queues, logs, users - one row per check:

| Status | Check | Message |
|---|---|---|
| ✕ `check_result_critical` | Application Log Errors | Found 2379 errors in application logs in the last 24 hours. |
| ! `check_result_warning` | Messenger Message Age | Oldest messenger message is 139 minutes old (warning threshold: 60 min). |
| ✓ `check_result_ok` | PHP Version | Current PHP version is 8.3.12 |
| – `check_result_na` | Composer Audit | n/a: Could not authenticate against repo.example.com |

The status labels are the ones `instride/pimcore-monitor` used, so a team coming from there reads it
without relearning. A blue **Details** button unfolds the measured values and the runtime of every
row. The page is plain HTML with inline CSS - no JavaScript framework, no external asset, so it
works behind a firewall.

### Nothing runs on a page view

The inventory is expensive: it sizes directories, counts tables, may shell out to `composer` and
asks endoflife.date. None of that happens when someone opens the page. A console command runs the
checks and stores the result in Pimcore's settings store; the dashboard and the API only read it:

```
bin/console basilicom:health-check:audit            # run, store, print a table, exit 1 on a failure
bin/console basilicom:health-check:audit --format=json
bin/console basilicom:health-check:audit --no-store  # look, do not touch the stored result
```

Run it from cron - hourly is plenty for an inventory. The page shows when the stored run was
generated. Until the first run it shows the command to type. The result lives in the database
(`settings_store`, scope `pimcore_plugin_health_check`), so every node of a multi-server setup
shows the same run and a cache clear does not empty the page.

The audit command runs the **core checks** (the ones behind `/health-check-status`) as well, so the
page has the whole picture, and a failing core check makes it exit non-zero exactly like
`basilicom:health-check` does.

### Routes

| Route | Content |
|---|---|
| `GET /health-check` | the dashboard |
| `GET /health-check/api` | the same run as JSON; `?check=<identifier>` for a single check |

Both paths are configurable (`dashboard.path`, `dashboard.api_path`), both can be switched off
together (`dashboard.enabled: false`).

```json
{
  "status": "critical",
  "healthy": false,
  "generated_at": "2026-09-23T10:00:00+02:00",
  "duration_ms": 8412,
  "summary": {"ok": 38, "warning": 2, "critical": 1, "skipped": 1, "na": 1},
  "checks": [
    {
      "check": "Basilicom\\PimcorePluginHealthCheck\\Checks\\Audit\\ApplicationLogErrorsCheck",
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

An authorised request always answers 200 - the body carries the verdict, so a monitor evaluates
`healthy` or `status`. The two exceptions: an unknown `?check=` answers 404 so a typo in a
monitoring configuration cannot look healthy, and an unauthorised request answers **404 with an
empty body**, exactly like the monitoring endpoint does.

### Who may look

Both routes show what the monitoring endpoint hides, so they are never public. A request gets in
when it either

* comes from a **logged in Pimcore admin** - the admin flag, not any backend user - or
* carries the **API key** in the `X-Health-Check-Api-Key` header.

```yaml
pimcore_plugin_health_check:
    dashboard:
        api_key: '%env(HEALTH_CHECK_API_KEY)%'
```

The key is a separate secret from `token` on purpose: the token only answers "healthy or not", the
key hands out the inventory. It is at least 16 characters, compared with `hash_equals()`, and it
is **not** accepted as a query parameter - query strings end up in access logs, proxy logs and
browser history. Without a key configured only the admin session opens the page.

### Audit checks

All of these run only from `basilicom:health-check:audit`. Thresholds are `warning_threshold` /
`failure_threshold` unless noted; set either to `null` to switch that level off. A `critical` on the
dashboard means "needs attention", not "drop this node from the load balancer" - the monitoring
endpoint never sees these results.

| Identifier | Config key | Reports | Default |
|---|---|---|---|
| `system:app_environment` | `app_environment` | warning when the environment differs from `environment` | on, `%kernel.environment%` |
| `system:php_version` | `php_version` | dynamic: critical past EOL, warning when a newer cycle is supported (endoflife.date); or `version` + `operator` | on, dynamic |
| `system:mysql_version` | `mysql_version` | same, detecting MariaDB vs MySQL - the two number their releases differently | on, dynamic |
| `system:doctrine_migrations` | `doctrine_migrations` | warning on pending, critical on executed-but-missing migrations | on |
| `security:debug_mode` | `debug_mode` | critical when `kernel.debug` is on in `prod` | on |
| `device:hosting_size` | `hosting_size` | size of the project directory, bytes | on, 45 GB / 50 GB |
| `device:thumbnail_storage` | `thumbnail_storage` | size of the generated thumbnails | on, no thresholds |
| `device:database_size` | `database_size` | `data_length + index_length` of the schema | on, 9.2 GB / 10 GB |
| `device:database_table_size` | `database_table_size` | per table, names the offenders | on, 900 MB / 1 GB |
| `pimcore:version` | `pimcore_version` | informational | on |
| `pimcore:bundles`, `pimcore:areabricks`, `pimcore:users` | `pimcore_bundles`, `pimcore_areabricks`, `pimcore_users` | counts | on |
| `pimcore:element_count` | `pimcore_element_count` | objects + assets + documents | on, 100 000 / 150 000 |
| `maintenance:last_run_age` | `maintenance_last_run` | minutes since the last maintenance run (`warning_minutes` / `failure_minutes`) | on, 120 / 1440 |
| `maintenance:messenger_message_count` | `messenger_message_count` | waiting messages, excluding delivered and failed ones | on, 500 / 1000 |
| `maintenance:messenger_message_age` | `messenger_message_age` | age of the oldest waiting message, minutes | on, 60 / 180 |
| `maintenance:failed_messages` | `failed_messages` | messages parked in a failure transport (`messenger.failed_queue_names`, plus any `*_failed`) | on, 1 / 50 |
| `logs:log_file_errors` | `log_file_errors` | error lines in `files` within `lookback_hours`, tail of `max_bytes` only | on, 10 / 50 |
| `logs:application_log_errors` | `application_log_errors` | error rows in `application_logs` within `lookback_hours` | on, 10 / 50 |
| `security:composer_audit` | `composer_audit` | critical on any advisory, `n/a` when composer is missing, unauthorised or times out | **off** |
| `security:composer_outdated` | `composer_outdated` | direct dependencies behind | **off**, 10 / 25 |
| `security:inactive_admin_users` | `inactive_admin_users` | admins without a login for `days` | on, 90 days, 1 / – |
| `security:users_without_2fa` | `users_without_2fa` | admins without an enabled TOTP secret | on, 1 / – |
| `database:versions_table_bloat` | `versions_table` | rows in `versions` | on, 500 000 / 1 000 000 |
| `database:largest_tables` | `largest_tables` | the `limit` largest tables | on |
| `data:dataobject_classes`, `data:documents_by_type`, `data:custom_templates` | same names | counts | on |
| `data:assets_storage` | `assets_storage` | asset count and size of `public/var/assets` | on, no thresholds |
| `data:objects_per_class` | `objects_per_class` | objects per class, optional warning on empty classes | on |
| `data:stale_objects` | `stale_objects` | objects untouched for `days` | on, 365 days, no thresholds |
| `data:unpublished_objects` | `unpublished_objects` | unpublished share, thresholds in percent | on, no thresholds |
| `data:assets_without_metadata` | `assets_without_metadata` | `asset_types` without any metadata, or missing one of `metadata_names` | on, no thresholds |
| `data:asset_types` | `asset_types` | MIME distribution, warning on `disallowed_extensions` | on |
| `data:documents_missing_seo` | `documents_missing_seo` | pages without title or description | on, no thresholds |

Some rows answer `check_result_na` instead of a verdict, and that is the honest answer: the
`messenger_messages` table does not exist until Messenger's Doctrine transport is used, the
application logger bundle may not be installed, `du` may not finish inside
`audit.directory_size_timeout_s`, composer may be absent, endoflife.date may be unreachable.
None of those is a fault of the system being checked.

The two composer checks are off by default because they need the `composer` binary and network
access on the host. The endoflife.date lookups are on, cached for a day, and a failed lookup is
remembered for ten minutes so an offline host does not pay the timeout on every run
(`external_lookups`).

```yaml
pimcore_plugin_health_check:
    dashboard:
        enabled: true
        path: /health-check
        api_path: /health-check/api
        api_key: '%env(HEALTH_CHECK_API_KEY)%'
    audit:
        timeout_ms: 120000            # budget for a whole audit run
        directory_size_timeout_s: 30  # per directory, then the size is n/a
    external_lookups:
        enabled: true
        timeout_s: 3
        cache_ttl_s: 86400
        failure_cache_ttl_s: 600
    messenger:
        failed_queue_names: [failed]
    checks:
        composer_audit: true          # needs composer + network on the host
        pimcore_element_count:
            warning_threshold: 250000
            failure_threshold: null   # never red, only yellow
        mysql_version:
            version: '10.6'           # hard-coded instead of endoflife.date
            operator: '>='
```

### Writing an audit check

Extend `Checks\AbstractReportingCheck`, return a `Report`, tag it `basilicom.health_check.audit`.
A passing check has a message too - that is the point of the dashboard:

```php
final readonly class ImportQueueCheck extends AbstractReportingCheck
{
    public function __construct(bool $enabled, private Connection $connection, private ?int $warningThreshold)
    {
        parent::__construct($enabled);
    }

    public function identifier(): string { return 'import:queue_length'; }
    public function label(): string { return 'Import Queue'; }

    protected function examine(): Report
    {
        $count = (int)$this->connection->fetchOne('SELECT COUNT(*) FROM my_import_queue');

        return Report::graded($count, $this->warningThreshold, null, sprintf('Import queue has %d entries.', $count), ['count' => $count]);
    }
}
```

`examine()` may throw; `inspect()` turns it into a `critical` row naming the exception class but
not its message. Anything that is not the system's fault - a missing table, an offline service -
should be `Report::notAvailable()`, and a check that has nothing to examine in this run,
`Report::skipped()`. Both leave the verdict untouched. The same class also works as a core check
under `basilicom.health_check`: `check(): void` throws for a warning or failure and passes otherwise.

## Motivation

Services like Pingdom and StatusCake should be used by detecting a specific success state instead
of looking for closing body tags, status codes or similar indications. A dedicated list of checks
helps ensuring a fully functioning web system.

## Installation

```
composer require basilicom/pimcore-plugin-health-check
```

### Activate Plugin

* Add to config/bundles.php
```
return [
    ...
    PimcorePluginHealthCheckBundle::class => ['all' => true],
];
```

## Securing the endpoint

**By default the endpoint is reachable by anyone.** Nothing breaks if you leave it that way, and
the response body is deliberately opaque, but an open endpoint still lets a stranger poll whether
your system is healthy — and the response time still hints at which check failed.

**The recommendation is to set a token.** It is the only measure that stops an anonymous caller
outright:

```yaml
pimcore_plugin_health_check:
    token: '%env(HEALTH_CHECK_TOKEN)%'
```

The token must be at least 16 characters. That is enforced on a literal value only - a token read
from an env var is not visible while the container is built, so keep the length in mind there.
Generate one with `openssl rand -hex 24` and keep it in the environment, not in a committed file.

Callers then pass it either as a header, which is preferred because query strings end up in access
logs, proxy logs and browser history:

```
curl -H 'X-Health-Check-Token: <token>' https://example.com/health-check-status
```

or, for monitoring services that cannot send custom headers, as a query parameter:

```
https://example.com/health-check-status?token=<token>
```

A request without the token, or with the wrong one, gets **404 with an empty body** — the same
answer as an unknown route, so a scanner cannot even confirm the endpoint exists. The rejection is
logged at notice level, which is where to look when a monitor suddenly reports 404. The token is
compared with `hash_equals()` and checked before any check runs, so an unauthorised caller never
makes the server touch the database.

`bin/console basilicom:health-check` prints a warning while no token is configured.

## Checks

| Check | Fails when | Severity | Config key | Default |
|---|---|---|---|---|
| `PimcoreConfigurationCheck` | the Pimcore environment or system configuration is unreadable | failure | always on | on |
| `DiskSpaceCheck` | free space on the temporary directory falls below a threshold | warning, then failure | `disk_space` | off |
| `DatabaseAccessibleCheck` | the database is unreachable or the `users` table is empty | failure | `database` | on |
| `DatabaseLatencyCheck` | a `SELECT 1` round trip exceeds the threshold | failure | `database_latency` | off |
| `PendingMigrationsCheck` | Doctrine migrations are waiting to be executed | warning | `pending_migrations` | off |
| `AdminUserCheck` | the named account exists and is active | failure | `admin_user` | on |
| `FilesystemCheck` | Pimcore's temporary directory is not writeable | failure | `filesystem` | on |
| `AssetStorageCheck` | the Pimcore asset storage cannot store and return a probe | failure | `asset_storage` | off |
| `CacheCheck` | the Pimcore cache pool cannot store and return a value | failure | `cache` | on |
| `RobotsTxtCheck` | no `robots.txt` is served at all, or the one served contains `Disallow: /` (warning); a file in the web root is unreadable (failure) | warning, then failure | `robots_txt` | on |
| `HttpsConnectionCheck` | the request that runs the checks arrived over plain HTTP (warning); skipped on the CLI | warning | `https_connection` | on |

Checks run in the order listed. The cheap local ones come first on purpose: a hung dependency
further down must not eat the timeout budget before the free diagnostics have run.

Everything added after 1.x is **off by default**, so an upgrade never turns a green system red.

Every active check runs on every request — the endpoint does not stop at the first failure, so all
failures reach the log and the response time does not betray which check failed. A run is bounded
by `timeout_ms` (default 5000): once the budget is spent no further check is started, and the
response is a failure. A check already running is not interrupted, so the budget caps how much a
hung dependency can cost, it does not cap a single check.

### Configuration

Defaults are in the table above. A check is either a boolean or a block:

```yaml
pimcore_plugin_health_check:
    token: '%env(HEALTH_CHECK_TOKEN)%'   # optional, see "Securing the endpoint"
    timeout_ms: 5000                     # budget for a whole run
    checks:
        database: true
        filesystem: true
        cache: true
        robots_txt: true

        # off by default: a tight threshold turns every load spike into an outage
        database_latency:
            enabled: false
            threshold_ms: 1000

        admin_user:
            enabled: true
            user_name: admin      # say so here if the project renamed the account

        asset_storage: false      # S3 or a network mount, fails independently of the local disk
        pending_migrations: false

        disk_space:
            enabled: false
            warning_below_percent: 20
            failure_below_percent: 5
```

### Severity

A check reports either a failure or a warning. A **failure** means the node cannot serve requests
and takes the endpoint to 503. A **warning** means the node is degraded but usable: it is logged at
warning level and shown by the console command, but the endpoint stays at 200.

That distinction exists so a degraded node is never dropped from a load balancer. Its traffic would
move to the remaining nodes and push those over the same line — a busy afternoon turns into a full
outage.

The dashboard adds two states that are not verdicts: **skipped** (nothing to examine in this run,
such as a request-bound check on the CLI) and **n/a** (the data could not be obtained). Neither
changes the outcome of a run. On the dashboard and in the API a failure is labelled `critical`,
matching the `check_result_critical` label users know from instride/pimcore-monitor.

## Upgrading

2.1 is additive: the monitoring endpoint, its body, the token and every 2.0 config key are
unchanged. New are the dashboard, the API, the audit command and 37 checks, all documented above.
`CheckResult` gained optional `message`, `data`, `identifier`, `label` and `durationMs`;
`Severity` gained `Skipped` and `NotAvailable` - code that `match`es on it exhaustively needs two
more arms.

### From 1.x

See [CHANGELOG.md](CHANGELOG.md) — 2.0 changes the HTTP status code on failure, moves the admin
user check into its own switch, and logs to Monolog instead of the Pimcore application logger.
