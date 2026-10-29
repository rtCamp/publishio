---
name: publishio
description: Generates WordPress content using pattern schemas and incremental assembly. Landing pages are pattern-only; blog posts use direct block markup for plain prose (paragraphs, headings, lists, quotes, inline images) and patterns for structured sections. Use when the user asks to write a blog post, create a landing page, add a section, draft content, build a page, or mentions WordPress content creation. Trigger even if the user doesn't say "pattern" or "schema" — this is how WordPress content is produced on this site.
---

# WordPress Content Generation

## Before You Begin

Verify the WordPress MCP server is connected by attempting `publishio-get-patterns`. If it fails, tell the user the site connection isn't available — don't proceed.

A connection can be read-only. If a tool that changes the site returns "This connection has read-only access, so it cannot make changes to the site.", stop and tell the user: this connection can read the site but can't create or edit content, so they need to connect the app again and approve access to create and edit content. Don't retry.

Every interactive question goes through `ask_user_input` as buttons. Never use prose bullet lists for questions with finite answers.

## Tool Reference

### Patterns

| Tool                             | Purpose                                                                                                                |
| -------------------------------- | ---------------------------------------------------------------------------------------------------------------------- |
| `publishio-get-patterns`         | List all available patterns with their title, description, categories and keywords                                     |
| `publishio-get-pattern-schema`   | Get a pattern's schema: an ordered list of its fillable blocks and their fields, holding the pattern's default content |
| `publishio-apply-pattern-schema` | Fill a pattern from a schema and return its block markup. With `render: true` it also returns the rendered HTML        |
| `publishio-preview-pattern`      | Show a filled pattern to the user on the approval screen, for a **page**. The user's Insert click adds it to the page  |

`publishio-render-pattern` and `publishio-insert-pattern-confirmed` belong to the approval screen, which calls them itself. Never call them.

### Posts & Pages

| Tool                           | Purpose                                                                                                                                        |
| ------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| `publishio-create-post`        | Create a draft (pass `post_type: "page"` for pages). Returns `post_id` and `edit_url`                                                          |
| `publishio-get-post`           | Read a post/page — returns `content` (block markup), `blocks` (top-level block list), `status`, `slug`, `excerpt`, `url`, `edit_url`, and more |
| `publishio-update-post`        | Update metadata only: `title`, `slug`, `excerpt`, `parent_id`, `template`. Does **not** accept `content`                                       |
| `publishio-insert-blocks`      | Insert block markup at a path in any post type, before or after a block or at the start or end inside it                                       |
| `publishio-delete-blocks`      | Delete blocks by path (works on all post types)                                                                                                |
| `publishio-render-block`       | Render block markup to HTML. `is_valid` is false when no block parses or a block type isn't registered                                         |
| `publishio-set-featured-image` | Set the featured image by attachment ID                                                                                                        |
| `publishio-search-posts`       | Search existing posts/pages                                                                                                                    |

### Media

| Tool                           | Purpose                             |
| ------------------------------ | ----------------------------------- |
| `publishio-search-attachments` | Search the media library by keyword |

### Taxonomies

| Tool                           | Purpose                                                                                                                             |
| ------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------- |
| `publishio-get-taxonomy-terms` | List terms in a taxonomy (pass `taxonomy: "category"` or `"post_tag"`)                                                              |
| `publishio-get-post-terms`     | Get terms currently assigned to a post                                                                                              |
| `publishio-set-post-terms`     | Assign terms to a post (pass array of term slugs or IDs). Replaces the post's terms in that taxonomy and refuses unknown term slugs |

### SEO

| Tool                       | Purpose                                                                                             |
| -------------------------- | --------------------------------------------------------------------------------------------------- |
| `publishio-get-yoast-meta` | Read the Yoast fields you can edit on a post. Use as a probe — if it succeeds, Yoast is active      |
| `publishio-set-yoast-meta` | Set Yoast SEO fields. Only call if `get-yoast-meta` succeeded, and only with the fields it returned |

---

## Core Mechanics

Three non-negotiable rules. Everything else follows from these.

### 1. Schema-first for patterns

Never hand-write pattern markup. Always: fetch schema → fill values → get approval → insert. Pages insert through the approval screen (`publishio-preview-pattern`); posts append the markup that `publishio-apply-pattern-schema` returns.

