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

> Run it with `--dir=sites/acme` so the client plugin/theme land directly under `sites/acme/` as `acme-site/`
> and `acme-theme/` (the flat layout above). **Backward compatibility:** sites generated before this layout used a
> nested `plugins/` + `themes/` structure; those keep working as-is — only newly generated sites use the flat
> layout, and the shared-host `dist` builder packages either shape.

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

Everything outside `sites/` and `.github/workflows/site-*.yml` is framework-owned, the repository root
included, and client work never edits it: an edit there is a merge conflict in every later framework update.
`npm run verify:framework` proves it — it compares every framework-owned path with the release recorded in
`sites/<client>/corex-baseline.json` and fails on anything that differs. Run it before pushing; the generated
`site-<client>.yml` workflow runs it on every pull request. (`wp corex compliance:check` answers a narrower
question — whether a list of file names you pass it touches a framework folder — and needs no git.)

To take a new CoreX release, follow [Updating CoreX in a client site](../05-deployment/updating-a-client-site.md).

## 3. Customize brand vs structure

- **Brand restyling** (colours, fonts, spacing) → the client theme's `theme.json` tokens / a style variation. No
  framework template edits.
- **Structural header/footer or layout changes** → override the template parts in the **client theme**
  (`acme-theme/parts/header.html`, `acme-theme/parts/footer.html`, `acme-theme/templates/front-page.html`), which
  override the CoreX parent theme. Never edit the CoreX parent theme for one client.
- **The launch page** → `acme-theme/templates/coming-soon.html`, the page visitors see while the site is in
  Coming soon mode. See [Coming soon mode](../03-operations/coming-soon.md).

## 4. Reusable vs client-specific

- A block/component useful across sites → contribute it to the **framework** (`addons/corex-ui` etc.) via a CoreX
  Framework Mode task.
- A company-specific block/page/template → keep it in `sites/<client>/`.

## 5. Build & deploy

The team commits **source only**. A deployable artifact is built into `dist/` (never committed) and deployed by
Azure Pipelines. See [Shared-host dist](../05-deployment/shared-host-dist.md) and
[Azure Pipelines](../05-deployment/azure-pipelines.md).
