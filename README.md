# Pimcore Plugin Health Check

[![CI](https://github.com/basilicom/pimcore-plugin-health-check/actions/workflows/ci.yml/badge.svg)](https://github.com/basilicom/pimcore-plugin-health-check/actions/workflows/ci.yml)

License: MIT — see [LICENSE.txt](LICENSE.txt)

## Version information

| Bundle Version | PHP   | Pimcore                    |
|----------------|-------|----------------------------|
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

To see the reasons directly, run the console command — it has the full picture:

```
bin/console basilicom:health-check
```

It prints one row per active check and exits non-zero if any of them failed, which makes it usable
as a deployment gate.

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

The token must be at least 16 characters. Generate one with `openssl rand -hex 24` and keep it in
the environment, not in a committed file.

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
| `RobotsTxtCheck` | `robots.txt` is missing, unreadable, or contains `Disallow: /` | failure | `robots_txt` | on |

Checks run in the order listed. The cheap local ones come first on purpose: a hung dependency
further down must not eat the timeout budget before the free diagnostics have run.

Everything added after 1.x is **off by default**, so an upgrade never turns a green system red.

Every active check runs on every request — the endpoint does not stop at the first failure, so all
failures reach the log and the response time does not betray which check failed. A run is bounded
by `timeout_ms` (default 5000): once the budget is spent no further check is started, and the
response is a failure. A check already running is not interrupted, so the budget caps how much a
hung dependency can cost, it does not cap a single check.

### Configuration

Every check is on by default except `database_latency`. A check is either a boolean or a block:

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

## Upgrading from 1.x

See [CHANGELOG.md](CHANGELOG.md) — 2.0 changes the HTTP status code on failure, moves the admin
user check into its own switch, and logs to Monolog instead of the Pimcore application logger.
