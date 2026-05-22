# WordPress Content Generation Guide

Two non-negotiable mechanics:

1. **Schema-first for patterns.** Never hand-write pattern markup. Fetch the pattern's schema, mutate its attributes, render markup from the schema. Exception: in **blog posts**, plain prose blocks (paragraphs, headings, lists, quotes, inline images) may be written as direct block markup. In **landing pages**, no exception — every block comes from a pattern.
2. **Incremental assembly by append.** Never draft a full page in one go. Create an empty draft, then append one section at a time directly to the end of the current content.

Hand-written pattern markup drifts from the design system. One-shot drafts produce invalid markup and lose work mid-generation. These mechanics keep generation deterministic and recoverable.

---

## Critical Rules

- **Landing pages: patterns only.** No custom blocks, no raw markup. If no pattern fits, stop and tell the user.
- **Blog posts: basic prose blocks only.** Paragraphs, headings, lists, quotes, and inline images as direct block markup. No patterns, no complex blocks, no custom blocks.
- **Never write pattern markup by hand.** Always: fetch schema → mutate attributes → render markup → append.
- **Size-fit before committing.** Fetch the pattern's content to see how much each slot actually shows. Tailor copy to the pattern, not the reverse.
- **Append, don't rewrite.** Each section is added to the end of existing content via the site's append/update mechanism — never regenerate the whole document.
- **Discover before planning.** Fetch all available patterns from the site every time. Don't rely on memory.
- **Resolve media before content.** Identify every image/video, search the library, ask for uploads if missing. Don't proceed until resolved.
- **Draft content in chat first.** Show prose, headlines, and CTA labels for approval *before* touching schemas or WordPress.
- **Disclose dropped content.** When the user provides reference material (e.g., a Google Doc, brief, or URL), explicitly list any sections, points, or details you are *not* including in the draft and explain why (pattern slot limits, relevance, length constraints). Get acknowledgement before proceeding.
- **No custom HTML** outside standard block markup.
- **Share links as clickable** — never inside code blocks.

---

## The Pattern-Schema Workflow

The only acceptable way to place a pattern. Applies to posts and landing pages alike.

1. **Fetch the pattern schema** — the authoritative description of attributes (text, image refs, links, button labels, repeating-item counts).
2. **Fetch the full pattern content** to understand its structure, slot sizes, and default copy. This tells you how much content each slot actually shows. If a slot is too small for the user's content, pick a different pattern.
3. **Identify changeable attributes.** You change *values only*: text, image IDs/URLs, link hrefs, button labels, and (where supported) the count of repeating items. You do **not** touch markup structure, CSS classes, inline styles, spacing, or colors. If the user wants something not in the schema, say so and propose a different pattern.
4. **Fill the schema with new content** and append the result to the draft. The rendered output comes from the filled schema — never hand-written.

**Repeating items**: if a pattern's schema marks a group as repeatable, it does **not** automatically mean you can add or remove items. Some repeaters contain items with intentionally different styles, sizes, or layouts (e.g., alternating card orientations, a hero item followed by smaller cards). Changing the count in these cases will break the design.

Before adding or removing items from a repeatable group:
1. Fetch the pattern's rendered HTML to see how each item is actually styled. If items have different visual treatments (varying sizes, alternating layouts, unique backgrounds), the count must stay as-is.
2. Only modify the count when all items are visually uniform — same size, same style, same layout.
3. Content length per item must stay visually balanced (no single card with one word next to cards with full paragraphs).
4. The section must remain readable and scannable — no overwhelming walls of cards or sparse empty-looking sections.

If the schema does not mark a group as repeatable, the count is fixed — pick a different pattern if you need more or fewer items.

---

## The Incremental Assembly Workflow

Build the page in chunks; each insertion is small, verifiable, recoverable.

1. **Create an empty draft** in WordPress.
2. **Append the first section** to the draft.
3. **Repeat for every subsequent section.** Each append adds exactly one section to the end.
4. **Done.** When the last section is appended, the draft is complete.

If a section fails, the draft is valid up to the last successful append — no cleanup needed.

---

## Blog Posts

Blog posts use **basic prose blocks** only: paragraphs, headings, lists, quotes, and inline images — written as direct block markup.

No complex or custom blocks are allowed. If it's not a basic prose block, it can't be used in a blog post.

1. Ask: topic, audience, tone, length, featured image needed?
2. Resolve media.
3. Share a plan in chat: title, hook, section-by-section structure. If reference material was provided, list what is included and **what is being dropped or condensed**, with reasons. Get approval.
4. Draft section content in chat (prose, headings, excerpt). Get approval.
5. Create an empty draft.
6. Append each section in order as prose block markup.
7. Share the post link.

---

## Landing Pages

Pattern-only. No exception for "just a paragraph of text" — find the text-content pattern, or surface that none fits.

1. Ask: purpose, audience, primary CTA, sections needed, reference pages?
2. Fetch **all** available patterns **and their full content**. Review each pattern's slot sizes, item counts, and content lengths so you know what fits *before* planning.
3. Using the fetched pattern inventory, match user requirements to patterns. Check candidate patterns' content to confirm slot sizes (headline length, body length, image aspect, item count, repeatable ranges).
4. Propose a section plan: pattern name, attributes to change, content sized to fit. If reference material was provided, list what is included and **what is being dropped or condensed**, with reasons. Get **explicit approval**.
5. Resolve media — list every image/video by section, search the library, ask for uploads. Don't proceed until done.
6. Draft section content in chat, already trimmed to fit pattern slots. Get approval.
7. Create an empty page draft.
8. Section by section: fill the pattern schema with approved content and append to the draft.
9. Share the page link.

---

## Media

Search the library first. Show options if multiple match. Ask for a file or URL if nothing fits. Never source media independently. Media IDs/URLs go into pattern schemas — not raw `<img>` tags.

---

## Common Mistakes to Avoid

- **"I'll tweak the pattern markup a little."** No. If it's not in the schema, it's not your change. Pick a different pattern.
- **"Landing page, but this section is just text — I'll inline a paragraph."** No. Find the text-content pattern or surface that none fits.
- **"I'll write the content first and assume it fits."** No. Fetch the pattern content first, then size copy to its slots.
- **"I'll write the whole post body in one blob."** No. Append section by section.
- **"A custom block would be perfect here."** Banned everywhere. Patterns for pages, basic prose blocks for posts.
- **"I'll add one more card to this testimonial pattern — its schema isn't repeatable but I'll force it."** No. Only add/remove items when the schema explicitly marks the group as repeatable, and only after verifying the result renders cleanly.
