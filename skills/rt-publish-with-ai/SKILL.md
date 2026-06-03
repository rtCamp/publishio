---
name: rt-publish-with-ai
description: Generates WordPress content using pattern schemas and incremental assembly. Landing pages are pattern-only; blog posts use direct block markup for plain prose (paragraphs, headings, lists, quotes, inline images) and patterns for structured sections. Use when the user asks to write a blog post, create a landing page, add a section, draft content, build a page, or mentions WordPress content creation. Trigger even if the user doesn't say "pattern" or "schema" — this is how WordPress content is produced on this site.
---

# WordPress Content Generation

## Before You Begin

Verify the WordPress MCP server is connected by attempting `pwai/get-patterns`. If it fails, tell the user the site connection isn't available — don't proceed.

Every interactive question goes through `ask_user_input` as buttons. Never use prose bullet lists for questions with finite answers.

## Tool Reference

### Patterns

| Tool                      | Purpose                                                      |
| ------------------------- | ------------------------------------------------------------ |
| `pwai/get-patterns`       | List all available patterns                                  |
| `pwai/get-pattern-schema` | Get a pattern's schema (attributes, slots, repeatable items) |
| `pwai/render-pattern`     | Render a pattern to block markup from a schema               |
| `pwai/preview-pattern`    | Render a pattern with default content to inspect slot sizes  |

### Posts & Pages

| Tool                      | Purpose                                                                                                                                        |
| ------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| `pwai/create-post`        | Create a draft (pass `post_type: "page"` for pages). Returns `post_id` and `edit_url`                                                          |
| `pwai/get-post`           | Read a post/page — returns `content` (block markup), `blocks` (top-level block list), `status`, `slug`, `excerpt`, `url`, `edit_url`, and more |
| `pwai/update-post`        | Update metadata only: `title`, `slug`, `excerpt`, `parent_id`, `template`. Does **not** accept `content`                                       |
| `pwai/append-blocks`      | Append block markup to a **post** (returns error for pages)                                                                                    |
| `pwai/insert-blocks-at`   | Insert block markup at a position in a **post** (returns error for pages)                                                                      |
| `pwai/delete-block-at`    | Delete a top-level block at a position (works on all post types)                                                                               |
| `pwai/set-featured-image` | Set the featured image by attachment ID                                                                                                        |
| `pwai/search-posts`       | Search existing posts/pages                                                                                                                    |

### Media

| Tool                      | Purpose                             |
| ------------------------- | ----------------------------------- |
| `pwai/search-attachments` | Search the media library by keyword |
| `pwai/upload-media`       | Upload a file to the media library  |

### Taxonomies

| Tool                      | Purpose                                                                |
| ------------------------- | ---------------------------------------------------------------------- |
| `pwai/get-taxonomy-terms` | List terms in a taxonomy (pass `taxonomy: "category"` or `"post_tag"`) |
| `pwai/get-post-terms`     | Get terms currently assigned to a post                                 |
| `pwai/set-post-terms`     | Assign terms to a post (pass array of term slugs or IDs)               |

### SEO

| Tool                  | Purpose                                                                      |
| --------------------- | ---------------------------------------------------------------------------- |
| `pwai/get-yoast-meta` | Read existing Yoast fields. Use as a probe — if it succeeds, Yoast is active |
| `pwai/set-yoast-meta` | Set Yoast SEO fields. Only call if `get-yoast-meta` succeeded                |

---

## Core Mechanics

Three non-negotiable rules. Everything else follows from these.

### 1. Schema-first for patterns

Never hand-write pattern markup. Always: fetch schema → mutate attributes → render markup from schema → preview → append.

**Why:** Hand-written pattern markup drifts from the design system. The schema is the single source of truth for what attributes exist, how slots are sized, and what can change. Bypassing it produces markup that looks right but breaks on theme updates, misses responsive variants, or uses wrong image sizes.

**Exception — blog post prose:** Paragraphs, headings, lists, quotes, and inline images may be written as direct block markup in blog posts. Any structured layout (cards, CTAs, heroes, columns, galleries, testimonials) still goes through the pattern-schema workflow.

**Decision heuristic:** If the section has layout — columns, cards, side-by-side content, overlapping elements, background treatments — it's a pattern. If it's a linear flow of text with occasional inline images, it's prose.

**Landing pages:** No exception. Every block comes from a pattern.

**Repeating items:** You may reduce count by removing entries. You may not add beyond the pattern's design. Need more? Pick a different pattern.

**Custom blocks:** Banned everywhere.

### 2. Incremental assembly by append

Build the page one section at a time. Create an empty draft, then append each section to the end of current content. Never generate the whole document in one shot.

