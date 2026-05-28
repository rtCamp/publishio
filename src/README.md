# src/

This directory contains all source code for the buildable WordPress assets.

- The `admin/` directory contains the React/TypeScript source for the plugin's admin interface.
- `tailwind.scss` provides the global styling for the plugin, processed by Tailwind CSS.

Assets are built by running `npm run build:dev` or `npm run build:prod`, which compiles the source files and outputs them to the `build/` directory. You can also run `npm run start:js` to start a development server that watches for changes and rebuilds automatically.

## Script entries

Defined in [`webpack.config.js`](../webpack.config.js). Each produces an enqueue-ready `.js` / `.css` asset in `build/`. These are registered to WordPress via `inc/Core/Assets.php`, and then enqueued as needed in the plugin classes that call them.

| Entry   | Source                | Purpose                                  |
| ------- | --------------------- | ---------------------------------------- |
| `admin` | `src/admin/index.tsx` | WordPress admin panel scripts and styles |

## Styles

Tailwind CSS is used for styling. The global configuration is defined in `tailwind.config.js` and the styles are processed from `src/tailwind.scss`.
