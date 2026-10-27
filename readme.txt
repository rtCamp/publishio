=== Publishio – Build Pages & Posts with ChatGPT and Claude using MCP ===
Contributors:      rtCamp, utsavladani, iamdanih17, aviral89, hiabhaykulkarni, justlevine, jayson31, muralig, dipankardas011
Tags:              chatgpt, claude, ai, mcp, ai content
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html
Requires PHP:      8.2
Requires at least: 6.9
Tested up to:      7.1
Stable tag:        1.2.0

AI publishing that follows your design system. Connect ChatGPT or Claude to WordPress and build pages and posts that match your patterns and theme.

== Description ==

**Publishio is AI publishing that follows your design system.** Connect ChatGPT, Claude, or any Model Context Protocol (MCP) client directly to WordPress, then build pages and posts just by chatting. Landing pages and structured sections are assembled from *your site's own block patterns*; post prose (paragraphs, headings, lists, quotes) uses native WordPress blocks styled by your theme — so the result matches your brand either way.

Publishio is not another AI writer, and it is not a page builder that reinvents your layout. It is a native **MCP server for WordPress** — the bridge between the AI you already use and your site. Because the AI works from the patterns your designers already approved and your theme's own block styles, your layout, spacing, colours, and typography stay exactly as intended. That's the difference between generic AI output and **on-brand AI content that matches your design**.

Everything runs on your own site. There's no third-party service in the middle: your WordPress site exposes its own MCP endpoint, and ChatGPT or Claude connect straight to it over an authenticated connection you control. Editors and content teams can ship landing pages, blog posts, and campaign pages in minutes, in plain language, without touching the designer's work or breaking the design system.

**Watch the demo:**

https://youtu.be/yUo94HqA9os

= Features =

* **Follows your design system:** Structured layouts are built from your site's own block patterns, and prose uses native blocks styled by your theme — so content inherits your existing layout, spacing, and brand instead of generic markup.
* **Connect ChatGPT, Claude, and other MCP clients:** A standard MCP server for WordPress that works with ChatGPT, Claude, Claude Code, and other MCP clients that support Client ID Metadata Documents (CIMD).
* **Pattern auto-discovery:** Automatically discovers every pattern registered on your site — from your theme, plugins, and core.
* **Structured content generation:** The AI builds pages with proper, Block Editor-compatible block structures — no messy HTML insertion.
* **In-chat approval:** Approve each section in the AI chat before it's added to the draft — page sections in a visual preview, blog post sections as text.
* **SEO & metadata:** Automatically adds metadata and SEO details (compatible with Yoast).
* **Runs on your own site:** A self-hosted MCP endpoint with OAuth — no third-party service in the middle.
* **Theme agnostic:** Works with any block-based / FSE theme.

= AI Skill =

This plugin includes a skill file that teaches AI assistants how to generate WordPress content correctly — using your site's existing patterns, building pages incrementally, and following your design system rather than inventing markup.

The skill is served automatically to any connected AI assistant via MCP. Editors and content creators don't need to configure anything — just connect your AI platform and start prompting.

To use the skill in Claude (claude.ai):

1. <a href="https://raw.githubusercontent.com/rtCamp/publishio/main/skills/publishio/SKILL.md">Download the skill file</a>
2. Open Claude and go to Customize → Skills → Upload Skill
3. Upload the downloaded file
4. Start prompting Claude to build WordPress pages using your theme's patterns

== Requirements ==

= WordPress =

* **WordPress 6.9 or higher**
* **Pretty permalinks** (any Permalinks setting except Plain). AI apps can't connect with Plain permalinks.
* **AI apps must be able to reach your site.** Addresses under `/.well-known/` and `/wp-json/` must reach WordPress for visitors who aren't logged in. Some web servers and firewalls block `/.well-known/`; **Publishio → Status** checks this for you.

= Themes =

* Tested this plugin with **Twenty Twenty-Five** and **Ollie**.
* Compatible with any theme. For best results, ensure patterns have descriptive names and descriptions.

= Why your theme's patterns need proper names and descriptions =

This plugin acts as a bridge between your WordPress theme and AI assistants. Here's how it works:

