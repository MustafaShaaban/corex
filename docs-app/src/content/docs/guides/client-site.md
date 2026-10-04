---
title: Build a client site
description: Generate a correctly-namespaced client site plugin + theme, ready for a team and AI agents.
---

Corex is a framework you build **client sites** on. One command scaffolds a client site — a **site plugin** for
app code and a **site theme** for presentation — with its own namespace and prefixes, distinct from Corex, plus the
governance files a team and AI agents need.

## Generate a site

```bash
wp corex make:site Acme --dir=sites/acme
```

This creates, **under the site root** (`--dir`, default the current directory + the slug — e.g. `sites/acme/`),
the client plugin + theme as **one client unit** (it is not a WordPress install — the local WordPress in `./wp`
loads it):

- a **site plugin** `sites/acme/acme-site/` — app/business code under the `AcmeSite\` namespace (a service
  provider + `Models/Services/Controllers/Api/Blocks/Options`).
- a **site theme** `sites/acme/acme-theme/` — presentation only (a valid block theme: `style.css`, `theme.json`,
  `templates/`, `parts/`).
- **governance** at the site root: `AGENTS.md`, `CLAUDE.md`, `README.md`, `PROGRESS.md`, `DECISIONS.md`, a
  `.gitignore`, and `specs/` + `docs/` scaffolding.
- **what lets the site take CoreX updates**: `corex-baseline.json` (the framework release and commit the
  site is on), `UPDATING-COREX.md` (the update checklist, with this site's paths), and — when the site root
  is `<repository>/sites/<client>` — the client's own CI workflow at
  `.github/workflows/site-<client>.yml`. Generated anywhere else, the command says the workflow was skipped.
  See [Update CoreX in a client site](/guides/updating-a-client-site/).

The site's identity is **distinct from Corex's**: namespace `AcmeSite\`, text domain `acme-site`, REST namespace
`acme/v1`, CSS prefix `--acme-`, option/CPT prefix `acme_`. Client code imports Corex base classes but never uses
the `Corex\` namespace for its own classes.

## Validate the scaffold

`wp corex readiness` generates temporary minimal and starter client sites and validates them. The `make-site` row
passes only when the generated scaffold includes:

- isolated `acme-site/` (plugin) and `acme-theme/` (theme) folders under the site root,
- client namespace, CSS prefix, and option prefix distinct from Corex,
- `AGENTS.md`, `CLAUDE.md`, `PROGRESS.md`, `DECISIONS.md`, `specs/`, and `docs/`,
- a theme token strategy in `acme-theme/theme.json`,
- starter example files only for `--starter`.

For client repositories, the generated `site-<client>.yml` workflow runs `node scripts/verify-framework.mjs`
on every pull request. It compares every framework-owned path — everything outside `sites/` and the client's
own workflow, the repository root included — with the recorded release, and fails on anything that differs.
`wp corex compliance:check` remains for the narrower question of whether a list of changed file names
touches `plugins/corex-*`, `addons/corex-*`, `packages/`, or `theme/`.

### Flags

| Flag | Effect |
|---|---|
| `--starter` | also generate a **runnable example slice** + a **starter-theme asset setup** (see below) |
| `--minimal` | force the lean scaffold (no example) — same as the default; documents intent |
| `--plugin-only` / `--theme-only` | generate just one side |
| `--force` | regenerate (otherwise an existing site is skipped) |
| `--dir=<dir>` | the site root. Not `--path`: WP-CLI takes that for itself, as the WordPress install |

### `--starter` — a runnable example to learn from and delete

```bash
wp corex make:site Acme --starter
```

Adds, on top of the lean scaffold, one complete vertical slice so you can see how a Corex client site is wired:

- a **model → repository → service → controller** (the controller returns the spec-043 response envelope at
  `GET acme/v1/examples`), a **server-rendered block** (`acme/example`), an **options page**, and a **Pest test** —
  all under `AcmeSite\`;
- a **starter-theme asset architecture**: `package.json` (project-local `sass` + `@wordpress/scripts` build —
  source maps in dev, minified in production, hashed `*.asset.php` for cache-busting), an
  `assets/src/{scss,js,images}/` → `assets/{css,js,images}/` pipeline (`styles`/`scripts`/`images`/`build`
  scripts), and a `functions.php` that enqueues the **compiled** output through the CoreX asset helpers
  (`Corex\Assets\*`) — never hardcoded paths;
- the generated plugin autoloads `AcmeSite\` (PSR-4) and boots the example automatically.

A **`REMOVE-EXAMPLE.md`** in the plugin lists exactly which files to delete and which provider lines to unwire to
return to a clean scaffold. The default and `--minimal` produce no example.

## The team & AI workflow

The generated `AGENTS.md` / `CLAUDE.md` state the rules a team — and any AI agent — follows:

- **Edit only the client plugin/theme** (`sites/acme/acme-site/`, `sites/acme/acme-theme/`) — **never** the
  Corex framework.
- **One feature = one branch = one spec folder = one PR.** Pull `develop`, branch `feature/...`, add `specs/...`,
  implement, run the guards, update docs, open a PR, merge to `develop` (staging) → `main` (production).
- Local AI/cache/notes (`.corex/cache/`, `.ai/`, `.claude/local/`, …) are **git-ignored** — never committed; the
  project memory (specs, PROGRESS, DECISIONS, AGENTS) **is** committed.

## See also

- [REST resources](/guides/rest/) — `make:api-resource` writes into your client plugin.
- [Assets & cache-busting](/guides/assets/) · [Image optimization](/guides/media/) — the performance primitives a
  client site consumes.
