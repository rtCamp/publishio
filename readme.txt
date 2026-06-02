=== Publish With AI ===
Contributors:      Utsav-Ladani, danishshakeel
Tags:              Publish, AI, Editorial Workflow, Abilities, MCP, blocks, patterns
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html
Requires PHP:      8.2
Requires at least: 6.9
Tested up to:      7.0
Stable tag:        0.2.0

Build WordPress pages and posts using your existing patterns directly from your favorite AI assistant. This plugin auto-discovers your site's patterns, allowing AI platforms like Claude and ChatGPT to build structured content using the Block Editor.

== Description ==

Build WordPress pages and posts using your existing patterns directly from your favorite AI assistant. This plugin auto-discovers your site's patterns, allowing AI platforms like Claude and ChatGPT to build structured content without manual markup insertion.

= Features =

* **Pattern Auto-Discovery:** Automatically discovers all patterns registered on your site — from your theme, plugins, and core.
* **Structured Content Generation:** AI builds pages using proper block structures, fully Block Editor-compatible content, avoiding messy HTML insertion.
* **In-Chat Previews:** Shows live previews of each section directly within the AI chat interface before AI publishes them.
* **Platform Agnostic:** Works with major AI platforms like Claude and ChatGPT via Model Context Protocol (MCP).
* **SEO & Metadata:** Automatically adds metadata and SEO details (compatible with Yoast).
* **Theme Agnostic:** Works with any Block-based/FSE theme.

= AI Skill =

This plugin includes a skill file that teaches AI assistants how to generate WordPress content correctly — using your site's existing patterns, building pages incrementally, and following your design system rather than inventing markup.

The skill is served automatically to any connected AI assistant via MCP. Editors and content creators don't need to configure anything — just connect your AI platform and start prompting.

To use the skill in Claude (claude.ai):

1. <a href="https://raw.githubusercontent.com/rtCamp/publishwithai/main/skills/rt-publish-with-ai/SKILL.md">Download the skill file</a>
2. Open Claude and go to Customize → Skills → Upload Skill
3. Upload the downloaded file
4. Start prompting Claude to build WordPress pages using your theme's patterns

== Requirements ==

= WordPress =

* **WordPress 6.9 or higher**

= Themes =

* Tested this plugin with **Twenty Twenty-Five** and **Ollie**.
* Compatible with any theme. For best results, ensure patterns have descriptive names and descriptions.

= Why your theme's patterns need proper names and descriptions =

This plugin acts as a bridge between your WordPress theme and AI assistants. Here's how it works:

1. **Pattern discovery** — the plugin scans all patterns registered by your active theme via `WP_Block_Patterns_Registry`.
2. **Metadata sent to AI** — each pattern's `title`, `description`, `categories`, and `keywords` are sent to the AI as structured data. The actual block markup is fetched separately, on demand.
3. **AI selects a pattern** — the AI reads the pattern metadata and decides which pattern best fits the user's request.
4. **Content is filled** — the AI hydrates the selected pattern with new content (headings, paragraphs, images, buttons).
5. **Preview and approve** — the generated page is previewed in the AI chat before insertion.

**When a pattern has no description (or a generic one), the AI has only the `title` to work with for matching.** A title like "Hero Section" doesn't tell the AI whether it's a full-width hero, a split hero with an image, a centered hero with a CTA, or a hero with a background video. The AI must then fetch the full content of every pattern that matches a keyword — adding expensive round trips — just to understand what each one looks like.

**What the AI sees:**

* Patterns with rich metadata → the AI matches confidently in one round trip.
* Patterns with no metadata → the AI either skips them or fetches every candidate's full content to reverse-engineer their purpose.

**Bottom line:** The more descriptive your pattern names and descriptions, the better the AI's output. Theme authors should treat pattern metadata as documentation for AI — not just a label for the block inserter.

== Installation ==

1. Install and activate the plugin in your WordPress dashboard.
2. Ensure you meet the requirements (WordPress 6.9+).
3. Navigate to the plugin settings to connect your AI platform (Claude).
4. Start prompting your AI to build WordPress pages using your theme's patterns.

== Frequently Asked Questions ==

= Do I need a specific theme? =
No, it works with any theme, but themes with well-described patterns (like Twenty Twenty-Five) yield the best results.

= Does it work with AI platforms other than Claude and ChatGPT? =
Yes. The plugin uses the Model Context Protocol (MCP), an open standard. Any AI platform or client that supports MCP — including local models, coding assistants, and custom integrations — can connect to your site and build content.

= Does the AI publish directly, or can I review content first? =
You're always in control. The plugin provides in-chat previews of each section before anything is inserted. You can review, request changes, or approve before the content is published.

= Does it work with classic (non-block) themes? =
The plugin is designed for block-based and Full Site Editing (FSE) themes. Classic themes without block editor support are not recommended — the AI generates Block Editor content that requires block-aware themes to render correctly.

= Will it work with page builders like Elementor or Divi? =
No. This plugin generates native WordPress block markup for the Block Editor. Page builder shortcodes and proprietary formats are not supported.

= Does it support custom post types? =
Yes. The plugin works with any registered post type — pages, posts, and custom post types (e.g. portfolio, product, event). Just pass the post type slug when creating content.

== Documentation ==

* **Development Guide** — Setup, commands, testing, and contribution.
* **Contributing**
* **Code of Conduct**
* **Security**

== Project Structure ==

```
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

== Contributors ==

[@Utsav-Ladani](https://github.com/Utsav-Ladani)
[@danishshakeel](https://github.com/danishshakeel)

== License ==

GPL-2.0-or-later. See [LICENSE.md](LICENSE.md).

== Screenshots ==

== Changelog ==

For the full changelog see <a href="https://github.com/rtCamp/publishwithai/blob/main/CHANGELOG.md" target="_blank">GitHub</a>.

== Upgrade Notice ==