1. **Pattern discovery** — the plugin scans all patterns registered by your active theme via `WP_Block_Patterns_Registry`.
2. **Metadata sent to AI** — each pattern's `title`, `description`, `categories`, and `keywords` are sent to the AI as structured data. The actual block markup is fetched separately, on demand.
3. **AI selects a pattern** — the AI reads the pattern metadata and decides which pattern best fits the user's request.
4. **Content is filled** — the AI hydrates the selected pattern with new content (headings, paragraphs, images, buttons).
5. **Preview and approve** — each section is shown in the AI chat for your approval before it's added: on pages in a visual preview, in blog posts as text.

**When a pattern has no description (or a generic one), the AI has only the `title` to work with for matching.** A title like "Hero Section" doesn't tell the AI whether it's a full-width hero, a split hero with an image, a centered hero with a CTA, or a hero with a background video. The AI must then fetch the full content of every pattern that matches a keyword — adding expensive round trips — just to understand what each one looks like.

**What the AI sees:**

* Patterns with rich metadata → the AI matches confidently in one round trip.
* Patterns with no metadata → the AI either skips them or fetches every candidate's full content to reverse-engineer their purpose.

**Bottom line:** The more descriptive your pattern names and descriptions, the better the AI's output. Theme authors should treat pattern metadata as documentation for AI — not just a label for the block inserter.

== Installation ==

1. Install and activate the plugin in your WordPress dashboard.
2. Ensure you meet the requirements (WordPress 6.9+, PHP 8.2+, pretty permalinks).
3. Open the **Publishio** page in your dashboard. It shows your site's MCP server URL and step-by-step guides for connecting Claude and ChatGPT; other MCP clients connect to the same URL (see the FAQ).
4. Add the connection in your AI, authorize it, and start prompting it to build pages and posts from your patterns.

== Frequently Asked Questions ==

= Will it change my design? =
No. Publishio builds pages from your site's own block patterns and your active theme's styles, so new content inherits your existing layout, spacing, colours, and typography. It doesn't restyle your site, edit your theme, or invent CSS — the AI assembles content from the building blocks your designers already approved. You approve every section in the chat before it's added to the draft, and nothing goes live until you publish it in WordPress.

= How do I connect ChatGPT to WordPress? =
Publishio exposes a standard MCP server on your own site at `https://your-site.com/wp-json/mcp/publishio` (your exact URL is shown on the Publishio dashboard page). In ChatGPT, add that URL as a custom MCP connector and authorize it — Publishio handles authentication with OAuth. The Publishio dashboard includes a step-by-step ChatGPT setup guide alongside the Claude guide.

= How do I connect Claude to WordPress? =
Open the **Publishio** page in your WordPress dashboard. It includes a complete, step-by-step guide for adding your site's MCP server to Claude and authorizing the connection.

= An AI app says it couldn't connect. What should I check? =
Open **Publishio → Status** in your dashboard. It checks your site the way an AI app does and names the step that fails, such as a web server blocking `/.well-known/` or a security plugin blocking the REST API, with how to fix it. Copy the report it gives you and send it to your developer, your host or rtCamp, or paste it into Claude or ChatGPT and ask it to walk you through the fix.

= Does my content and data stay on my site? =
Yes — Publishio adds no third-party service. It runs entirely on your own WordPress site as a self-hosted MCP server, and your site connects directly to the AI you choose; nothing is routed through rtCamp or any Publishio-operated cloud. The pages and posts you create live in your own database, exactly like any other WordPress content. The only data that leaves your site is what you send to your chosen AI assistant (ChatGPT or Claude) during a chat — the same as using that assistant anywhere else — governed by that provider's terms.

= Does it support custom post types? =
Yes. Publishio works with any registered post type — pages, posts, and custom post types (e.g. portfolio, product, event). Just tell the AI which post type to create.

= Does it work with AI platforms other than ChatGPT and Claude? =
Yes, if the app supports Client ID Metadata Documents (CIMD), the OAuth sign-in method the MCP specification recommends. Claude (web, desktop, and Claude Code) and ChatGPT support it. Cursor, Gemini CLI, and Windsurf only support the older dynamic client registration, so they can't connect until they add CIMD support.

= Do I need a specific theme? =
No, it works with any block-based / FSE theme. Themes with well-described patterns (like Twenty Twenty-Five) yield the best results, because the AI matches patterns from their names and descriptions.

= Does the AI publish directly, or can I review content first? =
You're always in control. The AI shows you each section in the chat before adding it: page sections in a visual preview with Insert and Show alternative buttons, blog post sections as text. You can approve, request changes, or ask for a different pattern. The AI can't publish: everything stays a draft until you publish it in WordPress. Building pages needs an AI app that can show the visual preview; in an app that can't, the AI tells you.

