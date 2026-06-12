# Data Model: Developer & operations handbook (028)

"Entities" here are the document structures the handbook is built from.

## Page front-matter (every handbook page)

```yaml
---
title: <human title>
description: <one line>
audience: setup | ops | contributor        # which tier this page serves
stability: stable | draft | planned        # 'planned' = the thing it documents isn't built yet
last_verified: YYYY-MM-DD | null           # set by the D12 verification pass
---
```

## Audience tiers

| Tier | Reader | Assumed knowledge |
|---|---|---|
| `setup` | a developer getting Corex running | zero Corex; some pages assume zero DevOps |
| `ops` | an operator deploying/running Corex | zero Corex; comfortable in a shell |
| `contributor` | someone changing the Corex codebase | zero Corex; reads the workflow |

## Conventions (enforced by `_template.md`)

- **Command → expected output**: every command is a language-tagged fenced block immediately followed by a
  second fenced block showing the expected output.
- **First mention of an external tool**: one-line description + install for Windows / Linux (apt) / macOS
  (brew) + a verify command and its expected output, in the same section.
- **No "simply"/"just"; no skipped steps.**
- **Link, don't duplicate**: architecture/class-reference references are links into `docs-app/`.
- **Planned references**: anything not yet built → `stability: planned` + a link to its Spec Kit module.
- **Diagrams**: ` ```mermaid ` blocks (GitHub-native) on every topology/lifecycle/deployment page.

## Templates (created in D1)

- `docs/en/_template.md` — the generic page skeleton (front-matter + sections + the command/output + tool-intro
  conventions).
- A **class-reference link-stub** template — instead of the brief's hand-written class page, a short stub that
  states the class's purpose in one line and **links to its generated docs-app reference page** (and is marked
  `stability: planned` if the class isn't built).

## Glossary (`docs/_glossary.md`)

A table: `Term | Plain-English definition | Arabic (Phase 2)`. Seeded with: Corex, Service Provider,
Repository, Container (DI), Event Bus, Middleware, Block (dynamic), Blueprint/Kit, Feature flag, Guard Gate,
Spec Kit, AdminGuard, Mailer seam, Field driver (ACF-optional).

## Translation memory (`docs/_translation-memory.md`)

A list of **locked English terms never translated**: all class names + namespaces (`Corex\…`), method names,
env vars (`FEATURES_*`, `COREX_*`), hook names, CLI flags (`--hard`, `--yes-i-mean-it`, `make:block`), file
paths, and the product names (Corex, WordPress, Docker, Azure, AWS, cPanel, nginx, php-fpm, MariaDB, redis,
mailpit, WP-CLI, Pest, Playwright, Composer, npm).

## Deployment recipe (one per target)

A self-contained page: prerequisites (with tool intros) → provisioning → configuration/App-Settings → deploy
(from a release **tag**) → HTTPS → secrets → backups → rollback → zero-downtime → CI/CD wiring → a **topology
Mermaid diagram** → a verification step + `last_verified` footer.
