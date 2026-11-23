# System Health in the Pimcore backend (classic and Studio)

## Intent

Admins should see the result of the last audit run inside the Pimcore backend instead of having to
know about `/health-check`. Two parts, in both admin UIs:

1. **A menu entry "System Health"** that opens the existing HTML report as a tab inside the backend,
   always available to admins.
2. **A notification** when the last stored run has at least one problematic check, styled after
   Pimcore's own "It seems that the maintenance isn't set up properly" notice. Clicking it opens the
   same report tab.

Who asked for what: the user asked for the notification ("admins should see a notification in the
notifications menu, if at least one check is problematic"), for it to open the HTML report, for the
report to open in the backend via the iframe opener, for classic and Studio, and for the menu entry.
Everything else below is marked as a call made in the design conversation.

## Goals

- Classic admin (Pimcore 11 and 12 with `pimcore/admin-ui-classic-bundle`): a menu entry and a
  notifications-menu entry with badge count.
- Studio (Pimcore 12 with `pimcore/studio-ui-bundle`, and 2026): a main navigation entry and a
  notice after login.
- Both open the report as a backend tab with the same URL the dashboard already serves
  (`dashboard.path`, default `/health-check`).
- Admins only, using the same rule as the report itself (`User::isAdmin()`), in both UIs.
- Works on a project with only one of the two UIs installed, or both (Pimcore 12 dual setup).

## Non-goals

- A new report UI. The existing Twig page is reused as-is inside the iframe.
- Running checks from the backend. Everything reads the stored run (AGENTS.md: "The dashboard and
  the API never run a check").
- A warning for a stale run (older than N hours). Possible follow-up.
- Pimcore inbox notifications (sent messages). Ruled out: they pile up per run and do not clear.

## Anti-goals

- No new hard dependency in `composer.json` on either admin UI bundle. Both integrations are
  registered only when their UI bundle is installed (pattern from data-flow-studio-bundle:
  `services_studio.yaml` loaded only when `PimcoreStudioUiBundle` is in `kernel.bundles`).
- No change to the monitoring endpoints, their bodies or their access rules.
- The report and its data are never shown to non-admins: the server decides, the frontend only
  hides.
- Not loading the full run on every classic backend page load beyond one settings-store read.

## Constraints (from the project)

- AGENTS.md hard constraints apply: a page view costs one settings-store read; unauthorised is 404 with
  an empty body; `n/a` and `skipped` are not verdicts.
- AGENTS.md now says "There is no Studio frontend." This design changes that; AGENTS.md is updated
  with the new layout.
- Supported lines: Pimcore 11 (classic only), 12 (classic, Studio or both), 2026 (Studio only).
- Studio's firewall uses the `pimcore_admin` session context, so `Security\PimcoreAdminSession`
  (`Tool\Authentication::authenticateSession()`) should recognise a Studio login (Symfony stores it
  under `_security_pimcore_admin`, the key that method reads). **Verified first, on Pimcore 12 Studio
  and 2026.** If it fails, the fix is part of this work (decided by the user): a Studio-aware lookup
  behind `Security\AdminSessionInterface`, so `AccessGuard` and everything above it stay unchanged. It
  stays the single place that reads Pimcore state statically (AGENTS.md), with its own unit tests and an
  AGENTS.md update. Rules that do not move: admin flag, 404 with an empty body, API key unchanged.

## Approaches

Picked in the conversation: **1. Classic menu entry + Studio plugin**, with the report opened in an
iframe tab.

1. **Classic ExtJS integration + Studio React plugin (picked).** Both UIs get a live status with a
   clickable link that clears once a later run is healthy, matching the maintenance notice. Cost: a JS
   toolchain and a build step for Studio.
2. Pimcore inbox notification sent by the audit command. No frontend code, reaches both UIs, but it is
   a message, not a status: it stays after a fix and the link is plain text in Studio.
3. Classic menu entry + inbox notification for Studio. Cheaper, but the UIs behave differently and
   Studio has no clickable link.

For the classic side, the notification data comes from the page settings (`IndexActionSettingsEvent`)
rather than a browser request to the API, because the classic menu is built synchronously at load and
a late answer cannot add a badge cleanly.

## Design

### Shared: the summary

- A small read-only service, e.g. `Audit\BackendSummary`, built on `RunStoreInterface`. It returns
  `null` when there is no stored run, or `{critical: int, warning: int, generated_at: string}` from
  `StoredRun::summary()`. "Problematic" means `critical + warning > 0`; `n/a` and `skipped` never count.
- It is used by the classic settings subscriber and by a Studio-facing endpoint, so both UIs count the
  same way.

### Classic admin (ExtJS)

- **Settings:** `EventListener\ClassicAdminSettingsSubscriber` on `AdminEvents::INDEX_ACTION_SETTINGS`
  sets `settings.health_check = {url, title, critical, warning}` only for admins and only when the
  dashboard is enabled. `critical`/`warning` are `0` when the run is healthy and absent before the first
  run. One settings-store read per backend load.
- **Assets:** one JS file `Resources/public/js/classic/health-check.js`, registered through the core
  `BundleManagerEvents::JS_PATHS` event (no classic-admin interface on the bundle class).
- **Menu entry:** on `pimcore.events.preMenuBuild`, an item "System Health" for admins. Placement: the
  Tools menu (`menu.extras`, which is what the classic toolbar calls Tools) when it exists; the builder
  checks the key on 11 and 12.
- **Notification:** in the same handler, when `critical + warning > 0`, an item in
  `menu.notification.items` reading e.g. "Health check: 2 critical, 5 warnings", plus
  `pimcore.notification.helper.incrementCount()`.
- **Open:** both items call
  `pimcore.helpers.openGenericIframeWindow('basilicom_health_check', url, iconCls, title)`, which
  reuses the tab when it is already open.
- **Registration:** only when the classic admin bundle is installed
  (`PimcoreAdminBundle` in `kernel.bundles`); otherwise nothing is registered.

### Studio (React plugin)

- **Supported Studio lines (decided by the user): Pimcore 12 (studio-ui-bundle 2025.x) and 2026
  (2026.x).** A module-federation remote is built against one SDK line, so there are **two builds** of
  one source tree: `assets/studio/` with rsbuild + module federation + `@pimcore/studio-ui-bundle`,
  following data-flow-studio-bundle, compiled once per SDK line. The source stays shared; only what
  differs between the SDK lines is switched per target, kept as small as possible.
- **Output:** `src/Resources/public/studio/build/2025/` and `.../2026/`, both committed so that
  consumers need no Node.
- **Registration:** `Integration\StudioEntryPointProvider` implementing
  `WebpackEntryPointProviderInterface`, tagged `pimcore_studio_ui.webpack_entry_point_provider`, loaded
  from `services_studio.yaml` only when `PimcoreStudioUiBundle` is installed. It returns the
  `entrypoints.json` of the build that matches the installed studio-ui-bundle version (read from
  Composer's `InstalledVersions`), and nothing for an unknown line.
- **Data:** a JSON endpoint `GET <dashboard.api_path>/summary` returning the summary above and the
  report URL. It goes through `Security\AccessGuard` like the existing API (404 for non-admins), reads
  one settings-store entry, never runs a check.
- **Menu entry:** a main navigation item "System Health", shown to admins only, that opens Studio's
  built-in `iframe` widget (`IframeContent`) as a main tab with the report URL. One widget id, so a
  second click focuses the existing tab.
- **Notification:** after login, for admins, the plugin fetches the summary once. When
  `critical + warning > 0` it shows a notice in the `global.feedback` slot ("Health check: 2 critical,
  5 warnings", with an "Open report" action that opens the same tab). It does not reappear in the same
  browser session after being closed.

### The report page

- Unchanged in content. Add `X-Frame-Options: SAMEORIGIN` (and `Content-Security-Policy:
  frame-ancestors 'self'`) to the dashboard response: the backend can frame it, other sites cannot.

### Configuration

- `dashboard.backend_integration` (bool, default `true`) switches off both UIs' menu entry and
  notification together. The report and API stay as configured by `dashboard.enabled`.
- `dashboard.enabled: false` also removes both integrations, since there is nothing to open.

### Text and translations

- English and German strings for "System Health", the notification text and "Open report", in the
  domains each UI reads (classic `admin`, Studio `studio`).

### Tooling and docs

- Makefile: `make build` (both Studio builds in a `node` container), and the build step added to CI so
  a stale committed build fails the pipeline.
- Demo: the default Pimcore 11 demo covers classic. Studio needs a Pimcore 12.3 demo with the Studio
  bundles (classic + Studio side by side, as data-flow-io's dual rig does) and a 2026 demo. Both are
  licensed (`DEMO_SKELETON`, licence trio in `.env.local`); the user provides the licence later for
  testing. The builder makes sure `run.sh` installs the Studio bundles there and runs `assets:install`.
- Until the licence is there, the Studio parts can be built and unit-tested but not verified in a
  running backend; the plan must say so and keep that verification as an open step.
- AGENTS.md (constraints, layout, "no Studio frontend" sentence), README (new section with
  screenshots of both UIs), CHANGELOG.

## Calls made in the conversation (open to correction)

- Menu label "System Health"; classic under Tools, Studio in the main navigation.
- Problematic = at least one `warning` or `critical`; no notification before the first run.
- One config switch for both UIs, on by default.
- Studio notice once per browser session, not on every page change.
- `X-Frame-Options: SAMEORIGIN` on the report.
- Built Studio assets are committed.

## Left to the builder

- Exact ExtJS menu key and icon class per Pimcore version, and the Studio icon.
- The Studio SDK calls for main navigation and opening a widget (see the `pimcore-studio-ui-*` skills).
- Test layout: PHP unit tests for the summary, the subscriber (admin / non-admin / healthy / unhealthy /
  no run / disabled) and the summary endpoint; wiring test for conditional registration; a vitest test
  for the Studio plugin's show/hide logic.