**Why:** One-shot drafts risk invalid markup mid-document that loses all subsequent content. Append-by-section means each insertion is small and verifiable. If a section fails, the draft is valid up to the last successful append — recoverable at every step.

**Mechanism depends on post type:**

| Post type                      | Append mechanism                                                                                                                         |
| ------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------- |
| **Post** (`post_type: "post"`) | `pwai/append-blocks` — one call, appends block markup to the end                                                                         |
| **Page** (`post_type: "page"`) | WordPress REST API `POST /wp/v2/pages/{id}` — read current `content` via `pwai/get-post`, concatenate new markup, send full content back |

**Append hygiene:** When sending full content for pages, pass prior sections through unchanged. Do not regenerate, rewrite, or reformat existing content.

### 3. Preview before append

After filling a pattern schema, always render a preview in the interactive widget and show it to the user. Wait for explicit approval before appending the section to the draft. Never insert without review.

**Why:** Unreviewed inserts put content the user hasn't seen into the draft. If the user wants changes, you've already modified the document and must undo work. Preview-then-approve keeps each insert intentional and user-directed. Incremental assembly guarantees recoverability; preview guarantees correctness.

---

## Disclose Dropped Content

When the user provides reference material (a Google Doc, brief, URL, or any source content), explicitly list any sections, points, or details you are _not_ including in the draft and explain why — pattern slot limits, relevance, length constraints, or redundancy. Get acknowledgement before proceeding.

Do not silently omit content the user provided. They need to know what was considered and why it was cut.

---

## The Pattern-Schema Workflow

The only acceptable way to place a pattern. Applies to posts and landing pages alike.

1. **Fetch the pattern schema** (`pwai/get-pattern-schema`) — the authoritative description of attributes: text fields, image refs, link hrefs, button labels, repeating-item counts.
2. **Render with defaults** (`pwai/preview-pattern`) to see how each slot is sized. This determines what copy fits without clipping or ugly wrap. If a slot is too small for the user's content, pick a different pattern.
3. **Identify changeable attributes.** You change values only: text, image IDs/URLs, link hrefs, button labels, and (where supported) the count of repeating items. Do not touch markup structure, CSS classes, inline styles, spacing, or colors. If the user wants something not in the schema, say so and propose a different pattern.
4. **Mutate the schema** — edit the structured data, not rendered markup.
5. **Render the filled pattern** (`pwai/render-pattern`) as a preview in the interactive widget. Do not append anything yet.
6. **Wait for explicit approval.** If the user requests changes, revise the schema and re-preview. Do not insert the old version. Only append after the user approves.

---

## Blog Posts

Prose body (paragraphs, headings, lists, quotes, inline images) uses direct block markup. Any structured section uses the full pattern-schema workflow.

1. **Ask interactively** via `ask_user_input`: topic/title (typed), audience (buttons), tone (buttons: Professional / Conversational / Educational), length (buttons: Short / Medium / Long), featured image needed (Yes / No).
2. **Discover:** Call `pwai/get-taxonomy-terms` for categories and tags. Call `pwai/get-patterns` to fetch all available patterns.
3. **Resolve media** before drafting content — search the library, ask for uploads if missing.
4. **Share a plan** in chat: title, hook, section-by-section structure. Mark each section as prose or pattern: \<name\>. If reference material was provided, disclose any dropped content with reasons. Get approval.
5. **Draft section content** in chat (prose, headings, excerpt, proposed categories/tags, SEO fields). For pattern sections, render the pattern with defaults first and size content to its slots. Get approval.
6. **Create an empty draft** via `pwai/create-post`.
7. **For each section in order:**
   - Prose section: write block markup directly, append via `pwai/append-blocks`
   - Pattern section: fetch schema → render with defaults to confirm fit → mutate → render final → **preview in widget, wait for approval** → append via `pwai/append-blocks`
8. **Assign categories and tags** using `pwai/set-post-terms` (taxonomy `"category"` then `"post_tag"`).
9. **SEO (mandatory):**
   - Set slug and excerpt via `pwai/update-post` (slug: short, keyword-rich; excerpt: 1–2 sentence meta description, max 155 chars).
   - Probe Yoast: call `pwai/get-yoast-meta`. If it succeeds, call `pwai/set-yoast-meta` with `seo_title`, `meta_description` (max 155 chars), `focus_keyphrase`, `og_title`, `og_description`, `twitter_title`, `twitter_description`. If the probe fails, skip silently.
10. **Share the post link** (from `pwai/get-post` → `url`). Do not ask to publish or change the visibility status. If user asks you to publish, tell the user you cannot do that and the publish can only be done via WordPress.

---

## Landing Pages

Pattern-only. No exception for "just a paragraph of text" — find a text-content pattern, or surface that none fits.

