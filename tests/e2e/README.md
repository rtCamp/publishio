# Publishio E2E tests

Playwright tests in plain JavaScript. One config, one runner, one reusable workflow. Set up once;
after that, runs change only through `--project`, environment variables and workflow inputs.

## Setup

Dependencies (`@playwright/test`, `@wordpress/e2e-test-utils-playwright`) are in `package.json`.

```bash
npm ci
npx playwright install chromium
```

CI: `.github/workflows/e2e.yml` runs `smoke` (in 2 parallel shards) and `install` on latest WordPress on every
pull request (on the PR branch), adds `smoke-wp69` and `install-wp69` on pushes to `main`, and manually (Actions → E2E → Run workflow →
pick the branch and projects). `release.yml` runs `install` on the release zip before the
WordPress.org deploy, which waits for approval on the `production` environment. Add QA as required
reviewers on `production`. Slack alerts are commented out in the workflows (marked `SLACK`) until a
webhook is available; then uncomment them and add the `SLACK_WEBHOOK_URL` secret.

## Scheduled runs and manual QA

| Workflow                    | When                                              | What                                                                                                                                               |
| --------------------------- | ------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------- |
| `e2e-scheduled.yml` nightly | Sun-Fri 02:00 IST, skipped if `main` is unchanged | All tests on 12 combinations: `full` on PHP 8.2/8.3/8.4 × Twenty Twenty-Five/Ollie, `install` on PHP 8.2/8.3/8.4 plus Ollie, and both WP 6.9 sites |
| `e2e-scheduled.yml` weekly  | Sunday 02:00 IST                                  | Every combination on WordPress latest and nightly (trunk), including `smoke-multisite` (30 jobs)                                                   |
| `qa-execution-runner.yml`   | Push to `release/**`                              | Full automated suite on the release build, then (only if green) the manual QA checklist issue from `rtCamp/qa` → `qa-management/publishio/`        |
| `qa-hotfix-runner.yml`      | Push to `hotfix/**`                               | Plugin-update tests, then a smoke-only QA checklist issue                                                                                          |

Scheduled runs report a change of result (a new failure, or back to green) in the run summary;
the Slack alert for it is commented out until a webhook is available.

**Release gate.** On a release tag, `release.yml` runs `bin/qa-release-gate.mjs`: the QA run issue
for `release/<tag>` must be closed with `qa-passed` and not `qa-blocked`, and every bug linked under
"Blockers Found" that is still open must be P2 or P3 (deferred). An open P0/P1, or an open bug
without a priority label, blocks the WordPress.org deploy and the GitHub release. Then the
`production` environment approval applies as before.

One-time setup: the publishio blueprints in `rtCamp/qa` (`qa-management/publishio/smoke-suit.md`,
`regression-suite.md`), the `RTBOT_TOKEN` secret (read access to `rtCamp/qa`), and the labels
`qa-testing`, `release-blocker`, `emergency-hotfix`, `qa-passed`, `qa-blocked`, `P0`-`P3`.

## Sites and projects

Each WordPress site is one wp-env instance. Each Playwright project runs on one site and depends
on that site's setup project (`setup-<site>`), so **only the sites of the selected projects are
prepared**. Both are defined in `tests/e2e/config/sites.json`.

| Project           | Site (port)                                                  | WordPress / PHP                           | Tests                                     |
| ----------------- | ------------------------------------------------------------ | ----------------------------------------- | ----------------------------------------- |
| `smoke`           | `test` (8889), repo mounted                                  | latest, or `WP_ENV_CORE` / wp-env default | `specs/smoke`: SMOKE-01 to SMOKE-09       |
| `full`            | `test` (8889), repo mounted                                  | same                                      | `specs/smoke` + `specs/compat`            |
| `install`         | `install` (8890), plugin installed from the zip              | same                                      | `specs/install`: INSTALL-01 to INSTALL-14 |
| `smoke-wp69`      | `test-wp69` (8892), repo mounted                             | 6.9 / 8.2, pinned                         | same as `smoke`                           |
| `install-wp69`    | `install-wp69` (8891), plugin installed from the zip         | 6.9 / 8.2, pinned                         | same as `install`                         |
| `smoke-multisite` | `test-multisite` (8893), repo mounted on a multisite network | latest, or `WP_ENV_CORE`                  | same as `smoke` (weekly run)              |

