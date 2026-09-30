# AGENTS.md

Instructions for AI coding agents working on this repository.

## What this is

A Pimcore bundle with two faces.

The monitoring face: `GET /health-check-status` runs the **core checks** (tag
`basilicom.health_check`) and answers `SUCCESS` (200) or `FAILURE: [<id>]` (503) in `text/plain`,
for uptime monitors such as StatusCake or Pingdom. `GET /health-check-live` runs no check and
always answers `SUCCESS`.

The inventory face: `bin/console basilicom:health-check:audit` runs the core checks plus the
**audit checks** (tag `basilicom.health_check.audit`, `Checks/Audit/*`) and stores the result in
Pimcore's settings store. `GET /health-check` (the System Health Status page) and
`GET /health-check/api` render that stored run for a logged in Pimcore admin or a caller with the
API key. They never run a check.

There is no ExtJS, no Studio frontend, no admin menu entry. The dashboard is a plain HTML page with
inline CSS at its own route.

## Hard constraints

- **The response body is a public contract.** Monitors match on the literal strings `SUCCESS` and
  `FAILURE`. Do not reformat the body, do not wrap it in JSON, do not translate it.
- **An unauthorised caller gets 404, never 401.** A 401 confirms the endpoint exists and is worth
  attacking. The token is compared with `hash_equals()` and checked before any check runs, so a
  stranger cannot make the server query the database. Token protection is optional and off by
  default; the opaque body is what keeps the endpoint safe without it.
- **The failure body says nothing.** It is exactly `FAILURE: [<16 hex>]` — no check name, no reason,
  no error code, constant length. The route is unauthenticated, so anything in the body is a free
  report on the state of the system for whoever asks. Do not add detail, not even behind a config
  flag: the bundle is public on GitHub, so any code or abbreviation is trivially mapped back. The
  reason belongs in the log and in `basilicom:health-check`, both of which require access.
- **Check exception messages are still written for humans, not for the wire.** They reach the log
  and the console command. Keep the underlying cause in the `previous` exception; never interpolate
  a DBAL, filesystem or cache error message into a `HealthCheckException`, and never put an
  absolute server path in one.
- **Checks must be side-effect free and safe to run in parallel.** A monitor polls every minute,
  often from several probes at once. Probe files and cache keys therefore carry a random suffix —
  a fixed name lets two concurrent requests delete each other's probe and report a false outage.
- **The dashboard and the API never run a check.** They read what `basilicom:health-check:audit`
  stored. A page view costs one settings-store read. Anything that sizes a directory, counts a
  table, shells out or leaves the host belongs to the audit group and runs from cron.
- **The dashboard and the API are never public.** Admin session (the admin flag, not
  `ROLE_PIMCORE_USER`) or the `X-Health-Check-Api-Key` header, checked in `Security\AccessGuard`.
  Unauthorised is 404 with an empty body, the same as the monitoring endpoint. The key is a
  separate secret from `token` and is never read from the query string.
- **An audit failure is a red row, not a dropped node.** The monitoring endpoint never sees audit
  results, so `Severity::Failure` in `Checks/Audit/*` means "needs attention now" and is labelled
  `check_result_critical`. The "resource pressure is a warning" rule below applies to core checks,
  whose failures take the node out of the load balancer.
- **`n/a` and `skipped` are not verdicts.** A missing table, an absent binary, an unreachable
  endoflife.date, a request-bound check on the CLI - `Report::notAvailable()` / `Report::skipped()`.
  They never change the outcome of a run and never become a failure.
- **Identifiers are stable.** `identifier()` (`system:php_version`) is the API key of a check and
  what `?check=` selects; renaming one breaks somebody's monitoring.
- **`isActive()` is the only switch.** `HealthCheckService` iterates the tagged checks and skips
  inactive ones. A check must not decide its own relevance any other way.
- **Resource pressure is a warning, never a failure.** Only a node that cannot serve requests gets
  `Severity::Failure`. Anything measuring load, memory or throughput reports `Severity::Warning`, so
  the endpoint stays at 200. A health check that drops busy nodes from a load balancer turns their
  traffic onto the remaining ones and escalates a busy afternoon into an outage.
- **A threshold is generous or it is noise.** Thresholds answer "is this broken", not "is this
  fast". A tight one flaps and trains people to ignore the alert.
