# Changelog

## 2.1.0

The System Health Status dashboard (PF-415): the picture the monitoring endpoint deliberately
withholds, for people who are allowed to see it.

### Added

- `GET /health-check`, the **System Health Status** page: one row per check with status label,
  name and message, coloured by result, a *Details* toggle for the measured values. Plain HTML with
  inline CSS, no external asset. Path via `dashboard.path`, off via `dashboard.enabled: false`.
- `GET /health-check/api`, the same run as JSON with `status`, `healthy`, `summary` and one entry
  per check; `?check=<identifier>` for a single one. Path via `dashboard.api_path`.
- `bin/console basilicom:health-check:audit`, which runs the core and the audit checks, stores the
  result in Pimcore's settings store and exits non-zero on a failure. The dashboard and the API
  read that stored run and **run no check themselves** - the inventory work (directory sizes,
  table counts, `composer`, endoflife.date) happens from cron, not from a page view.
- Access control for both routes: a logged in Pimcore **admin** (the admin flag, not any backend
  user) or the `X-Health-Check-Api-Key` header. The key (`dashboard.api_key`, at least 16
  characters, `hash_equals()`) is a separate secret from `token`, because it hands out the inventory
  where the token only says healthy or not. It is never read from the query string. An unauthorised
  request gets 404 with an empty body, like the monitoring endpoint.
