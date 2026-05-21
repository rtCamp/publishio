# GitHub Workflows

Workflows are defined to be reusable and modular.

### Code Review: [`ci.yml`](ci.yml)

Main CI pipeline used to validate code. Based on file changes it calls the following reusable workflows:

| Reusable Workflow                                                                                                                                                                      | What                                      |
| -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------- |
| [`reusable-phpcs.yml`](reusable-phpcs.yml) <br /> [`reusable-phpcs-public.yml`](reusable-phpcs-public.yml)                                                                             | PHPCS linting                             |
| [`reusable-phpstan.yml`](reusable-phpstan.yml) <br /> [`reusable-phpstan-public.yml`](reusable-phpstan-public.yml)                                                                     | PHPStan static analysis                   |
| [`reusable-phpunit.yml`](reusable-phpunit.yml) <br /> [`reusable-phpunit-public.yml`](reusable-phpunit-public.yml)                                                                     | PHPUnit tests                             |
| [`reusable-lint-css-js.yml`](reusable-lint-css-js.yml) <br /> [`reusable-lint-css-js-public.yml`](reusable-lint-css-js-public.yml)                                                     | ESLint, Stylelint, Prettier, tsc linting  |
| [`reusable-jest.yml`](reusable-jest.yml) <br /> [`reusable-jest-public.yml`](reusable-jest-public.yml)                                                                                 | Jest tests                                |
| [`reusable-e2e.yml`](reusable-e2e.yml) <br /> [`reusable-e2e-public.yml`](reusable-e2e-public.yml)                                                                                     | Playwright end-to-end tests               |
| [`reusable-build.yml`](reusable-build.yml) <br /> [`reusable-build-public.yml`](reusable-build-public.yml)                                                                             | Creates a build zip (used by playground)  |
| [`reusable-wp-playground-pr-preview.yml`](reusable-wp-playground-pr-preview.yml) <br /> [`reusable-wp-playground-pr-preview-public.yml`](reusable-wp-playground-pr-preview-public.yml) | PR preview environment with wp-playground |

Reusable workflows have a `-public` variant which is used for public GitHub runners. The non-public variants are used for rtCamp's private runners. Ensure that `ci.yml` points to the correct workflows you need, and delete the others.

### [`copilot-setup-steps.yml`](copilot-setup-steps.yml)

Sets up dev environment for GitHub Copilot coding agent.

### [`pr-title.yml`](pr-title.yml)

Triggers on PRs. Validates [Conventional Commit](https://www.conventionalcommits.org/en/v1.0.0/) format, required for release-please automation.

### [`release.yml`](release.yml)

Triggers on push to `main`. Uses [release-please](https://github.com/googleapis/release-please) to automate releases based on conventional commits.

When a release is created, it builds the plugin via `reusable-build.yml` and uploads the zip artifact to the GitHub release.

## Configuration

1. `php-version`
2. `ci.yml:phpunit` matrix.

### Secrets

| Secret          | Required By                                                    | Notes                                                |
| --------------- | -------------------------------------------------------------- | ---------------------------------------------------- |
| `CODECOV_TOKEN` | `reusable-phpunit-public.yml` <br />`reusable-jest-public.yml` | Optional - coverage uploads fail silently without it |

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
