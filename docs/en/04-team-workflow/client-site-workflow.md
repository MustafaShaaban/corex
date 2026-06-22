---
title: Client-site workflow
audience: team
stability: stable
---

# Client-site workflow

How a team builds a real company site on CoreX without touching framework internals. Examples use the neutral
**Acme** placeholder — substitute your real client only inside the generated client source.

## 1. Generate the client source

```bash
wp corex make:site Acme
```

This scaffolds a client **plugin** (`acme-site`, namespace `AcmeSite\`) and **theme** (`acme-theme`) with their own
prefixes, plus governance files (`AGENTS.md`, `CLAUDE.md`, `README.md`, `PROGRESS.md`, `DECISIONS.md`) and `specs/`
+ `docs/`. The target location for client source is:

```text
sites/acme/
  acme-site/
  acme-theme/
  AGENTS.md  CLAUDE.md  README.md  PROGRESS.md  DECISIONS.md
  specs/  docs/
```

> The generator currently writes a `plugins/` + `themes/` layout under its output dir; the move to the
> `sites/<client>/<client>-site` + `<client>-theme` layout above is tracked as spec 061 / PR C with a
> backward-compatibility note. Until that ships, place client source under `sites/<client>/` and adjust paths.

## 2. Work in Client Site Mode

Every session on the client site is **[Client Site Mode](./agent-roles.md#2-client-site-mode)**:

- Edit **only** `sites/<client>/`.
- Do **not** edit `plugins/`, `addons/`, `packages/`, root `theme/`, root `specs/`, `ROADMAP.md`, or root
  `PROGRESS.md`.
- Do **not** edit `wp/wp-content/` or `dist/` as source.
- Keep client specs in `sites/<client>/specs/`, progress in `sites/<client>/PROGRESS.md`, decisions in
  `sites/<client>/DECISIONS.md`.
- Follow **Spec Kit**, the **Guard Gate**, and **UI/UX ProMax**.
- For a framework bug, **stop** and open a CoreX Framework Mode task — never patch CoreX internals for one client.

## 3. Customize brand vs structure

- **Brand restyling** (colours, fonts, spacing) → the client theme's `theme.json` tokens / a style variation. No
  framework template edits.
- **Structural header/footer or layout changes** → override the template parts in the **client theme**
  (`acme-theme/parts/header.html`, `acme-theme/parts/footer.html`, `acme-theme/templates/front-page.html`), which
  override the CoreX parent theme. Never edit the CoreX parent theme for one client.

## 4. Reusable vs client-specific

- A block/component useful across sites → contribute it to the **framework** (`addons/corex-ui` etc.) via a CoreX
  Framework Mode task.
- A company-specific block/page/template → keep it in `sites/<client>/`.

## 5. Build & deploy

The team commits **source only**. A deployable artifact is built into `dist/` (never committed) and deployed by
Azure Pipelines. See [Shared-host dist](../05-deployment/shared-host-dist.md) and
[Azure Pipelines](../05-deployment/azure-pipelines.md).
