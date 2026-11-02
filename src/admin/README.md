# WordPress Admin Pages

React/TypeScript WordPress admin panel.

## Structure

| Dir            | Purpose                                                                                 |
| -------------- | --------------------------------------------------------------------------------------- |
| `guide/`       | MCP server URL, SKILL.md download, readiness check and setup guides (Claude, ChatGPT)   |
| `connections/` | List and remove AI app connections                                                      |
| `status/`      | Checks whether AI apps can reach the site, with a copyable report                       |
| `shared/`      | Reusable components (AdminHeader, CopyField, notices, table fields, site checks, utils) |
| `styles/`      | Admin menu icon SCSS                                                                    |

## Entry points

Three independent screens and the menu icon, each an enqueued asset:

- `guide/index.tsx` → `admin` entry
- `connections/index.tsx` → `admin-connections` entry
- `status/index.tsx` → `admin-status` entry
- `styles/menu-icon.scss` → `admin-menu-icon` entry

Built by `npm run build:admin` (or `npm run start:admin` for dev).
