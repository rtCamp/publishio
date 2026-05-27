---
name: rt-publish-with-ai
description: Generates WordPress content using the site's existing patterns (never custom blocks), assembled via pattern schemas and built up incrementally rather than written in one shot. Landing pages are pattern-only; blog posts may use direct block markup for plain prose (paragraphs, headings, lists, quotes, inline images) but still use the pattern-schema workflow for any structured section. Use whenever the user asks to "write a blog post", "create a landing page", "add a section", "draft content", "build a page", or mentions WordPress content creation, page assembly, or publishing workflows. Use this skill even if the user doesn't explicitly mention patterns or schemas — the schema-first, incremental approach is how WordPress content should be produced in this site, period.
---

# WordPress Content Generation

Two non-negotiable mechanics:

1. **Schema-first for patterns.** Never hand-write pattern markup. Fetch the pattern's schema, mutate its attributes, render markup from the schema. Exception: in **blog posts**, plain prose blocks (paragraphs, headings, lists, quotes, inline images) may be written as direct block markup. In **landing pages**, no exception — every block comes from a pattern.
2. **Incremental assembly by append.** Never draft a full page in one go. Create an empty draft, then append one section at a time directly to the end of the current content.

Hand-written pattern markup drifts from the design system. One-shot drafts produce invalid markup and lose work mid-generation. These mechanics keep generation deterministic and recoverable.

---

## Critical Rules