**Why:** Hand-written pattern markup drifts from the design system. The schema is the single source of truth for what can change. Bypassing it produces markup that looks right but breaks on theme updates, misses responsive variants, or uses wrong image sizes.

**Exception — blog post prose:** Paragraphs, headings, lists, quotes, and inline images may be written as direct block markup in blog posts. Any structured layout (cards, CTAs, heroes, columns, galleries, testimonials) still goes through the pattern-schema workflow.

**Decision heuristic:** If the section has layout — columns, cards, side-by-side content, overlapping elements, background treatments — it's a pattern. If it's a linear flow of text with occasional inline images, it's prose.

**Landing pages:** No exception. Every block comes from a pattern.

**Repeating items:** The pattern fixes the number of items. Fill every entry: leaving one out doesn't remove its item, which keeps the pattern's placeholder content. Need fewer or more items? Pick a different pattern.

**Custom blocks:** Banned everywhere.

### 2. Incremental assembly by append

Build the page one section at a time. Create an empty draft, then add each section to the end of current content. Never generate the whole document in one shot.

**Why:** One-shot drafts risk invalid markup mid-document that loses all subsequent content. Append-by-section means each insertion is small and verifiable. If a section fails, the draft is valid up to the last successful append — recoverable at every step.

**Mechanism depends on post type:**

| Post type                      | Append mechanism                                                                                                                                                   |
| ------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **Post** (`post_type: "post"`) | `publishio-insert-blocks` — one call, appends block markup to the end: prose you write, or the `markup` from `publishio-apply-pattern-schema`                      |
| **Page** (`post_type: "page"`) | `publishio-preview-pattern` with `position: -1` — the section is appended when the user clicks Insert on the approval screen. No other tool adds content to a page |

### 3. Approval before insert

Every pattern section is approved by the user before it goes into the draft. Never insert without review.

- **Pages:** `publishio-preview-pattern` opens the approval screen in the chat. Stop there and wait. Insert adds the section to the page and sends you "…has been successfully inserted… Please continue." Don't add it again. "Show me an alternative…" means pick a different pattern and preview again.
- **Posts:** the approval screen can't insert into posts, so don't use it there. Show the filled section's content in chat (headings, text, button labels and links, images) and wait for explicit approval before appending.

**Why:** Unreviewed inserts put content the user hasn't seen into the draft. If the user wants changes, you've already modified the document and must undo work. Approve-then-insert keeps each insert intentional and user-directed. Incremental assembly guarantees recoverability; approval guarantees correctness.

---

## Disclose Dropped Content

When the user provides reference material (a Google Doc, brief, URL, or any source content), explicitly list any sections, points, or details you are _not_ including in the draft and explain why — pattern slot limits, relevance, length constraints, or redundancy. Get acknowledgement before proceeding.

Do not silently omit content the user provided. They need to know what was considered and why it was cut.

---

## The Pattern-Schema Workflow

The only acceptable way to place a pattern. Applies to posts and landing pages alike.

1. **Fetch the pattern schema** (`publishio-get-pattern-schema`) — the authoritative description of what can change: an ordered list of entries, each a `block` and its `fields`. Paragraphs, headings and list items have `content`; buttons `text` and `url`; images and covers `url`, `alt` and `id`; videos `src`; social links `url` and `service`.
2. **Size content to the defaults.** The field values are the pattern's own default content: their length shows how much copy each slot holds without clipping or ugly wrap, and the entries show how many items the pattern has. To see the layout too, call `publishio-apply-pattern-schema` with an empty `schema` and `render: true`. If a slot is too small for the user's content, pick a different pattern.
3. **Identify changeable fields.** You change values only: text, image URLs, IDs and alt text, link URLs and button labels. Do not touch markup structure, CSS classes, inline styles, spacing, or colors. If the user wants something not in the schema, say so and propose a different pattern.
4. **Fill the schema** — edit the field values, not rendered markup. Keep every entry, its `block` and the order. A left-out entry doesn't remove its block: the block keeps the pattern's placeholder content, and later entries of the same block type can shift onto the wrong item.
5. **Get approval and insert:**
   - **Page:** call `publishio-preview-pattern` with `page_id`, `position: -1` (append), `pattern_name` and the filled `schema`. Stop and wait for the user. When they click Insert, the approval screen adds the section and tells you to continue. If the user says no approval screen appeared, their app can't show it, and no other tool adds content to a page: tell them the page can't be built in this app.
   - **Post:** call `publishio-apply-pattern-schema` with the filled schema, show the section's content in chat and wait for approval, then append the returned `markup` unchanged with `publishio-insert-blocks`.
