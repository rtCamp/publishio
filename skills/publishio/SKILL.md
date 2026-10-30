---
name: publishio
description: Builds and edits content on the user's WordPress site through Publishio, for posts, pages and other post types, from the site's own patterns and blocks. Use when the user asks to write a blog post, build or change a landing page, add or edit a section, save a pattern, or otherwise create or edit WordPress content, even if they don't mention patterns or blocks.
---

# Publishio content guide

## 1. Start

- If a write returns "This connection has read-only access, so it cannot make changes to the site.", stop. Tell the user this connection can read the site but can't change it, so they need to connect the app again and approve access to create and edit content. Don't retry.
- Every other refusal starts with a code and says how to fix it. Fix it yourself and tell the user only what affects their content.
- If the user wants this guide as a skill in their app, send them to the `guide_page_url` from `publishio-get-content-guide`, where they can download SKILL.md. Never write the file yourself.

## 2. Understand

- Find out what the user wants built or changed. Ask only what you can't work out yourself.
- Pick the post type with `publishio-get-post-types`. For existing content, find it with `publishio-search-posts`, then read its details and outline with `publishio-get-post` and its markup with `publishio-get-post-content`.
- See what the site offers with `publishio-list-patterns` (pass `post_type`), `publishio-get-pattern`, `publishio-list-block-types` and `publishio-get-block-type`.

## 3. Plan

Before any write, propose the whole plan as plain text and wait for a yes:

- the sections in order, with the pattern chosen for each, or a short description of anything new;
- the images, terms and SEO details;
- any published page that will change.

If the user gave you source material, say what you're leaving out and why. The yes covers the pattern choices too. Don't write anything before it.

## 4. Build

- Use the site's patterns first. `publishio-insert-pattern` adds one as real editor output, so never copy a pattern's markup into `publishio-insert-blocks`.
- Create an empty draft with `publishio-create-post`. Then add one section per call with `publishio-insert-pattern` or `publishio-insert-blocks`, never several at once, and give the user one short line on progress after each.
- If a call is refused, fix everything it lists and resend only that section.
- Blocks are addressed by paths such as `1:columns > 0:column > 2:paragraph`. Always use paths from the outline the latest call returned.
- To change text, links, images or attributes, read the block with `publishio-get-post-content` and its `path`, edit that markup, and send it back with `publishio-replace-block`. Rearrange with `publishio-move-block`, `publishio-duplicate-block` and `publishio-delete-blocks`.
- For a new block, start from the `example_markup` that `publishio-get-block-type` returns. `publishio-render-blocks` checks markup without saving it.
- A synced pattern is one `core/block` in the post. The outline lists its override fields; change their values by replacing that block with new values in its `content` attribute.

## 5. Media

Pick the best image from the media library with `publishio-search-attachments`, based on the page and the user's words. Don't ask the user to choose. If nothing fits, say so: they can upload one in WordPress (Media → Add New). Never use images from outside the library, and use the attachment's URL and ID together in image and cover blocks.

## 6. Live content

- Published, scheduled and private posts change only with the user's yes. The server refuses the first change and gives an `approval_token`. Ask the user, unless the plan they approved named this page, then repeat the call with the token.
- Change a saved pattern with `publishio-update-pattern` only when the user asks, and only an unsynced one.
- Never publish. Everything you create is a draft, and the user publishes it in WordPress.

## 7. Finish

- Assign terms with `publishio-get-taxonomy-terms` and `publishio-set-post-terms`. No tool creates terms, so ask the user to add a new one in WordPress. Set the featured image with `publishio-set-featured-image`.
- Set the slug and excerpt with `publishio-update-post`. If `publishio-get-yoast-meta` works, set the fields it returned with `publishio-set-yoast-meta`.
- When the content and all details are set, show the finished page with `publishio-preview-post` and share the links it returns. Show it again after later significant changes.
- Offer to save new sections as patterns with `publishio-create-pattern`.
