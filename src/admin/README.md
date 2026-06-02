# WordPress Admin Pages

React/TypeScript WordPress admin panel.

## Structure

| Dir            | Purpose                                                           |
| -------------- | ----------------------------------------------------------------- |
| `guide/`       | Setup guides for AI providers (Claude, OpenAI)                    |
| `connections/` | List and manage AI provider connections                           |
| `credentials/` | Create, edit, delete API credentials                              |
| `shared/`      | Reusable components (AdminHeader, CopyField, table fields, utils) |
| `styles/`      | Admin menu icon SCSS                                              |

## Entry points

Three independent screens, each an enqueued asset:

- `guide/index.tsx` → `admin` entry
- `connections/index.tsx` → `admin-connections` entry
- `credentials/index.tsx` → `admin-credentials` entry

Built by `npm run build:admin` (or `npm run start:admin` for dev).