The 6.9 smoke site does not load the WordPress.org AI plugin that the latest site has: its current
release requires WordPress 7.0.3, and Publishio does not depend on it.

## Tags: which tests run for which change

| Tag                             | On                                 | Meaning                                                                                                                                                                                                                       |
| ------------------------------- | ---------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `@smoke`, `@install`, `@compat` | describe blocks                    | Which project the test belongs to                                                                                                                                                                                             |
| `@multisite`                    | tests                              | Runs only in `smoke-multisite`: checks INSTALL already covers on single sites (SMOKE-01 activation, rewrite rules, deactivation; SMOKE-02 Guide and Connections render), kept for multisite because it has no install project |
| `@plugin-update`                | every test                         | A plugin change (PR, push to `main`, release) can break it                                                                                                                                                                    |
| `@wp-update`                    | tests that exercise WordPress core | A WordPress update can break it: admin screens, plugin installer and activation, rewrites, roles, the editor, core post and pattern APIs                                                                                      |

Tests of Publishio's own logic carry only `@plugin-update`: OAuth/PKCE rules, MCP protocol errors,
zip contents, upgrading from the previous Publishio release. Tag new tests the same way.

`E2E_UPDATE=plugin-update` or `E2E_UPDATE=wp-update` narrows the selected projects to that tag
(unset or `all` runs everything). The workflows set it for you:

| Workflow               | Trigger                                        | `E2E_UPDATE`    | Projects                                                            |
| ---------------------- | ---------------------------------------------- | --------------- | ------------------------------------------------------------------- |
| `e2e.yml`              | pull request                                   | `plugin-update` | `smoke` (2 shards), `install`: latest WordPress only, kept short    |
| `e2e.yml`              | push to `main`                                 | `plugin-update` | `smoke`, `install`, `smoke-wp69`, `install-wp69`                    |
| `e2e.yml`              | manual                                         | your choice     | your choice                                                         |
| `release.yml`          | release tag                                    | `plugin-update` | `install` on the release zip                                        |
| `wp-release-watch.yml` | new WordPress version (daily check), or manual | `wp-update`     | `full` (3 PHP/theme pairs) + `install` with the latest released zip |

```bash
E2E_UPDATE=wp-update node bin/e2e.mjs install test
```

| File                                        | Role                                                                                                                                                                                                                                                                                             | Edit when                   |
| ------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | --------------------------- |
| `playwright.config.js`                      | The only Playwright config; builds the projects from `sites.json`                                                                                                                                                                                                                                | Never per run               |
| `tests/e2e/config/sites.json`               | Sites and projects                                                                                                                                                                                                                                                                               | Adding a site or project    |
| `tests/e2e/config/env.js`                   | Reads run settings; `currentSite()` gives a test its project's site                                                                                                                                                                                                                              | Never per run               |
| `tests/e2e/setup/site.setup.js`             | The `setup-<site>` projects: start the site if it is down, then theme, permalinks, plugin state, admin login                                                                                                                                                                                     | Changing the starting state |
| `tests/e2e/setup/site.teardown.js`          | The `teardown-<site>` projects: stop the site after its projects if setup started it                                                                                                                                                                                                             | Never per run               |
| `bin/wp-env-down.mjs`                       | Confirms every wp-env environment is down and stops any that are not (last CI step)                                                                                                                                                                                                              | Never per run               |
| `.wp-env.*.json`                            | One per site                                                                                                                                                                                                                                                                                     | Never per run               |
| `.github/workflows/reusable-e2e-public.yml` | Runs one project on GitHub-hosted runners; same name, inputs and steps as rtCamp/plugin-skeleton-d's, plus optional `project`, `wp-version`, `theme`, `zip-artifact`, `update`, `extra-tags`. Called by `e2e.yml`, `e2e-scheduled.yml`, `release.yml`, `wp-release-watch.yml` and the QA runners | Never per run               |