- **Landing pages: patterns only.** No custom blocks, no raw markup. If no pattern fits, stop and tell the user.
- **Blog posts: prose may be direct block markup**, but anything structured (cards, CTAs, heroes, columns, galleries, testimonials) still goes through the pattern-schema workflow. Custom blocks remain banned.
- **Never write pattern markup by hand.** Always: fetch schema → mutate attributes → render markup → append.
- **Size-fit before committing.** Render the pattern (or read its schema's layout) to see how much each slot actually shows. Tailor copy to the pattern, not the reverse.
- **Append, don't rewrite.** Each section is added to the end of existing content via the site's append/update mechanism — never regenerate the whole document.
- **Discover before planning.** Fetch all available patterns from the site every time. Don't rely on memory.
- **Resolve media before content.** Identify every image/video, search the library, ask for uploads if missing. Don't proceed until resolved.
- **Draft content in chat first.** Show prose, headlines, and CTA labels for approval _before_ touching schemas or WordPress.
- **Always ask questions interactively using the `ask_user_input` tool.** Never ask clarifying questions as prose bullet points. Every question that requires a choice — images, CTA URLs, tone, audience, sections, publish status, etc. — must be presented as interactive buttons so the user taps rather than types. Minimise typing to zero wherever possible.
- **Always create as draft** unless the user explicitly says to publish.
- **No custom HTML** outside standard block markup.
- **Share links as clickable** — never inside code blocks.
- **Never surface internal errors or retries to the user.** If a tool call fails, retries, or returns an unexpected result, handle it silently. Never say "trying again", "that returned an error", "now attempting", or any variation. Only communicate outcomes that matter to the user.

---

## The Pattern-Schema Workflow

The only acceptable way to place a pattern. Applies to posts and landing pages alike.

1. **Fetch the pattern schema** — the authoritative description of attributes (text, image refs, links, button labels, repeating-item counts).
2. **Render the pattern** with default content to see how each slot is sized. This determines what copy actually fits without clipping or ugly wrap. If a slot is too small for the user's content, pick a different pattern.
3. **Identify changeable attributes.** You change _values only_: text, image IDs/URLs, link hrefs, button labels, and (where supported) the count of repeating items. You do **not** touch markup structure, CSS classes, inline styles, spacing, or colors. If the user wants something not in the schema, say so and propose a different pattern.
4. **Mutate the schema** — edit the structured data, not rendered markup.
5. **Render markup from the mutated schema** using the site's rendering mechanism. The markup you insert is the output of this step, never something typed by hand.
6. **Append the rendered markup** to the draft (see below).

**Repeating items**: you may _reduce_ count by removing entries. You may not _add_ beyond the pattern's design. If you need more items, pick a different pattern.

---

## The Incremental Assembly Workflow

Build the page in chunks; each insertion is small, verifiable, recoverable.

1. **Create an empty draft** in WordPress.
2. **Append the first section.** Produce its markup (pattern-schema workflow for patterns; direct block markup for prose in blog posts), then append it to the draft's existing content using the site's update/append mechanism. Read the draft's current content, concatenate the new section, and write it back — no anchors, no placeholders.
3. **Repeat for every subsequent section.** Same append operation: read current content, append new markup, write back. Each iteration adds exactly one section.
4. **Done.** When the last section is appended, the draft is complete. No cleanup needed.

**Why append (not anchor-based search-and-replace):**

- One fewer operation per section (no anchor insertion, no anchor removal).
- Nothing to verify between steps — no "is the anchor still there?" failure mode.
- No risk of leaving stray markers in the final document.
- Still recoverable: if a section fails, the draft is valid up to the last successful append.

**Append hygiene**: if the site's API requires sending full content, fetch it first, concatenate, then send. Do not regenerate prior sections — pass them through unchanged.

**Step-by-step visual previews**: if the user asks to see a preview after each section, call `rtpwai/screenshot-post` immediately after each append. If the tool returns `not_supported`, skip silently and continue — do not fail or pause the generation flow.

---

## Blog Posts

Prose body (paragraphs, headings, lists, quotes, inline images) uses direct block markup. Any pattern section uses the full schema workflow.

1. **Ask interactively** using `ask_user_input`: topic/title (typed), audience (buttons), tone (buttons: Professional / Conversational / Educational), length (buttons: Short / Medium / Long), featured image needed (Yes / No).
2. Use `rtpwai/get-taxonomy-terms` to fetch existing categories and tags. Fetch **all** available patterns.
3. Resolve media.
4. Share a plan in chat: title, hook, section-by-section structure. Mark each section as _prose_ or _pattern: \<name\>_. Get approval.
5. Draft section content in chat (prose, headings, excerpt, proposed categories/tags, SEO fields if Yoast is active). For pattern sections, fetch/render the pattern first and size content to its slots. Get approval.
6. Create an empty draft.
7. For each section in order: produce markup (prose markup directly, or pattern-schema workflow for patterns), then append to the draft.
8. Assign categories and tags using `rtpwai/set-post-terms` (taxonomy `"category"` then `"post_tag"`).
9. **SEO & metadata (mandatory).** Always do both of the following — never skip either:
   - Set the slug and excerpt via `rtpwai/update-post` (slug: short, keyword-rich; excerpt: 1–2 sentence meta description, max 155 chars).
   - Attempt Yoast: call `rtpwai/set-yoast-meta` with `seo_title`, `meta_description` (max 155 chars), `focus_keyphrase`, `og_title`, `og_description`, `twitter_title`, `twitter_description`. If Yoast returns a not-active error, skip silently — the slug/excerpt from above already cover native WordPress meta.
10. Call `rtpwai/screenshot-post` to show a final visual preview of the assembled post. If it returns `not_supported`, skip silently. **Screenshot is always the very last step.**
11. Share the post link. Ask interactively: Publish now / Submit for review / Keep as draft.

---

## Landing Pages

Pattern-only. No exception for "just a paragraph of text" — find the text-content pattern, or surface that none fits.

1. **Ask interactively** using `ask_user_input`: purpose (typed or buttons if common types apply), audience (buttons), primary CTA label and URL (typed), sections needed (multi-select of common section types), reference pages (typed URL or "None").
2. Fetch **all** available patterns.
3. Render every pattern in the plan to inspect slot sizes (headline length, body length, image aspect, item count).
4. Propose a section plan: pattern name, attributes to change, content sized to fit. Get **explicit approval**.
5. Resolve media — list every image/video by section, search the library, ask for uploads. Don't proceed until done.
6. Draft section content in chat, already trimmed to fit pattern slots. Get approval.
7. Create an empty page draft.
8. Section by section: fetch schema → render to confirm fit → mutate → render final → append.
9. **SEO & metadata (mandatory).** Always do both of the following — never skip either:
   - Set the slug and excerpt via `rtpwai/update-post` (slug: short, keyword-rich; excerpt: 1–2 sentence meta description, max 155 chars).
   - Attempt Yoast: call `rtpwai/set-yoast-meta` with `seo_title`, `meta_description` (max 155 chars), `focus_keyphrase`, `og_title`, `og_description`, `twitter_title`, `twitter_description`, and `schema_page_type`. If Yoast returns a not-active error, skip silently — the slug/excerpt from above already cover native WordPress meta.
10. Call `rtpwai/screenshot-post` to show a final visual preview of the assembled page. If it returns `not_supported`, skip silently. **Screenshot is always the very last step.**
11. Share the page link. Ask interactively: Publish now / Keep as draft.

---

## Media

Search the library first. Show options if multiple match. If nothing fits, ask interactively using `ask_user_input`: provide found options as buttons plus a "I'll provide a URL" option — never ask the user to type a filename or describe an image when a button will do. Never source media independently. Media IDs/URLs go into pattern schemas — not raw `<img>` tags.

---

## Supporting Details

- **Categories & Tags** — Use `rtpwai/get-taxonomy-terms` to discover existing terms. Use `rtpwai/set-post-terms` to assign them (taxonomy `"category"` or `"post_tag"`). Create new terms only with explicit user confirmation; if confirmed, create via the WordPress REST API and then assign with `rtpwai/set-post-terms`.
- **SEO (mandatory on every post/page)** — Always set slug and excerpt via `rtpwai/update-post` first (these work regardless of plugins). Then always attempt `rtpwai/set-yoast-meta` with: `seo_title`, `meta_description` (max 155 chars), `focus_keyphrase`, `og_title`, `og_description`, `twitter_title`, `twitter_description`. For landing pages also set `schema_page_type`. If Yoast returns a not-active error, skip silently — do not mention it to the user.
- **Post Types** — Check registered post types first; use the relevant custom post type if one exists.
- **Status** — Default is draft. Change only on explicit instruction (publish, pending, scheduled, private).
- **Screenshots** — `rtpwai/screenshot-post` is always the very last step, after SEO/metadata. It returns an inline image when the feature is enabled and configured. If it returns `not_supported`, skip silently, never surface the error to the user.

---

## Common Mistakes to Avoid

- **"I'll ask clarifying questions as a bullet list."** No. Every question with a finite set of answers goes through `ask_user_input` as buttons. Prose question lists are banned — they force the user to type when they could tap.
- **"I'll tweak the pattern markup a little."** No. If it's not in the schema, it's not your change. Pick a different pattern.
- **"Landing page, but this section is just text — I'll inline a paragraph."** No. Find the text-content pattern or surface that none fits.
- **"I'll write the content first and assume it fits."** No. Render the pattern first, then size content to its slots.
- **"I'll write the whole post body in one blob."** No. Append section by section.
- **"A custom block would be perfect here."** Banned everywhere. Patterns only (plus prose markup in posts).
- **"I'll add one more card to this testimonial pattern."** No. Reduce, never extend. Need more items? Pick a different pattern.
- **"The screenshot tool failed — I should tell the user."** No. If `rtpwai/screenshot-post` returns `not_supported`, skip silently. It means the feature is not configured, not that something went wrong with the content.
- **"I'll tell the user I'm retrying or that something errored."** No. Handle tool failures, retries, and unexpected results silently. Only report outcomes that affect the user's content.
- **"I'll use whatever categories/tags come to mind."** No. Always call `rtpwai/get-taxonomy-terms` first to discover real existing terms before proposing or assigning any.
- **"Yoast isn't installed so I'll skip SEO entirely."** No. Always set slug and excerpt via `rtpwai/update-post` regardless of Yoast. Then attempt `rtpwai/set-yoast-meta` — if it errors, skip silently. Never skip the slug/excerpt step.
- **"I'll do the screenshot before setting SEO."** No. SEO and metadata always come before the screenshot. Screenshot is always the very last step.
