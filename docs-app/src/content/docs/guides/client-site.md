---
title: Build a client site
description: Generate a correctly-namespaced client site plugin + theme, ready for a team and AI agents.
---

Corex is a framework you build **client sites** on. One command scaffolds a client site — a **site plugin** for
app code and a **site theme** for presentation — with its own namespace and prefixes, distinct from Corex, plus the
governance files a team and AI agents need.

## Generate a site

```bash
wp corex make:site Acme
```

This creates, under the site root (`--path`, default the current directory + the slug):

- a **site plugin** `plugins/acme-site/` — app/business code under the `AcmeSite\` namespace (a service provider +
  `Models/Services/Controllers/Api/Blocks/Options`).
- a **site theme** `themes/acme/` — presentation only (a valid block theme: `style.css`, `theme.json`,
  `templates/`, `parts/`).
- **governance**: `AGENTS.md`, `CLAUDE.md`, `README.md`, `PROGRESS.md`, `DECISIONS.md`, a `.gitignore`, and
  `specs/` + `docs/` scaffolding.

The site's identity is **distinct from Corex's**: namespace `AcmeSite\`, text domain `acme-site`, REST namespace
`acme/v1`, CSS prefix `--acme-`, option/CPT prefix `acme_`. Client code imports Corex base classes but never uses
the `Corex\` namespace for its own classes.

### Flags

| Flag | Effect |
|---|---|
| `--starter` | also generate a **runnable example slice** + a **starter-theme asset setup** (see below) |
| `--minimal` | force the lean scaffold (no example) — same as the default; documents intent |
| `--plugin-only` / `--theme-only` | generate just one side |
| `--force` | regenerate (otherwise an existing site is skipped) |
| `--path=<dir>` | the site root |

### `--starter` — a runnable example to learn from and delete

```bash
wp corex make:site Acme --starter
```

Adds, on top of the lean scaffold, one complete vertical slice so you can see how a Corex client site is wired:

- a **model → repository → service → controller** (the controller returns the spec-043 response envelope at
  `GET acme/v1/examples`), a **server-rendered block** (`acme/example`), an **options page**, and a **Pest test** —
  all under `AcmeSite\`;
- a **starter-theme asset architecture**: `package.json` (`@wordpress/scripts` build — source maps in dev,
  minified in production, hashed `*.asset.php` for cache-busting), `assets/src/main.{scss,js}`, and an
  `inc/Assets.php` helper (`Assets::url()` / `path()` / `version()`) for images, icons, and fonts;
- the generated plugin autoloads `AcmeSite\` (PSR-4) and boots the example automatically.

A **`REMOVE-EXAMPLE.md`** in the plugin lists exactly which files to delete and which provider lines to unwire to
return to a clean scaffold. The default and `--minimal` produce no example.

## The team & AI workflow

The generated `AGENTS.md` / `CLAUDE.md` state the rules a team — and any AI agent — follows:

- **Edit only the client plugin/theme** (`plugins/acme-site/`, `themes/acme/`) — **never** the Corex framework.
- **One feature = one branch = one spec folder = one PR.** Pull `develop`, branch `feature/...`, add `specs/...`,
  implement, run the guards, update docs, open a PR, merge to `develop` (staging) → `main` (production).
- Local AI/cache/notes (`.corex/cache/`, `.ai/`, `.claude/local/`, …) are **git-ignored** — never committed; the
  project memory (specs, PROGRESS, DECISIONS, AGENTS) **is** committed.

## See also

- [REST resources](/guides/rest/) — `make:api-resource` writes into your client plugin.
- [Assets & cache-busting](/guides/assets/) · [Image optimization](/guides/media/) — the performance primitives a
  client site consumes.
