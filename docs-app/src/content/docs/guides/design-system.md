---
title: The Design Language System
description: Corex's UI organized into Components, Blocks, Patterns, Templates, and Guidelines.
---

Corex has a WordPress-native **Design Language System** — its UI organized into five categories, all token-driven
and accessible. It lives in **`corex-ui`** (one home, no separate plugin).

## The taxonomy

| Category | What it is | Examples |
|---|---|---|
| **Components** | small UI atoms | alert, badge, breadcrumbs, copyright |
| **Blocks** | composed `corex/*` sections | hero, call-to-action, stat, testimonial, pricing, accordion, tabs, team, gallery, posts |
| **Patterns** | composed sections | hero section, content section, pricing table |
| **Templates** | FSE templates | front page, page, single, archive, 404 |
| **Guidelines** | the rules | design tokens, accessibility, RTL |

The `DesignSystemCatalog` (in `corex-ui`) enumerates these, and a drift test guarantees every listed **Block**
maps to a real registered `corex/*` block — the catalog can never list something that doesn't exist.

## Components

The component layer covers feedback/status atoms, including:

- **`corex/alert`** — an accessible `role="alert"` message with an info / success / warning / error variant.
- **`corex/badge`** — a small labelled, token-styled span.

Every component is server-rendered (dynamic), token-only (no hardcoded color/size), escaped, i18n-ready, and
RTL-correct via logical properties — the same rules as the rest of the library.

## Guidelines

- **Tokens are the single source of truth** — `theme.json` (+ per-site `brand.json`), exposed as CSS custom
  properties. No component hardcodes a color, size, or font.
- **Accessibility** — WCAG 2.2 AA: roles, labels, focus, and status announced to assistive tech.
- **RTL** — logical CSS properties (`margin-inline`, `inset-inline`, …) so Arabic is correct by default.

## See also

- [Create a block (CLI)](/guides/blocks/) — scaffold your own `corex/*` component with `make:block`.
- [Apply a brand](/guides/branding/) — the tokens the system consumes.
