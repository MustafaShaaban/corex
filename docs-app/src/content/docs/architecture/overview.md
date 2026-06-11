---
title: Architecture overview
description: How the pieces fit — the engine, the layers, the blocks, the add-ons.
---

Corex is a monorepo of one theme, the core plugins, and optional add-ons. The repo is the
source; a WordPress install loads it through `wp-content/` links.

## Layout

```
theme/                     # the skin — FSE templates/parts/patterns/tokens
plugins/
  corex-core               # the engine: Boot, Container, Config, Hooks, data layer, middleware
  corex-blocks             # block engine: discovery, conditional assets, connectors
  corex-forms              # forms: schema, validation, secured submit, the form block
  corex-config             # branding + the admin settings UI
addons/
  corex-ui                 # the corex/* block library + section patterns
  corex-email              # Corex Mail
  corex-newsletter · corex-careers · corex-captcha · corex-bookings
  corex-kit-company        # a site kit (blueprint + templates)
packages/
  cli                      # wp corex make:* generators
  build-tools              # @wordpress/scripts build conventions
docs-app/                  # this documentation site
```

## The principles (non-negotiable)

1. **The theme is a skin, not a skeleton** — presentation only.
2. **Plugins boot themselves** — `corex-core` self-inits on `plugins_loaded`.
3. **Thin controllers, fat services** — repositories are the only data callers.
4. **Everything is injected** — through the PSR-11 container.
5. **Design tokens are runtime** — `theme.json` → CSS vars → `brand.json`.
6. **Assets load conditionally** — a block's CSS/JS loads only where it renders.
7. **Security is declarative** — routes declare middleware; it applies automatically.
8. **RTL is first-class** — logical CSS properties by default.
9. **No optional dependency is a hard dependency** — ACF/Woo/Polylang behind interfaces.
10. **The spec is the source of truth** — code is generated from the spec.

## The request path

```
REST/AJAX → Controller (thin) → Service (logic) → Repository (data) → Model (shape)
            └ middleware: nonce → sanitize → throttle (declared, automatic)
```

## Blocks as components

Every section of a site is built from Corex blocks — dynamic, server-rendered, token-only.
There is no hardcoded section markup; a block's PHP renderer produces escaped output and
the editor previews it with `<ServerSideRender>`.
