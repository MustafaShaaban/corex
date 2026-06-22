# Feature Specification: Pre-Site Asset & Media Hardening

**Feature Branch**: `spec/062-pre-site-asset-media-hardening`

**Created**: 2026-06-23

**Status**: In progress — PR A (asset-helper foundation + generated client pipeline + docs) implemented; the WebP
activation gate, reset-webp CLI, and dist client-asset verification are spec'd here and ship in PR B.

**Input**: The owner-approved final pre-site priority goal (`COREX-FINAL-PRE-SITE-GOAL.md`, a handoff — not
committed). Builds on the v0.29.0 team-safe readiness baseline (spec 061): `sites/<client>/` layout, the
shared-host `dist` builder, and CoreX Media (`MediaSettings`/`ConversionPlan`/`WebpConverter`/`PictureRenderer`/
`MediaImage`). Uses the existing `Corex\Assets\AssetManager`/`BuildManifest`/`AssetEnvironment` foundation (spec 047).

## Overview

Before building the first real company website, CoreX needs the **minimum safe asset + media foundation** so a
client theme/plugin can develop normal website assets (SCSS/JS/images, not just block assets) correctly, never
hardcode paths, never serve a bad WebP, and never deploy an incomplete build. This is a hardening pass, not new
product scope. All examples use the neutral **Acme** placeholder; no real client name enters the repo.

Two clearly separated concerns (documented in item 6):

- **`Corex\Assets\*`** — source-controlled **theme/plugin/client** assets (SCSS→CSS, JS, images, fonts).
- **`Corex\Media\*`** — WordPress **Media Library** uploads (the WebP-on-upload pipeline).

## Requirements

### Priority 1 — must finish before the first website

- **FR-062-01 — First-class asset helpers (PR A).** `Corex\Assets\Style`, `Script`, `Image`, `Picture` (facades over
  the existing `AssetManager`/`BuildManifest`), plus an `AssetRegistry` mapping named bases (corex / a theme /
  plugin / client) to managers. They resolve URL, path, version, and hashed built files from a manifest when
  present; fall back with a clear development warning; support theme/plugin/client and normal frontend + admin/editor
  assets (not only blocks); read WordPress `.asset.php` dependency/version files for JS; and **reject `.scss`** passed
  to an enqueue helper (SCSS is source only — compiled CSS is enqueued).
- **FR-062-02 — Generated client SCSS/JS/image pipeline (PR A).** `make:site --starter` generates
  `assets/src/{scss,js,images}/` → built `assets/{css,js,images}/`, with project-local `styles`/`scripts`/`images`/
  `build` npm scripts (no undocumented global tools), example code that uses the CoreX asset helpers (not hardcoded
  paths), and governance docs instructing developers to use the helpers.
- **FR-062-03 — WebP quality/activation gate (PR B).** Don't serve WebP just because it exists. On Media Library
  upload, keep the original as the attachment, generate the WebP derivative when enabled/supported, and **serve WebP
  only if it passes a gate**: the derivative exists, is readable + valid, dimensions match, transparency preserved
  where practical, and it is smaller than the original by a configured threshold (default 5%). Track per-derivative
  metadata (original/generated paths + bytes, saving %, dimensions, quality, source hash, generated_at,
  `active_for_delivery`, `inactive_reason`). Filter seams: quality, enabled, JPEG/PNG, minimum-saving threshold,
  convertible MIME types. If the gate fails, serve the original.
- **FR-062-04 — WebP reset/delete cleanup (PR B).** `wp corex media reset-webp [--dry-run] [--all] [--attachment=<id>]
  [--limit=<n>]` deletes **only CoreX-generated, tracked** derivatives (never originals, never manually-uploaded
  WebP, never unknown files), updates/clears the CoreX metadata, dry-runs, and reports scanned/deleted/skipped/failed
  counts. When an attachment is deleted, only its tracked CoreX derivatives are removed. Generated WebP is never a
  duplicate Media Library attachment; WebP status/actions surface through CoreX Media settings (full attachment-detail
  UI is future).
- **FR-062-05 — Dist client-asset verification (PR B).** `npm run build:dist` builds/requires the client asset build;
  `verify:dist` additionally checks compiled CSS/JS exist, optimized images exist where expected, a manifest exists if
  required, forbidden dev files are absent, and `dist/` stays git-ignored. Azure runs the asset build before packaging.
- **FR-062-06 — Source-of-truth docs (PR A + B).** README/PROGRESS/ROADMAP/CHANGELOG/DECISIONS/docs/docs-app/agent
  files + make:site stubs explain: `Corex\Assets\*` vs `Corex\Media\*`; SCSS is source-only and compiled CSS is
  enqueued; the helpers are the approved path; WebP is not served unless it passes the gate; generated WebP is not a
  duplicate attachment; reset/regenerate/delete behavior; client work in `sites/<client>/`; never edit
  `wp/wp-content/` or `dist/` as source.

### Non-functional

- **NFR-062-01** — No existing contract weakened; `Corex\Assets\*` and `Corex\Media\*` stay decoupled (Media is
  optional; the Assets layer is in corex-core and never hard-depends on it).
- **NFR-062-02** — Pure cores are unit-tested; WP boundaries are thin. SCSS is never enqueued/served.
- **NFR-062-03** — Guard Gate run on each diff; where a named guard has no command, the repo's executable validation
  is the documented fallback.

## Deferred (Priority 2 — explicitly NOT blockers)

Retrofitting every CoreX UI image block to the media delivery seam; the manual M6 RTL/200%/keyboard sweep; the Arabic
team-workflow docs mirror; PR #60 (Astro 7) validation/merge; a curated WordPress Font Library collection. These are
tracked (spec 061 backlog / ROADMAP) and must not delay the first company site once Priority 1 is complete.

## Acceptance

- A client theme can enqueue versioned CSS/JS and render images/`<picture>` via `Corex\Assets\*` with no hardcoded
  paths; passing a `.scss` to an enqueue helper fails clearly.
- `make:site --starter` produces a working `styles`/`scripts`/`images`/`build` pipeline whose examples use the helpers.
- (PR B) WebP is served only when it passes the gate; `reset-webp` removes only tracked derivatives; `verify:dist`
  fails an incomplete client asset build.
- Docs make the Assets-vs-Media split and the SCSS/WebP rules unambiguous.

## Out of scope

A new design system, more site kits/blocks/templates (Priority 3); a full attachment-detail WebP UI; AVIF generation.
