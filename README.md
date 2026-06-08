# Corex

A professional, Laravel-inspired WordPress framework. Build any site — corporate,
e-commerce, multisite, headless, or AI-agent-driven — on one clean, documented foundation.

- **Target:** WordPress 7.0+, PHP 8.3+, FSE block themes.
- **Namespace:** `Corex\` · **CLI:** `wp corex` · **CSS prefix:** `--corex-`.
- **Stack:** Composer (PHP) + npm workspaces (JS), one monorepo.

## Repository layout (COREX-FRAMEWORK.md §4)

```
theme/                 parent block theme — presentation only (theme.json tokens)
plugins/
  corex-core/          MVC engine (Boot, DI container, controllers, services, …)
  corex-blocks/        block engine (auto-discovery, connectors, conditional assets)
  corex-config/        settings + .env resolution + security headers
addons/                optional, installable Composer packages (profile, forms, mail, woo)
packages/
  cli/                 wp corex commands + generator stubs
  build-tools/         shared build configuration
docs/                  derived/extra docs (the four COREX-*.md references live at root)
specs/                 Spec Kit specs (constitution pointer → .specify/memory/)
tests/                 Pest (Unit, Integration), Jest, Playwright (e2e)
```

## Read first (agents and humans)

1. `specs/constitution.md` → `.specify/memory/constitution.md` — the non-negotiable rules.
2. `PROGRESS.md` — current status and the recommended next step.
3. `COREX-FRAMEWORK.md` (architecture) and `COREX-WORKING-GUIDE.md` (how we work).
   `COREX-EMAIL-ADDON.md` is the Corex Mail spec; `COREX-SPECKIT-START.md` the build order.
   `CLAUDE.md` / `AGENTS.md` orient any coding agent.

## Local development

```bash
composer install        # wires the root PSR-4 autoloader (Corex\)
npm install             # links the npm workspaces
npx wp-env start        # Docker WordPress matching CI (see wp-env.json)
```

> Status: bootstrap stage — tooling, constitution, and the monorepo skeleton are in place.
> No framework code yet. See `PROGRESS.md`.
