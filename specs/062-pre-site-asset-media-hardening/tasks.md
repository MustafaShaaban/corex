# Tasks: Pre-Site Asset & Media Hardening (spec 062)

`[x]` done in PR A (this branch) · `[ ]` PR B (immediately after) or deferred Priority 2.

## PR A — Asset helper foundation + generated client pipeline + asset docs

### T001 — Spec Kit
- [x] spec.md / plan.md / tasks.md.

### T002 — Asset helpers (FR-062-01)
- [x] T002a `Corex\Assets\AssetRegistry` (named bases → AssetManager; default; test seam) + binding.
- [x] T002b `Corex\Assets\ScriptOptions` (pure: deps/in_footer/strategy=defer|async/module/version + `.asset.php`).
- [x] T002c `Corex\Assets\Style` facade — resolve url+version, enqueue, **reject `.scss`**.
- [x] T002d `Corex\Assets\Script` facade — enqueue with strategy/module/in_footer/deps/version + `.asset.php`.
- [x] T002e `Corex\Assets\Image` facade — `tag()` `<img>` + `picture()` `<picture>` (source-controlled `.webp` sibling).
- [x] T002f Tests: AssetRegistry, ScriptOptions, Style scss-guard + url/version, Script attrs/.asset.php, Image/Picture.

### T003 — Generated client SCSS/JS/image pipeline (FR-062-02) — PR B
- [x] T003a `make:site --starter`: `assets/src/{scss,js,images}/` sources → `assets/{css,js,images}/` output +
  `styles`(sass)/`scripts`(wp-scripts)/`images`/`build` npm scripts + `sass` devDep.
- [x] T003b Generated `functions.php` enqueues the compiled output via the CoreX asset helpers (no hardcoded paths);
  README stub documents it. (Replaced the old per-theme `inc/Assets.php` — the framework helpers are the one way.)
- [x] T003c Generator tests updated (Scaffolder/Starter/Validation): new files/scripts + helper usage asserted.

### T004 — Asset docs (FR-062-06, asset half)
- [x] T004a docs-app + handbook: `Corex\Assets\*` vs `Corex\Media\*`; SCSS source-only; helpers are the approved path.
- [x] T004b README / agent files / make:site stubs note the helper usage + the Assets/Media split.

### T005 — Validation + PR A
- [x] T005a composer validate, PHP lint, Pest, Jest, build, docs-app build, build:dist+verify, git diff --check.
- [x] T005b Open + merge PR A.

## PR B — WebP gate + reset CLI + dist verification + media docs

- [ ] T010 `WebpGate` (pure: exists/readable/valid/dimensions/transparency/saving≥threshold → active + reason) +
  `WebpMeta` per-derivative record; wire into `ConversionPlan`/delivery; threshold + seams; tests.
- [ ] T011 `wp corex media reset-webp [--dry-run] [--all] [--attachment] [--limit]` — tracked-only deletion, counts,
  attachment-delete hook; tests.
- [ ] T012 Dist client-asset verification (compiled CSS/JS/images + manifest + forbidden absent) in
  `verify-shared-host-dist`; Azure asset build before packaging; tests.
- [ ] T013 Media-half docs (WebP gate, not-a-duplicate-attachment, reset/regenerate/delete) + CHANGELOG.

## Release
- [ ] T020 v0.30.0 after PR A + PR B merge and the release gate passes.

## Deferred (Priority 2 — not blockers; tracked in spec 061 backlog / ROADMAP)
- [ ] Retrofit CoreX UI image blocks to the media delivery seam (needs PictureRenderer class/loading preservation).
- [ ] Manual M6 RTL / 200% / full-keyboard sweep · Arabic team-workflow docs mirror · PR #60 Astro 7 · WP Font Library.