- **Every active check runs, always.** Stopping at the first failure turns the response time into a
  signal for *which* check failed, and hides the later failures from the log. The only thing that
  cuts a run short is the `timeout_ms` budget, which stops starting new checks and reports a
  failure of its own.
- **The liveness route stays empty.** `/health-check-live` must never run a check. Liveness asks
  whether to restart the process, and nothing a check can detect is fixed by a restart; putting a
  dependency in there restarts healthy containers because a database blinked.
- **Order is tag priority, not array order.** `PimcoreConfigurationCheck` runs first (priority 100)
  because every later check assumes a readable configuration. New checks pick a priority below it.
- **Nothing reaches into the container.** Checks receive their collaborators and their scalar
  settings through the constructor. `Pimcore::getKernel()->getContainer()` is what made the old
  implementation untestable — it does not come back.

## Adding a core check

1. Implement `Checks\CheckInterface`, `final readonly`, collaborators plus `private bool $enabled`
   in the constructor.
2. Throw a subclass of `Exception\AbstractHealthCheckException`, passing the real cause as
   `previous:` and, for a degraded-but-usable condition, `severity: Severity::Warning`.
3. Add an array node under `checks` in `DependencyInjection\Configuration`, using `canBeDisabled()`
   for a check that should be on by default and `canBeEnabled()` for one that should not. Both
   accept the boolean shorthand. Thresholds are children of that node.
4. Register it in `src/Resources/config/services.yml` with the `basilicom.health_check` tag and a
   priority, wiring `$enabled` to `%pimcore_plugin_health_check.checks.<name>.enabled%`. The
   extension flattens the whole `checks` tree into parameters, so it needs no change.
5. Cover it in `tests/Unit/Checks/`, and extend the tag assertions in `tests/Integration/`.

## Adding an audit check

1. Extend `Checks\AbstractReportingCheck` in `Checks/Audit/`, `final readonly`, call
   `parent::__construct($enabled)`. Give it a stable `identifier()` (`group:name`) and a `label()`.
2. Implement `examine(): Report` with the named constructors - `Report::ok()` carries a message
   too, that is what the dashboard shows on a green row. Grade numbers with `Report::graded()`,
   whose thresholds may be `null`. Reach for `Report::notAvailable()` when the data cannot be had
   and `Report::skipped()` when there is nothing to examine; never turn either into a failure.
   `examine()` may throw - `inspect()` reports the exception class, not its message.
3. Keep absolute paths, DSNs and error messages out of `message`; put a path in `data` if it helps.
4. Config node as above; thresholds via `Configuration::thresholds()` so `null` is accepted.
5. Register with the `basilicom.health_check.audit` tag. Priorities: free local facts high,
   database counts in the middle, directory sizing and composer last.
6. Cover it in `tests/Unit/Checks/Audit/` with a mocked `Connection`; `AUDIT_CHECK_COUNT` in
   `tests/Integration/ServiceWiringTest.php` goes up by one.

## Layout

- `Controller/HealthCheckController` — the monitoring routes, maps exceptions to the response
- `Controller/DashboardController`, `Controller/ApiController` — render the stored audit run
- `Command/HealthCheckCommand` — the core checks with reasons, for a shell
- `Command/AuditCommand` — runs core + audit checks, stores the run
- `Services/HealthCheckService` — runs tagged checks in priority order; two instances, one per tag
- `Services/CheckResult` — one result; `Services/EndOfLifeDateClient` — cached endoflife.date lookups
- `Checks/*` — core checks implementing `CheckInterface`
- `Checks/Report`, `Checks/ReportingCheckInterface`, `Checks/AbstractReportingCheck` — the
  reporting contract
- `Checks/Audit/*` — the audit checks
- `Audit/*` — `StoredRun` and the run store
- `Security/*` — `AccessGuard` and the admin session lookup
- `Util/*` — byte formatting, `du`-based directory sizing
- `Exception/*` — one subclass of `AbstractHealthCheckException` per failure mode
- `DependencyInjection/*` — config tree and the extension that turns it into parameters
- `Resources/views/dashboard.html.twig` — the page, inline CSS, no JavaScript
- `Resources/config/pimcore/routing.yml` — picked up automatically by Pimcore's `BundleConfigLocator`

## Commands

```bash
make test         # phpunit in Docker
make lint         # php-cs-fixer --dry-run + phpstan
make lint-fix     # php-cs-fixer, also adds the license header to new files
```

Everything runs in Docker; no local PHP or Composer is required.