= Does it work with classic (non-block) themes? =
Publishio is designed for block-based and Full Site Editing (FSE) themes. Classic themes without block editor support are not recommended — the AI generates Block Editor content that requires block-aware themes to render correctly.

= Will it work with page builders like Elementor or Divi? =
No. Publishio generates native WordPress block markup for the Block Editor. Page builder shortcodes and proprietary formats are not supported.

== Documentation ==

* **Connection Guide** — Open the Publishio page in your WordPress dashboard for the Claude and ChatGPT setup guides.
* <a href="https://github.com/rtCamp/publishio/blob/main/docs/DEVELOPMENT.md">Development Guide</a> — Setup, commands, testing, and contribution.
* <a href="https://github.com/rtCamp/publishio/blob/main/docs/CONTRIBUTING.md">Contributing</a>
* <a href="https://github.com/rtCamp/publishio/blob/main/docs/CODE_OF_CONDUCT.md">Code of Conduct</a>
* <a href="https://github.com/rtCamp/publishio/blob/main/docs/SECURITY.md">Security</a>

== Source Code ==

The source code is available on <a href="https://github.com/rtCamp/publishio">GitHub</a>.

== Screenshots ==

1. The chat prompt in ChatGPT or Claude, where you describe the page or post you want in plain language.
2. The AI's plan in the chat — a section-by-section outline of the page, with each section mapped to one of your site's own block patterns.
3. The in-chat preview of a generated page section, shown for review with insert and show-alternative controls before anything is added to the draft.
4. The finished page on your site, assembled from your patterns and styled by your active theme.
5. The Publishio Guide page, with your MCP server URL, a readiness check, and setup guides for Claude and ChatGPT.
6. The Claude AI setup guide showing how to add a custom MCP connector in Claude's Customize > Connectors panel.
7. The authorization screen, where you approve an AI app's access to your site.
8. The Connections page listing all AI apps that have authenticated with your site, with user, connection date, and last-active details.
9. The Status page, checking whether AI apps can connect to your site.

== Changelog ==

= 1.2.0 =
* Add a Status page (Publishio → Status) that checks whether AI apps can reach your site and tells you what to fix.
* Let Claude Code connect to live sites, not just development sites.
* Let AI apps see and change only the Yoast SEO fields you see in the editor.
* Replace dynamic client registration (DCR) with Client ID Metadata Documents (CIMD). Claude, Claude Code and ChatGPT support it. Apps that only support DCR, such as Cursor, can't connect until they add CIMD.
* Fix the Connections page, which was blank on WordPress 6.9.
* Fix pattern images and covers that showed "Attempt Block Recovery" in the editor.
* Remove the upload-media tool, which let AI apps make your site download any web address. Upload images to the media library instead.
* Disconnect AI apps when a user changes their password or clicks "Log Out Everywhere Else" on their profile, and ask for approval again 90 days after an app was connected.

= 1.1.0 =
* Add an in-dashboard ChatGPT setup guide for connecting OpenAI to your site.
* Update bundled dependencies and fix reported npm vulnerabilities.

= 1.0.0 =
* Declare the plugin stable with the 1.0.0 release.
* Test the plugin with WordPress 7.1.

= 0.4.1 =
* Fix readme.txt

= 0.4.0 =
* Release plugin on WordPress.org.

= 0.3.1 =
* Update plugin name to "Publishio".

= 0.3.0 =
* Add support for OAuth DCR flow for seamless AI platform integration.
* Add SEO capabilities by integrating with Yoast SEO metadata.
* Refactor codebase for improved maintainability and performance.

= 0.2.0 =
* Initial public release.
* Pattern auto-discovery via WP_Block_Patterns_Registry.
* In-chat previews before content is inserted.
* Claude connection with MCP and OAuth authentication.
* Yoast SEO metadata support.
* AI Skill file for guided content generation.

== Upgrade Notice ==

= 1.2.0 =
* MCP specification and security update. AI apps now connect with CIMD instead of DCR, as the latest MCP specification recommends, so approve each app, such as ChatGPT or Claude, again after updating. Apps without CIMD support, such as Cursor, can't connect yet.

= 1.1.0 =
* Adds an in-dashboard ChatGPT setup guide. No upgrade steps required.

= 1.0.0 =
* First stable release. No upgrade steps required.

= 0.4.0 =
* Release plugin on WordPress.org.

= 0.2.0 =
Initial public release. No upgrade steps required.
