# Publishio – Build Pages & Posts with ChatGPT and Claude using MCP

[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](LICENSE.md)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue?logo=php)](composer.json)
[![WordPress](https://img.shields.io/badge/WordPress-7.1-blue?logo=wordpress)](https://wordpress.org)<br>
[![CI](https://github.com/rtCamp/publishio/actions/workflows/ci.yml/badge.svg)](https://github.com/rtCamp/publishio/actions/workflows/ci.yml)
[![GitHub commits since latest release](https://img.shields.io/github/commits-since/rtCamp/publishio/latest)](https://github.com/rtCamp/publishio/releases)

---

**Publishio is AI publishing that follows your design system.** Connect ChatGPT, Claude, or any Model Context Protocol (MCP) client directly to WordPress, then build pages and posts just by chatting. Landing pages and structured sections are assembled from _your site's own block patterns_; post prose (paragraphs, headings, lists, quotes) uses native WordPress blocks styled by your theme — so the result matches your brand either way.

Publishio is not another AI writer, and it is not a page builder that reinvents your layout. It is a native **MCP server for WordPress** — the bridge between the AI you already use and your site. Because the AI works from the patterns your designers already approved and your theme's own block styles, your layout, spacing, colours, and typography stay exactly as intended. That's the difference between generic AI output and **on-brand AI content that matches your design**.

Everything runs on your own site. There's no third-party service in the middle: your WordPress site exposes its own MCP endpoint, and ChatGPT or Claude connect straight to it over an authenticated connection you control. Editors and content teams can ship landing pages, blog posts, and campaign pages in minutes, in plain language, without touching the designer's work or breaking the design system.

[<img src="https://img.shields.io/badge/WordPress.org-Plugin-21759B?style=for-the-badge&logo=wordpress" alt="WordPress.org" height="40">](https://wordpress.org/plugins/publishio/)
[<img src="https://img.shields.io/badge/GitHub-Download-181717?style=for-the-badge&logo=github" alt="GitHub Download" height="40">](https://github.com/rtCamp/publishio/releases/latest/download/publishio.zip)

## 🎬 Demo

[![Watch the Publishio demo on YouTube](https://img.youtube.com/vi/yUo94HqA9os/maxresdefault.jpg)](https://youtu.be/yUo94HqA9os)

## ✨ Features

- **Follows your design system:** Structured layouts are built from your site's own block patterns, and prose uses native blocks styled by your theme — so content inherits your existing layout, spacing, and brand instead of generic markup.
- **Connect ChatGPT, Claude, or any MCP client:** A standard MCP server for WordPress that works with ChatGPT, Claude, and any tool that speaks the Model Context Protocol.
- **Pattern auto-discovery:** Automatically discovers every pattern registered on your site — from your theme, plugins, and core.
- **Structured content generation:** The AI builds pages with proper, Block Editor-compatible block structures — no messy HTML insertion.
- **In-chat previews:** Preview each section inside the AI chat before anything is published.
- **SEO & metadata:** Automatically adds metadata and SEO details (compatible with Yoast).
- **Runs on your own site:** A self-hosted MCP endpoint with OAuth — no third-party service in the middle.
- **Theme agnostic:** Works with any block-based / FSE theme.<sup>see: [compatible themes](#themes)</sup>

## 🧠 AI Skill

[<img src="https://img.shields.io/badge/Download-Skill-blue?style=for-the-badge&logo=markdown" alt="Download Skill" height="40">](https://raw.githubusercontent.com/rtCamp/publishio/main/skills/publishio/SKILL.md)

This plugin includes a skill file that teaches AI assistants how to generate WordPress content correctly — using your site's existing patterns, building pages incrementally, and following your design system rather than inventing markup.

The skill is served automatically to any connected AI assistant via MCP. Editors and content creators don't need to configure anything — just connect your AI platform and start prompting.

**To use the skill in Claude (claude.ai):**

1. Download the skill file
2. Open Claude Web and go to **Customize → Skills → Upload Skill**
3. Upload the downloaded file
4. Start prompting Claude to build WordPress pages using your theme's patterns

## 📋 Requirements

### WordPress

- **WordPress 6.9 or higher**

### Themes

- Tested this plugin with **Twenty Twenty-Five** and **Ollie**.
- Compatible with any theme. For best results, ensure patterns have descriptive names and descriptions. (see: [Why your theme's patterns need proper names and descriptions](#why-your-themes-patterns-need-proper-names-and-descriptions))

### Why your theme's patterns need proper names and descriptions

This plugin acts as a bridge between your WordPress theme and AI assistants. Here's how it works:

1. **Pattern discovery** — the plugin scans all patterns registered by your active theme via `WP_Block_Patterns_Registry`.
2. **Metadata sent to AI** — each pattern's `title`, `description`, `categories`, and `keywords` are sent to the AI as structured data. The actual block markup is fetched separately, on demand.
3. **AI selects a pattern** — the AI reads the pattern metadata and decides which pattern best fits the user's request.
4. **Content is filled** — the AI hydrates the selected pattern with new content (headings, paragraphs, images, buttons).
5. **Preview and approve** — the generated page is previewed in the AI chat before insertion.

**When a pattern has no description (or a generic one), the AI has only the `title` to work with for matching.** A title like "Hero Section" doesn't tell the AI whether it's a full-width hero, a split hero with an image, a centered hero with a CTA, or a hero with a background video. The AI must then fetch the full content of every pattern that matches a keyword — adding expensive round trips — just to understand what each one looks like.

**What the AI sees:**

- Patterns with rich metadata → the AI matches confidently in one round trip.
- Patterns with no metadata → the AI either skips them or fetches every candidate's full content to reverse-engineer their purpose.

**Bottom line:** The more descriptive your pattern names and descriptions, the better the AI's output. Theme authors should treat pattern metadata as documentation for AI — not just a label for the block inserter.

## 🚀 Quick Start

1. Install and activate the plugin in your WordPress dashboard.
2. Ensure you meet the [Requirements](#-requirements) (WordPress 6.9+, PHP 8.2+).
3. Open the **Publishio** page in your dashboard. It shows your site's MCP server URL and a step-by-step guide for connecting Claude; ChatGPT and other MCP clients connect to the same URL (see the [FAQ](#-faq)).
4. Add the connection in your AI, authorize it, and start prompting it to build pages and posts from your patterns.

## ❓ FAQ

**Q: Will it change my design?**
A: No. Publishio builds pages from your site's own block patterns and your active theme's styles, so new content inherits your existing layout, spacing, colours, and typography. It doesn't restyle your site, edit your theme, or invent CSS — the AI assembles content from the building blocks your designers already approved. You review every section in an in-chat preview before anything is published, so nothing reaches your site without your approval.

**Q: How do I connect ChatGPT to WordPress?**
A: Publishio exposes a standard MCP server on your own site at `https://your-site.com/wp-json/mcp/publishio` (your exact URL is shown on the Publishio dashboard page). In ChatGPT, add that URL as a custom MCP connector and authorize it — Publishio handles authentication with OAuth. A dedicated in-dashboard ChatGPT guide is on the way; today the dashboard ships a step-by-step Claude guide, and ChatGPT connects to the same server URL.

**Q: How do I connect Claude to WordPress?**
A: Open the **Publishio** page in your WordPress dashboard. It includes a complete, step-by-step guide for adding your site's MCP server to Claude and authorizing the connection.

**Q: Does my content and data stay on my site?**
A: Yes — Publishio adds no third-party service. It runs entirely on your own WordPress site as a self-hosted MCP server, and your site connects directly to the AI you choose; nothing is routed through rtCamp or any Publishio-operated cloud. The pages and posts you create live in your own database, exactly like any other WordPress content. The only data that leaves your site is what you send to your chosen AI assistant (ChatGPT or Claude) during a chat — the same as using that assistant anywhere else — governed by that provider's terms.

**Q: Does it support custom post types?**
A: Yes. Publishio works with any registered post type — pages, posts, and custom post types (e.g. portfolio, product, event). Just tell the AI which post type to create.

**Q: Does it work with AI platforms other than ChatGPT and Claude?**
A: Yes. Publishio is a standard MCP server, and MCP is an open protocol. Any AI platform or client that supports MCP — including local models, coding assistants, and custom integrations — can connect to your site and build content.

**Q: Do I need a specific theme?**
A: No, it works with any block-based / FSE theme. Themes with well-described patterns (like Twenty Twenty-Five) yield the best results, because the AI matches patterns from their names and descriptions.

**Q: Does the AI publish directly, or can I review content first?**
A: You're always in control. The plugin shows in-chat previews of each section before anything is inserted. You can review, request changes, or approve before content is published.

**Q: Does it work with classic (non-block) themes?**
A: Publishio is designed for block-based and Full Site Editing (FSE) themes. Classic themes without block editor support are not recommended — the AI generates Block Editor content that requires block-aware themes to render correctly.

**Q: Will it work with page builders like Elementor or Divi?**
A: No. Publishio generates native WordPress block markup for the Block Editor. Page builder shortcodes and proprietary formats are not supported.

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
├── inc/                    # Plugin-specific PHP source
│   └── Modules/
│       ├── MCP/            # Model Context Protocol abilities
│       └── Settings/       # Plugin settings and connections
├── src/                    # TypeScript/JS entry points
├── templates/              # Admin screen and OAuth consent templates
└── tests/                  # PHPUnit and Jest tests
```

See [./docs/DEVELOPMENT.md](docs/DEVELOPMENT.md#directory-structure) for a detailed directory tree and descriptions.

## 👥 Contributors

[@Utsav-Ladani](https://github.com/Utsav-Ladani)
[@danish17](https://github.com/danish17)
[@aviral-mittal](https://github.com/aviral-mittal)
[@HiAbhayKulkarni](https://github.com/HiAbhayKulkarni)
[@justlevine](https://github.com/justlevine)
[@muralig-hub](https://github.com/muralig-hub)
[@dipankardas011](https://github.com/dipankardas011)

## 📄 License

GPL-2.0-or-later. See [LICENSE.md](LICENSE.md).
