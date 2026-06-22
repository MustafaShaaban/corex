# Implementation Plan: Pre-Site Asset & Media Hardening (spec 062)

## Approach

Two reviewable PRs. **PR A** (this branch) ships the asset-helper foundation (item 1), the generated client SCSS/JS/
image pipeline that uses those helpers (item 2), and the docs that draw the `Corex\Assets\*` vs `Corex\Media\*` line
(item 6, the asset half). **PR B** ships the WebP activation gate (item 3), the `reset-webp` cleanup CLI (item 4),
the dist client-asset verification (item 5), and the media half of the docs. Splitting keeps the framework asset
layer and the media-delivery hardening independently reviewable; both are Priority 1 and both land before the first
site (PR B follows PR A immediately).

## Architecture

- **Build on `AssetManager` (spec 047).** It already resolves url/path/version + manifest + environment per base.
  PR A adds, in `Corex\Assets`:
  - `AssetRegistry` — a container singleton mapping a base name (`corex`, a theme/plugin/client) to an
    `AssetManager`, with a settable default. The facades resolve through it; a test seam swaps it for fakes.
  - `Style` / `Script` / `Image` / `Picture` — facades. `Style::enqueue($handle,$rel,$opts)` resolves the manager,
    rejects `.scss`, and calls `wp_enqueue_style` with the resolved URL + version. `Script::enqueue` supports
    deps/in_footer/defer/async/module/version and reads a sibling `.asset.php`. `Image::tag`/`Picture::picture`
    render `<img>`/`<picture>` (a `.webp` sibling of a **source-controlled** image, distinct from Media uploads).
  - The non-WP decisions (scss guard, script-attribute spec, picture markup) live in pure, unit-tested cores
    (`ScriptOptions`, the renderers); the facades are thin WP boundaries.
- **Decoupling.** `Corex\Assets\*` lives in corex-core and never references `Corex\Media\*`. Picture-for-source-image
  resolves its own `.webp` sibling on disk; the Media-upload delivery stays in corex-media.
- **Generator (PR A).** `make:site --starter` gains `assets/src/{scss,js}` + `assets/{css,js,images}` + the
  `styles`/`scripts`/`images`/`build` scripts + an example that enqueues via the helpers + governance doc updates.
- **WebP gate (PR B).** A pure `WebpGate` scores a derivative (exists/readable/valid/dimensions/transparency/saving≥
  threshold) → `active_for_delivery` + `inactive_reason`; `WebpMeta` is the tracked per-derivative record
  (attachment meta). Delivery (`MediaImage`/`PictureRenderer`) consults the gate. `reset-webp` deletes only tracked
  derivatives.

## Guard Gate / fallback validation

`clean-code-guard`/`wp-guard`/`test-guard`/`docs-guard` applied as a review pass. Executable gate (the documented
fallback): `composer validate`, `php -l`, `vendor/bin/pest`, `npx jest`, `npm run lint:css/js`, `npm run build`,
`npm run build:dist` + `verify:dist`, docs-app `npm run build`, `npm run verify:dependencies`, `git diff --check`.

## Release

After PR A + PR B merge and the release gate passes, cut **v0.30.0** (new runtime: asset helpers + WebP gate + CLI +
dist verification).
