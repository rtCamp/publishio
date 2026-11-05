# GitHub Workflows

Workflows are defined to be reusable and modular.

### Code Review: [`ci.yml`](ci.yml)

Main CI pipeline used to validate code. Based on file changes it calls the following reusable workflows:

| Reusable Workflow                                                                | What                                      |
| -------------------------------------------------------------------------------- | ----------------------------------------- |
| [`reusable-phpcs.yml`](reusable-phpcs.yml)                                       | PHPCS linting                             |
| [`reusable-phpstan.yml`](reusable-phpstan.yml)                                   | PHPStan static analysis                   |
| [`reusable-phpunit.yml`](reusable-phpunit.yml)                                   | PHPUnit tests, also on multisite networks |
| [`reusable-lint-css-js.yml`](reusable-lint-css-js.yml)                           | ESLint, Stylelint, Prettier, tsc linting  |
| [`reusable-jest.yml`](reusable-jest.yml)                                         | Jest tests                                |
| [`reusable-build.yml`](reusable-build.yml)                                       | Creates a build zip (used by playground)  |
| [`reusable-wp-playground-pr-preview.yml`](reusable-wp-playground-pr-preview.yml) | PR preview environment with wp-playground |

### E2E: [`e2e.yml`](e2e.yml), [`e2e-scheduled.yml`](e2e-scheduled.yml), [`wp-release-watch.yml`](wp-release-watch.yml)

Run the Playwright end-to-end tests through [`reusable-e2e-public.yml`](reusable-e2e-public.yml): on pull requests and pushes to `main`, nightly and weekly, and when WordPress ships a new release. See [the E2E README](../../tests/e2e/README.md#in-ci-github-actions) for what runs when.

### [`copilot-setup-steps.yml`](copilot-setup-steps.yml)

Sets up dev environment for GitHub Copilot coding agent.

### [`copilot-code-review.yml`](copilot-code-review.yml)

Setup steps for Copilot code review on pull requests.

### [`pr-title.yml`](pr-title.yml)

Triggers on PRs. Validates [Conventional Commit](https://www.conventionalcommits.org/en/v1.0.0/) format to keep `main` history clean.

### [`release.yml`](release.yml)

Triggers on bare `X.Y.Z` tag pushes. Builds the plugin via `reusable-build.yml`, creates the GitHub Release with the zip attached, and deploys to WordPress.org via SVN. A manual `workflow_dispatch` from `main` runs the same pipeline in dry-run. See [Releasing](../../docs/DEVELOPMENT.md#releasing) for details.

## Configuration

1. `php-version`
2. `ci.yml:phpunit` matrix.

### Secrets

| Secret          | Required By                                      | Notes                                                |
| --------------- | ------------------------------------------------ | ---------------------------------------------------- |
| `CODECOV_TOKEN` | `reusable-phpunit.yml` <br />`reusable-jest.yml` | Optional - coverage uploads fail silently without it |
| `SVN_USERNAME`  | `release.yml`                                    | WordPress.org committer username.                    |
| `SVN_PASSWORD`  | `release.yml`                                    | WordPress.org committer password.                    |

### PR Previews

WordPress Playground requires a public URL for the plugin zip. By default, the GitHub action will attach release assets to the `ci-artifacts` release; after the first run, a draft release will be created which you must publish (as a pre-release) before PR Previews will work.

For private repositories, you can configure [`WordPress/action-wp-playground-pr-preview/.github/actions/expose-artifact-on-public-url`](https://github.com/WordPress/action-wp-playground-pr-preview) to expose the artifact on a publicly accessible URL without needing to publish a release, e.g. an S3 bucket or temporary server.

### Testing Workflows Locally

You can use [act](https://github.com/nektos/act) to test GitHub workflows locally. The examples below use inline inputs and inline secrets only (no external JSON or .env files).

```bash
# List workflows available in this repo
act -l

# Run the full CI as a push event (map ubuntu-24.04 to an act-compatible image)
act push -P ubuntu-24.04=catthehacker/ubuntu:act-latest

# Run the `detect` job for a pull request event
act pull_request -j detect -P ubuntu-24.04=catthehacker/ubuntu:act-latest

# Trigger `ci.yml` via workflow_dispatch and run the `phpunit` job with specific inputs and secrets
act workflow_dispatch \
	--input php-version=8.2 \
	--input wp-version=latest \
	--input coverage=true \
	-j phpunit \
	-s CODECOV_TOKEN=your_codecov_token_here \
	-s GITHUB_TOKEN=your_github_token_here \
	-P ubuntu-24.04=catthehacker/ubuntu:act-latest
```
