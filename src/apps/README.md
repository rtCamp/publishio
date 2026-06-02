# MCP App Pages

Standalone MCP (Model Context Protocol) application pages. Each app renders as its own HTML page — not embedded inside the WP admin.

## Structure

| Dir                 | Purpose                          |
| ------------------- | -------------------------------- |
| `pattern-approval/` | MCP pattern approval review flow |

## App anatomy

Each app follows the same structure:

```
<app-name>/
  index.tsx        — Mount point, renders App into #root
  App.tsx          — Top-level component
  components/      — UI components
  hooks/           — Custom React hooks
  types.ts         — TypeScript types
  utils.ts         — Helpers
  styles.scss      — App-specific styles
  assets/          — Static assets (SVGs, etc.)
```

## Build

- Config: `webpack.apps.config.js`
- Output: `build-apps/<app-name>/` — one `.js` + `.html` per app
- Template: `src/apps/template.html` — HTML shell with `%%SITE_DATA%%` placeholder injected at serve time
- Dev: `npm run start:apps` runs HMR on port 8889 proxies to `SITE_URL`
- Production: `npm run build:apps`

## Adding an app

1. Create `<app-name>/` under `src/apps/` with an `index.tsx` entry
2. Add entry to `mcpApps` in `webpack.apps.config.js`
3. Register the app page in PHP (see `inc/` for existing pattern)