| Variable                    | Example                                                         | Default                  |
| --------------------------- | --------------------------------------------------------------- | ------------------------ |
| `WP_ENV_CORE`               | `WordPress/WordPress#7.0` (unpinned sites only)                 | latest                   |
| `WP_ENV_PHP_VERSION`        | `8.3` (unpinned sites only)                                     | wp-env default           |
| `E2E_THEME`                 | `ollie`                                                         | `twentytwentyfive`       |
| `E2E_TAGS`                  | `@oauth @patterns` (from impact analysis)                       | none                     |
| `PUBLISHIO_ZIP`             | `artifacts/zips/publishio.zip`                                  | same                     |
| `E2E_SLOWMO`                | `500` (ms per browser action, for `--headed`)                   | `0`                      |
| `E2E_KEEP_SITES`            | `1`: teardown never stops sites                                 | unset                    |
| `E2E_STOP_SITES`            | `1`: teardown stops every used site, even ones it did not start | unset                    |
| `WP_BASE_URL`, `WP_CLI_CMD` | staging URL and its WP-CLI                                      | the site's wp-env values |

## Site lifecycle

Each site's setup project starts its wp-env instance if it is not running (first start pulls
Docker images, a few minutes), and its teardown project stops it once every project using it has
finished, including after failures. Teardown only stops sites that setup started, so a site you
started yourself keeps running between local runs. In CI nothing runs beforehand, so every site is
started and stopped by the run, and a final always-run step (`node bin/wp-env-down.mjs`) confirms
all environments are down and stops any that are not.

## Run locally

```bash
bin/get-plugin-zip.sh <branch>          # or --build / --release; needed by install projects

node bin/e2e.mjs install test           # starts the site if needed, stops it afterwards
node bin/e2e.mjs install start          # or keep a site up between runs; then teardown leaves it
node bin/wp-env-down.mjs                # stop every E2E site

# Several projects, one report:
npx playwright test --project=install --project=install-wp69 --project=smoke --project=smoke-wp69

# Watch it:
E2E_SLOWMO=500 node bin/e2e.mjs install test --headed
```

Reports, screenshots, traces and `results.json` land in `artifacts/`
(`npx playwright show-report artifacts/report`).

## Notes

- **OAuth clients**: Publishio identifies AI apps by Client ID Metadata Document URLs (CIMD). Tests
  put a validated client record into the plugin's CIMD cache via WP-CLI (`utils/oauth.js`) instead of
  hosting a public HTTPS document; authorize, consent and token exchange run unmodified.
- **INSTALL-09** only runs on WordPress below the plugin's minimum; start an install site with an older `WP_ENV_CORE`.
- **Known-bug markers:** tests for known bugs are skipped with `test.fixme` (with the reason) so CI stays green:
  SMOKE-04 blocked-discovery warning (#118), SKILL-04, SKILL-05 and SKILL-08 (skill/plugin mismatches). Remove the `fixme` once fixed. COMPAT-01 checks that user-defined
  (synced/unsynced) patterns are _not_ listed: get-patterns covers registered patterns only, by design (#119 tracks support).
  #126 (Connections blank on WordPress 6.9) is fixed by #139: INSTALL-07, SMOKE-02, SMOKE-03 and ISSUE-126 now pass on 6.9.
- **GitHub issues** have regression tests in `specs/smoke/issue-regressions.spec.js` (ISSUE-11, 12, 14, 118, 126):
  closed issues must stay fixed; open ones are skipped with `test.fixme` until fixed.
- **INSTALL-10** installs the latest GitHub release first (override with `PUBLISHIO_PREVIOUS_ZIP`). From
  1.1.0 (dynamic client registration) the upgrade removes old connections by design (#129); the test
  checks that, then connects again.
- Tests run one at a time because each WordPress site has one shared database.
