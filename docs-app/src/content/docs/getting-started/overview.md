---
title: Getting started — overview
description: What you need and the two supported paths to run Corex locally.
---

Corex is the framework **source** (a theme + plugins + add-ons in a monorepo). To run it
you map that source into a real WordPress install — the repo stays the single source of
truth; WordPress core is never committed.

## Requirements

- **PHP 8.3+** and **WordPress 7.0+**
- **Composer 2** (PHP dependencies + autoload)
- **Node 20+** and npm (the block/SCSS/JS build, and these docs)
- **WP-CLI** (install, activate, and the `wp corex` commands)
- A local stack: **WAMP/Apache + MySQL**, or **wp-env/Docker**

## The two paths

1. **[WAMP / Apache + WP-CLI](/getting-started/wamp-apache/)** — install WordPress into
   `./wp`, run it on your local Apache. This is how this repository is set up.
2. **[wp-env / Docker](/getting-started/wp-env-docker/)** — a throwaway containerised
   WordPress via `@wordpress/env` (config already in `wp-env.json`).

Either way you then **[wire the monorepo](/getting-started/monorepo-wiring/)** into
`wp-content/` and do the **[first run](/getting-started/first-run/)**.

## The mental model

- `theme/` is a **skin** — presentation only (FSE templates, parts, patterns, tokens).
  Deactivating it breaks presentation, never data or behaviour.
- `plugins/corex-core` **boots itself** on `plugins_loaded` and is the engine.
- `plugins/corex-blocks`, `plugins/corex-forms`, `plugins/corex-config` and the
  `addons/*` mount on the core.
- Build the front-end assets once with `npm install && npm run build` (see
  [Create a block](/guides/blocks/)).

:::tip
Run `composer install` (PHP autoload + dev tools) and `npm install` (block/docs build)
from the repo root before anything else.
:::