CI (`.github/workflows/ci.yml`) runs the same pinned tool images, plus the test suite and PHPStan
against a matrix of Pimcore 11 / 12 / 2026. Pimcore 11 does not run on PHP 8.4 and Pimcore 2026
does not run on PHP 8.3, so the matrix lists the combinations explicitly rather than crossing them.
PHPStan runs per matrix entry on purpose: analysed against the Pimcore version that job installed,
it is what catches an API that moved or disappeared between the supported lines.

## Conventions

- `src/` is PSR-12 plus aligned `=>`/`=` (see `.php-cs-fixer.dist.php`), PHPStan level 6.
- `declare(strict_types=1)` everywhere. Checks and services are `final readonly`.
- Tests use `// prepare` / `// test` / `// verify` blocks and PHPUnit attributes.
- Comments explain a non-obvious *why*, one or two lines. Never what the code already says.

## Known limitations

- `CacheCheck` writes through `pimcore.cache.pool`, the PSR-6 pool, not `Pimcore\Cache`. It
  therefore tests the cache backend rather than Pimcore's runtime cache flag — which is the point,
  but it also means a pool fronted by an in-memory adapter can answer the read from memory and hide
  a dead backend.
- `RobotsTxtCheck` only rejects a bare `Disallow: /` line. It does not parse user-agent groups, so
  a `Disallow: /` that applies to one crawler only still fails the check. It also cannot tell which
  site a request would resolve to, so it rejects a `Disallow: /` configured for any of them, and a
  project that configures robots.txt for some sites only passes on the strength of the others.
- **The response time is still weakly correlated with what failed.** The body is opaque and every
  check now runs on every request, so the *ordering* signal is gone, and `timeout_ms` bounds the
  range. But a failing check takes a different amount of time than a passing one, and that remains
  observable. Closing it completely would mean padding every response to a fixed duration, paying
  the worst case on every poll for a pad target nobody can pick honestly. Configure a token
  instead: it makes the channel unreachable rather than merely noisy. Do not "fix" this with
  padding, and do not change the 200/503 distinction — that is the entire purpose of the endpoint.
- `AdminUserCheck` looks for one configured account name; it does not detect a second admin.
- **No CPU or memory checks, deliberately.** A health endpoint is a binary gate; resource trends
  belong in a metrics stack that has history and alerting rules. They were built and removed again.
  If anyone tries once more: PHP cannot measure either one inside a container. In one capped at
  0.5 CPU and 128 MB, `sys_getloadavg()` reported the host's 8.73 over 8 apparent cores and
  `memory_get_usage()` reported the PHP process, while `/sys/fs/cgroup/memory.max` and `cpu.max`
  reported the limits exactly. Only cgroup files tell the truth, and CPU needs cgroup v2 PSI
  (`cpu.pressure`) because a load average says nothing from a single read.
- **No maintenance mode check.** `Tool\Admin::activateMaintenanceMode()` exists in Pimcore 11 and 12
  but is gone in 2026, and no `isMaintenanceModeActive()` exists in any of them. There is no
  cross-version API to build it on.
- **The Messenger checks are audit checks and answer `n/a` without the table.** `messenger_messages`
  is not in the install dump - Symfony's Doctrine transport creates it on first use - and the
  failure transport name is per project (`messenger.failed_queue_names`, plus any `*_failed`). Both
  are configuration, never a failure, and none of it reaches the monitoring endpoint.
- **Directory sizes need `du`.** `Util\DirectorySize` has no PHP fallback: a recursive scan of a
  large project has no upper bound. Without `du`, or past `audit.directory_size_timeout_s`, the
  size is `n/a`.
- **Two asset storage checks, on purpose.** Core `AssetStorageCheck` probes writability of the
  Flysystem storage; audit `AssetStorageSizeCheck` counts assets and sizes `public/var/assets`.
  Pimcore 11+ keeps no file size in the `assets` table.
- **`PimcoreAdminSession` calls `Pimcore\Tool\Authentication::authenticateSession()`**, a static
  Pimcore helper, behind `Security\AdminSessionInterface` so everything above it is testable. It
  is the one place that reads Pimcore state statically; do not add a second.
- **`PimcoreConfigurationCheck` and the plain core checks show no message on a green row.** Their
  contract is `check(): void`; the dashboard prints `—`. Converting them to reporting checks was
  deliberately left out of 2.1 to keep the monitoring path untouched.