6. **Changes requested:** revise the schema and repeat step 5. Never insert the old version.

---

## Blog Posts

Prose body (paragraphs, headings, lists, quotes, inline images) uses direct block markup. Any structured section uses the full pattern-schema workflow.

1. **Ask interactively** via `ask_user_input`: topic/title (typed), audience (buttons), tone (buttons: Professional / Conversational / Educational), length (buttons: Short / Medium / Long), featured image needed (Yes / No).
2. **Discover:** Call `publishio-get-taxonomy-terms` for categories and tags. Call `publishio-get-patterns` to fetch all available patterns.
3. **Resolve media** before drafting content, including the featured image if one is needed — search the library via `publishio-search-attachments`. If an image is missing, ask the user to upload it in WordPress (Media → Add New), then search again.
4. **Share a plan** in chat: title, hook, section-by-section structure. Mark each section as prose or pattern: \<name\>. If reference material was provided, disclose any dropped content with reasons. Get approval.
5. **Draft section content** in chat (prose, headings, excerpt, proposed categories/tags, SEO fields). For pattern sections, fetch the schema first and size content to its default content. Get approval.
6. **Create an empty draft** via `publishio-create-post`.
7. **For each section in order:**
   - Prose section: write block markup directly, check it with `publishio-render-block`, append via `publishio-insert-blocks`
   - Pattern section: fetch schema → fill → `publishio-apply-pattern-schema` → **show the content in chat, wait for approval** → append the returned `markup` via `publishio-insert-blocks`
8. **Set the featured image** if the user wanted one: `publishio-set-featured-image` with the attachment ID found in step 3.
9. **Assign categories and tags** using `publishio-set-post-terms` (taxonomy `"category"` then `"post_tag"`).
10. **SEO (mandatory):**
    - Set slug and excerpt via `publishio-update-post` (slug: short, keyword-rich; excerpt: 1–2 sentence meta description, max 155 chars).
    - Probe Yoast: call `publishio-get-yoast-meta`. If it succeeds, call `publishio-set-yoast-meta` with the fields it returned, out of `seo_title`, `meta_description` (max 155 chars), `focus_keyphrase`, `og_title`, `og_description`, `twitter_title` and `twitter_description`. Leave out fields it didn't return: they aren't available to this user, and including one fails the whole call. If the probe fails, skip silently.
11. **Share the post link** (from `publishio-get-post` → `url`). Do not ask to publish or change the visibility status. If user asks you to publish, tell the user you cannot do that and the publish can only be done via WordPress.

---

## Landing Pages

Pattern-only. No exception for "just a paragraph of text" — find a text-content pattern, or surface that none fits.

1. **Ask interactively** via `ask_user_input`: purpose (typed or buttons), audience (buttons), primary CTA label and URL (typed), sections needed (multi-select of common types), reference pages (typed URL or "None").
2. **Discover:** Call `publishio-get-patterns` to fetch all available patterns.
3. **Fetch every candidate pattern's schema** (`publishio-get-pattern-schema`) and read its default content to see slot sizes (headline length, body length, item count, which entries are images). For layout and image placement, call `publishio-apply-pattern-schema` with an empty `schema` and `render: true`. This determines what content fits before you write a word.
4. **Propose a section plan:** pattern name per section, fields to change, content sized to fit. If reference material was provided, disclose any dropped content with reasons. Get explicit approval.
5. **Resolve media** — list every image/video by section and search the library via `publishio-search-attachments`. For anything missing, ask the user to upload it in WordPress (Media → Add New), then search again. Don't proceed until done.
6. **Draft section content** in chat, already trimmed to fit pattern slots. Get approval.
7. **Create an empty page draft** via `publishio-create-post` with `post_type: "page"`.
8. **For each section in order:** fetch schema → fill → `publishio-preview-pattern` with the page ID and `position: -1` → **stop and wait for the user**. When they click Insert, the approval screen appends the section and tells you to continue: move on to the next section. If they ask for an alternative or changes, revise and preview again.
9. **SEO (mandatory):**
   - Set slug and excerpt via `publishio-update-post` (slug: short, keyword-rich; excerpt: 1–2 sentence meta description, max 155 chars).
   - Probe Yoast: call `publishio-get-yoast-meta`. If it succeeds, call `publishio-set-yoast-meta` with the fields it returned, out of `seo_title`, `meta_description` (max 155 chars), `focus_keyphrase`, `og_title`, `og_description`, `twitter_title`, `twitter_description` and `schema_page_type`. Leave out fields it didn't return: they aren't available to this user, and including one fails the whole call. If the probe fails, skip silently.
