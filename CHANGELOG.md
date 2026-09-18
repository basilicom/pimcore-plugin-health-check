# Changelog

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
  `AdminUserCheck`, switchable via `admin_user_check_enabled` (default: true, so behaviour is
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
- `AdminUserCheck` and `admin_user_check_enabled`.
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