1. **Ask interactively** via `ask_user_input`: purpose (typed or buttons), audience (buttons), primary CTA label and URL (typed), sections needed (multi-select of common types), reference pages (typed URL or "None").
2. **Discover:** Call `pwai/get-patterns` to fetch all available patterns.
3. **Render every candidate pattern** with defaults (`pwai/preview-pattern`) to inspect slot sizes (headline length, body length, image aspect, item count). This determines what content fits before you write a word.
4. **Propose a section plan:** pattern name per section, attributes to change, content sized to fit. If reference material was provided, disclose any dropped content with reasons. Get explicit approval.
5. **Resolve media** — list every image/video by section, search the library via `pwai/search-attachments`, ask for uploads. Don't proceed until done.
6. **Draft section content** in chat, already trimmed to fit pattern slots. Get approval.
7. **Create an empty page draft** via `pwai/create-post` with `post_type: "page"`.
8. **For each section in order:** fetch schema → render to confirm fit → mutate → render final → **preview in widget, wait for approval** → append. Append by reading current content via `pwai/get-post`, concatenating the new markup, and writing back via the WordPress REST API (`POST /wp/v2/pages/{id}` with the full `content` field).
9. **SEO (mandatory):**
   - Set slug and excerpt via `pwai/update-post` (slug: short, keyword-rich; excerpt: 1–2 sentence meta description, max 155 chars).
   - Probe Yoast: call `pwai/get-yoast-meta`. If it succeeds, call `pwai/set-yoast-meta` with `seo_title`, `meta_description` (max 155 chars), `focus_keyphrase`, `og_title`, `og_description`, `twitter_title`, `twitter_description`, and `schema_page_type`. If the probe fails, skip silently.
10. **Share the page link** (from `pwai/get-post` → `url`).

---

## Media

Search the library first via `pwai/search-attachments`. Show options as buttons if multiple match. If nothing fits, ask interactively: provide found options plus an "I'll provide a URL" option. Never ask the user to type a filename or describe an image when a button will do.

Never source media independently — only from the library, user-provided URLs, or `pwai/upload-media`.

Media IDs/URLs go into pattern schemas, never raw `<img>` tags.

---

## SEO & Taxonomies

- **Categories & Tags** — Always call `pwai/get-taxonomy-terms` first to discover real existing terms before proposing or assigning any. Use `pwai/set-post-terms` to assign (taxonomy `"category"` or `"post_tag"`). Create new terms only with explicit user confirmation; if confirmed, create via the WordPress REST API and then assign with `pwai/set-post-terms`.
- **SEO is mandatory on every post and page.** Always set slug and excerpt via `pwai/update-post` (works regardless of plugins). Always probe Yoast with `pwai/get-yoast-meta` — if it succeeds, set all fields with `pwai/set-yoast-meta`. If the probe fails, skip silently. Never skip slug/excerpt just because Yoast isn't active.
- **Post Types** — Check registered post types first; use the relevant custom post type if one exists.
- **Status** — Default is draft. You cannot change that even if the user asks..

---

## Common Mistakes

These are failure modes seen in practice. They are not a restatement of the rules above — they're specific anti-patterns to catch before they happen.

- **Asking questions as bullet lists.** Every question with a finite set of answers goes through `ask_user_input` as buttons. Prose question lists force the user to type when they could tap.
- **Tweaking pattern markup by hand.** If it's not in the schema, it's not your change. Pick a different pattern.
- **Landing page prose inlined as raw markup.** Pattern-only for pages. Find the text-content pattern or surface that none fits.
- **Writing content before checking pattern slot sizes.** Render the pattern with defaults first, then size content to fit. Content written blind will overflow or clip.
- **Adding items to a repeating pattern beyond its design limit.** Reduce only, never extend. Need more items? Pick a different pattern.
- **Using a custom block because it "would be perfect here."** Banned everywhere. Patterns only (plus prose markup in posts).
- **Appending a section without showing the user a preview first.** Always render the filled pattern, show it, and wait for approval before appending. Unreviewed inserts create rework.
- **Silently dropping content the user provided.** When reference material is given, list what's being cut and why. Get acknowledgement. The user needs to know what was considered.
- **Skipping SEO because Yoast isn't installed.** Slug and excerpt via `pwai/update-post` work regardless. Always set them. Probe Yoast, skip if inactive — but never skip slug/excerpt.
- **Assigning categories/tags from memory.** Always call `pwai/get-taxonomy-terms` first. Made-up terms don't match the site's actual taxonomy.
- **Surfacing tool errors to the user.** Handle failures, retries, and unexpected results silently. Only communicate outcomes that affect the user's content. If a tool call fails and there's a fallback, use the fallback without narration.
- **Generating the whole page in one shot.** Append section by section. One-shot drafts produce invalid markup and lose work mid-generation.
