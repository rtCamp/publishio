# Publishio E2E tests

End-to-end tests for Publishio, written with [Playwright](https://playwright.dev). Each test runs
against a real WordPress site started with [wp-env](https://www.npmjs.com/package/@wordpress/env)
(Docker).

## Run them locally

**You need:** Node (version in `.nvmrc`), Composer and Docker running.

**One-time setup:**

```bash
composer install
npm ci
npm run build
npx playwright install chromium
```

**Run a group of tests:**

```bash
npm run test:e2e -- smoke test     # main checks on the latest WordPress
npm run test:e2e -- install test   # install, upgrade and uninstall from the plugin zip
```

The WordPress site starts by itself and stops when the tests finish (the first start downloads
Docker images and takes a few minutes). Before the `install` tests, build the plugin zip once:
`bin/get-plugin-zip.sh --build`.

**Other ways to run:**

```bash
npm run test:e2e -- smoke test -g "SMOKE-10"   # only tests whose title matches
npm run test:e2e -- smoke test --headed        # watch the browser
npm run test:e2e -- smoke start                # keep a site running between runs
node bin/wp-env-down.mjs                       # stop every test site
```

**See the results:** `npx playwright show-report artifacts/report` opens the HTML report with
screenshots, errors and traces.

## Test groups

| Group             | What it covers                                                 | WordPress     |
| ----------------- | -------------------------------------------------------------- | ------------- |
| `smoke`           | Admin screens, Status page, OAuth, MCP tools, editor, AI skill | latest        |
| `install`         | Install from the zip, activate, upgrade, uninstall             | latest        |
| `smoke-wp69`      | Same as `smoke`                                                | 6.9 (minimum) |
| `install-wp69`    | Same as `install`                                              | 6.9 (minimum) |
| `full`            | `smoke` plus pattern-compatibility checks                      | latest        |
| `smoke-multisite` | Same as `smoke`, on a multisite network                        | latest        |

## In CI (GitHub Actions)

| When                                  | What runs                                                               | Workflow               |
| ------------------------------------- | ----------------------------------------------------------------------- | ---------------------- |
| Every pull request                    | `smoke` (2 parallel jobs) and `install`, latest WordPress               | `e2e.yml`              |
| Every push to `main`                  | The same, plus `smoke-wp69` and `install-wp69`                          | `e2e.yml`              |
| Nightly, Sun–Fri 20:30 UTC, on `main` | All tests on PHP 8.2–8.4 and two themes; skipped if `main` is unchanged | `e2e-scheduled.yml`    |
| Weekly, Sat 20:30 UTC, on `main`      | Every combination, including multisite and WordPress trunk              | `e2e-scheduled.yml`    |
| New WordPress release                 | `full` and `install` tests affected by WordPress updates                | `wp-release-watch.yml` |
| Manual                                | Your choice of groups, versions and theme                               | `e2e.yml`              |

**Run manually:** Actions → **E2E** → **Run workflow**, pick the branch and the groups.

**Results:** each run's summary page shows a passed / failed / flaky / skipped table. The full HTML
report is the **e2e-report** download at the bottom of the run page (unzip it, open `index.html`).

A failing test is retried once in CI. If it passes on the retry it is reported as **flaky** and the
run stays green; if it fails again, the run fails.

## Add a test

1. Add a `*.spec.js` file under `tests/e2e/specs/smoke/` (or `install/`), named after its ID, e.g.
   `smoke-11-my-feature.spec.js`.
2. Import `test` and `expect` from `../../fixtures`, and start each title with the ID
   (`SMOKE-11 …`).
3. Tag it: the `describe` block gets `@smoke` (or `@install`) and `@plugin-update`; add
   `@wp-update` to tests that also depend on WordPress itself (admin screens, editor, plugins page).
4. Prefer user-facing locators (`getByRole`, `getByLabel`, `getByText`) so tests wait for the page
   on their own.
5. A test for a known bug: keep it, with `test.fixme( true, '<reason>' )` until the bug is fixed.
