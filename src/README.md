# src/

This directory contains all source code for the buildable WordPress assets.

- The `blocks/` directory contains WordPress blocks, and is automatically discovered by `@wordpress/scripts` and does not require a manual entry.
- The other directories (`admin/`, `editor/`, `frontend/`, `global-styles/`, and `example-module/`) contain scripts that are compiled as separate entries defined in `webpack.config.js`.

Assets are built by running `npm run build:dev` or `npm run build:prod`, which compiles the source files and outputs them to the `build/` directory. You can also run `npm run start` to start a development server that watches for changes and rebuilds automatically.

## Script entries

Defined in [`webpack.config.js`](../webpack.config.js) under `scriptEntries`. Each produces an enqueue-ready `.js` / `.css` asset in `build/`. These are registered to WordPress via `inc/Core/Assets.php`, and then enqueued as needed in the plugin classes that call them.

| Entry           | Source                         | Purpose                                                           |
| --------------- | ------------------------------ | ----------------------------------------------------------------- |
| `admin`         | `src/admin/index.ts`           | WordPress admin panel scripts and styles                          |
| `editor`        | `src/editor/index.ts`          | Block editor enhancements (toolbar buttons, sidebar panels, etc.) |
| `frontend`      | `src/frontend/index.ts`        | Public-facing JavaScript                                          |
| `global-styles` | `src/global-styles/index.scss` | CSS-only asset for styling PHP-generated or third-party markup    |

## Script module entries

Defined in [`webpack.config.js`](../webpack.config.js) under `scriptModuleEntries`. These are compiled as native ES modules and registered with `wp_register_script_module()` rather than `wp_enqueue_script()`.

| Entry            | Source                        | Purpose                                                                    |
| ---------------- | ----------------------------- | -------------------------------------------------------------------------- |
| `example-module` | `src/example-module/index.ts` | Demonstrates `@wordpress/interactivity` store usage as a standalone module |

## Blocks

Blocks are discovered automatically by `@wordpress/scripts` via the `--blocks-manifest` flag, and do not need to be listed in webpack entries. Each block lives in its own directory under `src/blocks/` and is defined by a `block.json` manifest. Blocks are autoloaded by `inc/Core/Assets.php` using `register_block_manifest()`.

| Block Type          | Description                                                                                                                                                      |
| ------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `static-block`      | A block whose output is serialized into `post_content` at save time by a React `Save` component.                                                                 |
| `dynamic-block`     | A block with no `Save` component. On every page load, WordPress calls `render.php` to produce the markup, giving full access to server-side data at render time. |
| `interactive-block` | A dynamic block that uses the [WordPress Interactivity API](https://developer.wordpress.org/block-editor/reference-guides/interactivity-api/).                   |