- **36 audit checks** under `Checks\Audit\`, tagged `basilicom.health_check.audit`: the fifteen
  checks of instride/pimcore-monitor (app environment, PHP and MySQL/MariaDB version, Doctrine
  migrations, disk and hosting size, database and table size, Pimcore version, bundles, areabricks,
  users, element count, maintenance age), the CS-990 checks (messenger count and age, `prod.log` /
  `php.log` errors, application log errors, `composer audit`) and the PF-88 metrics that need no
  external tool (composer outdated, failed messages, inactive admins, admins without 2FA, debug
  mode, versions table, largest tables, DataObject classes, objects per class, documents by type,
  stale and unpublished objects, assets storage, assets without metadata, asset types, custom
  templates, pages without SEO metadata).
- `HttpsConnectionCheck` in the core group: warns when the request that runs the checks arrived
  over plain HTTP, skipped on the CLI.
- **Dynamic version checks.** With no `version` configured, `php_version` and `mysql_version` grade
  against the release cycles on endoflife.date: critical past end of life, warning when a newer
  cycle is supported. Responses are cached for a day; a failed lookup is remembered for ten minutes
  (`external_lookups`). The MySQL check detects MariaDB vs MySQL first - the two number their
  releases differently, so no single hard-coded version is right for both.
- `Checks\ReportingCheckInterface`, `Checks\AbstractReportingCheck` and `Checks\Report`: a check
  that has something to say when it passes. `inspect()` never throws; `check(): void` still works
  for the monitoring endpoint. `Report::graded()` grades a value against two inclusive thresholds,
  either of which may be `null`.
- `Severity::Skipped` and `Severity::NotAvailable`, with `label()` (`check_result_ok`,
  `check_result_warning`, `check_result_critical`, `check_result_skipped`, `check_result_na` - the
  instride/pimcore-monitor names) and `key()` / `fromKey()` for the API.
- `Services\CheckResult` gained optional `message`, `data`, `identifier`, `label` and `durationMs`.
  A plain check derives `identifier()` and `label()` from its class name (`core:database_accessible`,
  `Database Accessible`).
- `Audit\StoredRun`, `Audit\RunStoreInterface` and `Audit\SettingsStoreRunStore`;
  `Security\AccessGuard`, `Security\AdminSessionInterface`, `Security\PimcoreAdminSession`;
  `Services\EndOfLifeDateClient`; `Util\Bytes`, `Util\DirectorySize`.
- Configuration: `dashboard`, `audit`, `external_lookups`, `messenger` and one `checks.<name>` node
  per audit check. Thresholds accept a number or `null`.

### Changed

- `HealthCheckService::run()` records the duration of every check and, for a reporting check, its
  message, data, identifier and label. Plain checks are unaffected.
- `basilicom:health-check` prints `SKIPPED` and `N/A` rows for the two new severities.

### Notes

- Directory sizes come from `du` with a timeout (`audit.directory_size_timeout_s`) and there is no
  PHP fallback on purpose: a recursive scan of a large project has no upper bound, and `n/a` is the
  honest answer when `du` does not finish.
- `messenger_message_count` and `messenger_message_age` count waiting messages only: delivered ones
  and those parked in a failure transport (`messenger.failed_queue_names`, plus any `*_failed`) are
  excluded, so one dead message cannot keep the age red forever. `failed_messages` reports those
  separately.
- The two composer checks are off by default; they need the `composer` binary and network access on
  the host.

## 2.0.0

### Breaking

- `AdminUserCheck::__construct()` gained a required `string $userName`. Only relevant to code that
  built the check by hand.

- Every configuration key moved under `checks:`, and each check is now a boolean or a block with an
  `enabled` flag. An old key fails the container build with `Unrecognized option ... Available
  option is "checks"`, so an upgrade breaks loudly instead of silently ignoring your settings.

  | 1.x | 2.0 |
  |---|---|
  | `database_check_enabled` | `checks.database` |
  | `cache_check_enabled` | `checks.cache` |
  | `filesystem_check_enabled` | `checks.filesystem` |
  | `robots_txt_check_enabled` | `checks.robots_txt` |
  | — | `checks.admin_user` |
  | — | `checks.database_latency.enabled` / `.threshold_ms` |

- A failed health check now answers `503 Service Unavailable` instead of `200 OK`. Monitors that
  match on the `SUCCESS` / `FAILURE` body are unaffected; monitors keyed on the status code start
  reporting outages they previously missed.
- The "admin user is active" condition moved out of `DatabaseAccessibleCheck` into its own
  `AdminUserCheck`, switchable via `checks.admin_user` (default: true, so behaviour is
  unchanged unless you turn it off). It now throws `AdminUserActiveException` rather than
  `DatabaseNotAccessibleException`.
- Failures are logged to the Symfony `health_check` Monolog channel instead of the Pimcore
  application logger. The bundle no longer depends on `PimcoreApplicationLoggerBundle` being enabled.
- The failure response body is now opaque: `FAILURE: [<id>]`, with no check name and no reason, at
  constant length. The route is public and unauthenticated, so the previous body let anyone map the
  response back to the state of the system — including whether an active `admin` account exists.
  The reason is logged under the `health_check` channel with the same correlation id, and
  `bin/console basilicom:health-check` prints it in full.
- `Checks\ConfigurationTrait` was removed. Checks receive their settings through the constructor.
- `AbstractHealthCheckException::__construct()` dropped the unused `$code` parameter and gained a
  trailing `Severity $severity = Severity::Failure`.
- `HealthCheckService::check(): void` was replaced by `run(): array`, which returns one
  `Services\CheckResult` per check that ran instead of throwing on the first failure. The service
  now also takes a required `int $timeoutMilliseconds` constructor argument.
- All check classes are `final readonly` and are registered via the `basilicom.health_check` service
  tag. Projects that extended a check class must switch to implementing `CheckInterface`.

### Fixed

- `RobotsTxtCheck` never detected `Disallow: /` — the line still carried its trailing newline when
  compared, so the check silently passed on a fully disallowed domain.
- `RobotsTxtCheck` crashed with a `TypeError` when `robots.txt` was absent, because the failed
  `fopen()` result was passed to `feof()`. A missing file now fails the check as intended.
- `RobotsTxtCheck` resolved the web root from `$_SERVER['DOCUMENT_ROOT']`, which is empty on CLI and
  unreliable behind some web servers. It now uses `PIMCORE_WEB_ROOT`.
- `RobotsTxtCheck` looked for a file in the web root only, while Pimcore answers `/robots.txt`
  from `_pimcore_service_robots_txt` out of its own settings. A site whose robots.txt lives there -
  the normal Pimcore setup - was reported missing, and a `Disallow: /` configured in the SEO
  settings, the way it actually happens on Pimcore, went unnoticed. A robots.txt is still
  mandatory, but it now counts as served when either a file exists or Pimcore has one configured.
  Pimcore's built-in "allow everything" fallback does not count: that is what a site gets when
  nobody decided anything. A missing robots.txt and a `Disallow: /` both report a warning rather
  than a failure now - they cost reach, not availability, and dropping the node from the load
  balancer fixes neither.
- `FilesystemCheck` ignored the return value of `file_put_contents()` and used a fixed probe file
  name, so two concurrent monitoring requests could delete each other's probe.
- `CacheCheck` used a fixed cache key with the same race, and left `Cache::setForceImmediateWrite(true)`
  set for the rest of the process.
- `composer.json` required `pimcore/pimcore: ">=11 || >=12"`, which is unbounded and resolved to the
  current Pimcore release regardless of the version table in the README. It is now
  `^11.0 || ^12.0 || ^2026.0`.

### Added

- `/health-check-live`, a liveness route that runs no check and always answers `SUCCESS`. Liveness
  answers "should this process be restarted", and no external dependency can be fixed by a restart.
  Point a Kubernetes liveness probe at it and the readiness probe at `/health-check-status`.
- Three further checks, all **off by default** so an upgrade never turns a green system red:
  `AssetStorageCheck` (writes a probe through Flysystem, which is usually S3 or a network mount and
  fails independently of the local disk), `PendingMigrationsCheck` (warning — during a rolling
  deploy the new code is up before the migration ran) and `DiskSpaceCheck` (warning, then failure
  below a lower bound).
- `checks.admin_user.user_name` (default `admin`). A project that renamed the default account was
  otherwise told it was healthy while the account stayed active.

- Every active check now runs on every request. The endpoint used to stop at the first failure,
  which made the response time reveal which check failed — an anonymous caller could tell a dead
  database from a bad `robots.txt` by latency alone — and hid every failure after the first from
  the log.
- `timeout_ms` (default 5000) bounds a whole run. Past the budget no further check is started and
  the run reports a `HealthCheckTimedOutException`. Without it, running all checks would make a
  hung database three times as expensive as before: DBAL does not cache a failed connection, so
  each of the three database-touching checks pays the full connect timeout again.

- Optional token protection for the endpoint, via the top-level `token` key. It is **off by
  default and nothing breaks without it**, but it is the recommended setup: the opaque failure body
  hides *what* is wrong, only a token stops a stranger from asking at all. The token must be at
  least 16 characters, is compared with `hash_equals()` and is accepted as the
  `X-Health-Check-Token` header or a `token` query parameter. A missing or wrong token gets 404
  with an empty body rather than 401, so the endpoint stays indistinguishable from an unknown
  route, and the check runs before any health check touches the database.
  `basilicom:health-check` warns while no token is set.
- `bin/console basilicom:health-check`, which runs every active check, prints a table of results and
  exits non-zero on failure — usable as a deployment gate. Unlike the HTTP endpoint it does not stop
  at the first failure and it does print the reasons, because running it requires shell access.
- `AdminUserCheck` and `checks.admin_user`.
- `Services\CheckResult`, the per-check result carrying the check class, its severity and its
  failure, if any.
- `Severity` (`ok` / `warning` / `failure`). A check can report a warning instead of a failure;
  warnings are logged and shown by the console command but leave the endpoint at 200, so a degraded
  node is never dropped from a load balancer.
- `DatabaseLatencyCheck`, off by default, configured under `checks.database_latency` with a
  `threshold_ms` of 1000. It times a constant-cost `SELECT 1`; the existing
  `DatabaseAccessibleCheck` probe (`SELECT COUNT(*) FROM users`) is an index scan and unusable for
  latency.
- PHPUnit test suite, PHP-CS-Fixer, PHPStan level 6 and a Docker-only `Makefile`.
- GitHub Actions CI running the suite and PHPStan against Pimcore 11, 12 and 2026.
- `.gitattributes`, so the distributed package contains only `src/`, `composer.json`, `README.md`,
  `CHANGELOG.md` and `LICENSE.txt`.
