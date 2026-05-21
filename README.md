# Publish with AI

[![Try in WordPress Playground](https://img.shields.io/badge/Try%20in-WordPress%20Playground-blue?logo=wordpress)](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/rtCamp/publishwithai/main/blueprint.json)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](LICENSE.md)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue?logo=php)](composer.json)
[![WordPress](https://img.shields.io/badge/WordPress-6.x%2B-blue?logo=wordpress)](https://wordpress.org)<br>
[![CI](https://github.com/rtCamp/publishwithai/actions/workflows/ci.yml/badge.svg)](https://github.com/rtCamp/publishwithai/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/rtCamp/publishwithai/branch/main/graph/badge.svg)](https://codecov.io/gh/rtCamp/publishwithai)
[![GitHub commits since latest release](https://img.shields.io/github/commits-since/rtCamp/publishwithai/latest)](https://github.com/rtCamp/publishwithai/releases)

---

A WordPress plugin to enhance the publishing experience with AI capabilities. This plugin allows users to leverage AI for content generation and editing from AI apps like Claude, ChatGPT, etc.

## Documentation

- **[Development Guide](docs/DEVELOPMENT.md)** - Local setup, commands, testing, and contribution guidelines.
- **[Contributing](docs/CONTRIBUTING.md)** - How to contribute to this project.
- **[Code of Conduct](docs/CODE_OF_CONDUCT.md)** - Community standards.
- **[Security](docs/SECURITY.md)** - Reporting security vulnerabilities.

**Reference:**
[Action Hooks](docs/reference/actions.md) | [Filter Hooks](docs/reference/filters.md) | [Constants](docs/reference/constants.md) | [WP-CLI Commands](docs/reference/cli.md)

## Project Structure

```
├── .github/workflows/      # CI/CD workflows
├── docs/                   # Development guides and references
├── inc/                    # Plugin-specific PHP source
├── framework/              # Reusable framework (shared across plugins)
│   └── README.md
├── src/                    # TypeScript/JS entry points
│   └── README.md
├── templates/              # PHP templates with theme override support
└── tests/                  # PHPUnit, Jest, Playwright tests
```

See [./docs/DEVELOPMENT.md](docs/DEVELOPMENT.md#directory-structure) for a detailed directory tree and descriptions.

## License

GPL-2.0-or-later. See [LICENSE.md](LICENSE.md).

<a href="https://rtcamp.com/"><img src="https://rtcamp.com/wp-content/uploads/sites/2/2019/04/github-banner@2x.png" alt="Join us at rtCamp, we specialize in providing high performance enterprise WordPress solutions"></a>