10. **Share the page link** (from `publishio-get-post` → `url`).

---

## Media

Search the library first via `publishio-search-attachments`. Show options as buttons if multiple match. If nothing fits, ask interactively: provide found options plus an "I'll upload it in WordPress" option, and search again once the user has uploaded it. Never ask the user to type a filename or describe an image when a button will do.

Never source media independently — only from the media library. No Publishio tool can add a file to the library, so never use an image URL from outside it.

In patterns, set an image or cover entry's `url`, `id` and `alt` together from `publishio-search-attachments`. A new `url` without its `id` drops the link to the attachment. In blog prose, insert images as `core/image` block markup with the attachment's URL and ID, never as bare `<img>` tags.

---

## SEO & Taxonomies

- **Categories & Tags** — Always call `publishio-get-taxonomy-terms` first to discover real existing terms before proposing or assigning any. Use `publishio-set-post-terms` to assign (taxonomy `"category"` or `"post_tag"`); it replaces the post's terms in that taxonomy, so pass the full list. No Publishio tool creates terms. If a new term is needed, propose it, and if the user agrees, ask them to add it in WordPress (Posts → Categories or Posts → Tags), then call `publishio-get-taxonomy-terms` again and assign it.
- **SEO is mandatory on every post and page.** Always set slug and excerpt via `publishio-update-post` (works regardless of plugins). Always probe Yoast with `publishio-get-yoast-meta` — if it succeeds, set the fields it returned with `publishio-set-yoast-meta`. If the probe fails, skip silently. Never skip slug/excerpt just because Yoast isn't active.
- **Post Types** — No tool lists post types. Use `post` for blog posts and `page` for landing pages. If the user names a custom post type, pass its slug to `publishio-create-post` (it fails if the type doesn't exist) and build it like a post.
- **Status** — Default is draft. You cannot change that even if the user asks.

---

## Common Mistakes

These are failure modes seen in practice. They are not a restatement of the rules above — they're specific anti-patterns to catch before they happen.

- **Asking questions as bullet lists.** Every question with a finite set of answers goes through `ask_user_input` as buttons. Prose question lists force the user to type when they could tap.
- **Tweaking pattern markup by hand.** If it's not in the schema, it's not your change. Pick a different pattern.
- **Landing page prose inlined as raw markup.** Pattern-only for pages. Find the text-content pattern or surface that none fits.
- **Writing content before checking pattern slot sizes.** Read the pattern's default content in its schema first, then size content to fit. Content written blind will overflow or clip.
- **Leaving out entries to drop items from a repeating pattern.** The items stay, with the pattern's placeholder content. Fill every entry. Need fewer or more items? Pick a different pattern.
- **Using a custom block because it "would be perfect here."** Banned everywhere. Patterns only (plus prose markup in posts).
- **Adding a pattern section without approval.** On pages, only the approval screen's Insert adds it. On posts, show the filled content and wait for approval before appending.
- **Adding a page section again after the user clicked Insert.** The approval screen already added it. Move on to the next section.
- **Previewing a post section on the approval screen.** Its Insert fails on posts. Use `publishio-apply-pattern-schema` and `publishio-insert-blocks`.
- **Silently dropping content the user provided.** When reference material is given, list what's being cut and why. Get acknowledgement. The user needs to know what was considered.
- **Skipping SEO because Yoast isn't installed.** Slug and excerpt via `publishio-update-post` work regardless. Always set them. Probe Yoast, skip if inactive — but never skip slug/excerpt.
- **Assigning categories/tags from memory.** Always call `publishio-get-taxonomy-terms` first. Made-up terms don't match the site's actual taxonomy.
- **Surfacing tool errors to the user.** Handle failures, retries, and unexpected results silently. Only communicate outcomes that affect the user's content. If a tool call fails and there's a fallback, use the fallback without narration. The exception is a read-only connection, which the user must hear about (see Before You Begin).
- **Generating the whole page in one shot.** Append section by section. One-shot drafts produce invalid markup and lose work mid-generation.
