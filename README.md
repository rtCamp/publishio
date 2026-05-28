# Publish with AI

[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](LICENSE.md)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue?logo=php)](composer.json)
[![WordPress](https://img.shields.io/badge/WordPress-7.0-blue?logo=wordpress)](https://wordpress.org)<br>
[![CI](https://github.com/rtCamp/publishwithai/actions/workflows/ci.yml/badge.svg)](https://github.com/rtCamp/publishwithai/actions/workflows/ci.yml)
[![GitHub commits since latest release](https://img.shields.io/github/commits-since/rtCamp/publishwithai/latest)](https://github.com/rtCamp/publishwithai/releases)

---

Build WordPress pages and posts using your existing patterns directly from your favorite AI assistant. This plugin auto-discovers your site's patterns, allowing AI platforms like Claude and ChatGPT to build structured content.

[<img src="https://img.shields.io/badge/Download-Now-green?style=for-the-badge&logo=github" alt="Download Now" height="40">](https://github.com/rtCamp/publishwithai/releases/latest/download/rtcamp-publish-with-ai.zip)

## 👥 Contributors

[@Utsav-Ladani](https://github.com/Utsav-Ladani)
[@danishshakeel](https://github.com/danishshakeel)

## ✨ Features

- **Pattern Auto-Discovery:** Automatically detects patterns from your active theme.
- **Structured Content Generation:** AI builds pages using proper block structures, avoiding messy HTML insertion.
- **Platform Agnostic:** Works with major AI platforms like Claude and ChatGPT via Model Context Protocol (MCP).
- **SEO & Metadata:** Automatically adds metadata and SEO details (compatible with Yoast).
- **In-Chat Previews:** Shows live previews of your generated content directly within the AI chat interface.
- **Full Compatibility:** Works seamlessly with both Posts and Pages, ensuring 100% Block Editor compatibility.

## 📋 Requirements

### WordPress

- **WordPress 7.0**
- For WordPress versions **below 7.0**, the [official WordPress AI plugin](https://wordpress.org/plugins/ai/) must be active.

### Themes

- Tested with **Twenty Twenty-Five** and **Ollie**.
- Compatible with any theme. For best results, ensure patterns have descriptive names and descriptions.

#### Importance of proper naming and description

This metadata provides the semantic context the AI needs to understand the purpose and structure of each pattern, enabling it to select the most appropriate ones for your content.

## 🚀 Quick Start

1. Install and activate the plugin in your WordPress dashboard.
2. Ensure you meet the [Requirements](#-requirements) (WP 7.0 or the official AI plugin).
3. Navigate to the plugin settings to connect your AI platform (Claude/ChatGPT).
4. Start prompting your AI to build WordPress pages using your theme's patterns.

## ❓ FAQ

**Q: Do I need a specific theme?**
A: No, it works with any theme, but themes with well-described patterns (like Twenty Twenty-Five) yield the best results.

**Q: Why do I need the official WordPress AI plugin on older versions?**
A: WordPress 7.0 introduced core features that this plugin relies on. The official AI plugin backports those features to older versions.

## 📚 Documentation

- **[Development Guide](docs/DEVELOPMENT.md)** - Setup, commands, testing, and contribution.
- **[Contributing](docs/CONTRIBUTING.md)**
- **[Code of Conduct](docs/CODE_OF_CONDUCT.md)**
- **[Security](docs/SECURITY.md)**

## 🏗️ Project Structure

```text
├── .github/workflows/      # CI/CD workflows
├── assets/                 # Static assets (CSS, images)
├── docs/                   # Development guides and references
├── framework/              # Reusable framework (shared across plugins)
│   └── README.md
├── inc/                    # Plugin-specific PHP source
│   └── Modules/
│       ├── MCP/            # Model Context Protocol abilities
│       ├── Screenshot/     # Screenshot generation API
│       └── Settings/       # Plugin settings and connections
├── src/                    # TypeScript/JS entry points
│   └── README.md
├── templates/              # PHP templates with theme override support
└── tests/                  # PHPUnit, Jest, Playwright tests
```

See [./docs/DEVELOPMENT.md](docs/DEVELOPMENT.md#directory-structure) for a detailed directory tree and descriptions.

## 📄 License

GPL-2.0-or-later. See [LICENSE.md](LICENSE.md).

<a href="https://rtcamp.com/"><img src="https://rtcamp.com/wp-content/uploads/sites/2/2019/04/github-banner@2x.png" alt="Join us at rtCamp, we specialize in providing high performance enterprise WordPress solutions"></a>
