# Spec 057 Logo Evidence

Captured: 2026-06-20 EEST (updated after owner-approved production logo package landed)

## Owner approval / provenance

The repository owner approved the design handoff root **"Design project questions answered (3)"**
(`design_handoff_corex_brand_system/`) as the authoritative design/provenance source for the production CoreX logo
system. The documented **locked winner** is the **"Core X"** mark — five rounded 12u modules on a 48x48 grid, 3u
gutters, 2.5u corner radius, four corner modules `currentColor`, center module brass `#c9a25e`. Confirmed against:

- `design_handoff_corex_brand_system/README.md` (§Logo + "Core X is the locked winner").
- `Corex Logo System.dc.html` — exact rect geometry, color variants (full/mono/knockout), app icons, clearspace.
- `Corex Brand System.dc.html` — `theme.json` tokens and the brass `#c9a25e` / AA `#ad8643` accents.

The legacy navy/cyan logo (removed by spec 121) is **not** approved production artwork; it was retained only as
rollback/migration evidence. No `.dc.html` prototype runtime files were shipped.

## Production assets (T059, T060)

Five optimized SVGs under `plugins/corex-config/assets/brand/`, recorded in `logo-manifest.json` with source, owner,
rights, approval date (2026-06-20), viewBoxes, filenames, sha256 checksums, variants, and accessible usage:

| Variant | File | viewBox | Accessible usage | Core fill |
|---|---|---|---|---|
| symbol | `corex-symbol.svg` | `0 0 48 48` | decorative | brass `#c9a25e` |
| wordmark | `corex-wordmark.svg` | `0 0 2600.5 728` | named-image | brass `x` |
| lockup | `corex-lockup.svg` | `0 0 170.02 48` | named-image | brass `#c9a25e` |
| monochrome | `corex-monochrome.svg` | `0 0 170.02 48` | decorative | `currentColor` |
| contrast | `corex-contrast.svg` | `0 0 170.02 48` | linked-brand | AA brass `#ad8643` |

The symbol geometry is verbatim from the documented mark. The wordmark glyphs are a **mechanical outline
extraction** (fontTools) from the self-hosted, OFL-licensed Space Grotesk variable font at `wght=600`, tracking
`-0.035em`, terminal `x` in brass — not traced, redrawn, or reinterpreted. Generator:
`scripts/generate-logo-assets.py`; optimization config: `scripts/svgo-logo.config.mjs`.

## Integration (T061, T062, T063)

- **T061:** the default CoreX product logo URL now points to `assets/brand/corex-lockup.svg`
  (`ConfigServiceProvider`). A per-site `brand.logo_url` override still wins, so client identity is unaffected.
- **T062:** admin surfaces use the documented decorative/named pattern without screen redesign — the dashboard
  header keeps a decorative `alt=""` mark beside the "Corex Settings" heading; the login mark is the product lockup
  with WordPress's site-name link text providing the accessible name (`AdminDashboard`, `AdminBranding`).
- **T063:** the legacy navy/cyan SVG is retained as rollback evidence; the logo contract now forbids genuine
  external-resource URLs and font-text dependencies while allowing the W3C SVG namespace (DECISIONS #102). Each
  shipped SVG verified: contains `<svg`/`viewBox`, no `<script|image|text>`, no external URL, no font-family/woff.

## Verification (T064)

- **Focused Pest — PASS:** `tests/Unit/Config/LogoAssetContractTest.php` (4/4) and
  `tests/Unit/Config/BrandingTest.php` (7/7) — 11 tests, 102 assertions GREEN.
- **Full Pest — PASS for logo scope:** 656 passed / 5 failed. The three previously-RED logo contracts are now
  GREEN (653 -> 656). The 5 remaining failures are the unrelated **US4** future-story contracts
  (`AdminTokenAdapterTest` ×3 needing `corex-admin-tokens.css`, `BrandOverrideCompatibilityTest` ×2 needing
  `BrandOverrideValidator`) and are not in this story's scope.
- **PHP lint — PASS** for all changed PHP. **Manifest JSON — PASS** (parses). **svgo — PASS** (5 assets optimized).
- **ENVIRONMENT-GATED (not PASS):** rendered browser minimum-size (16px favicon / 24px in-app), contrast, and
  forced-colors logo checks. Node v22.14.0 is below the browser-bridge v22.22.0 minimum and Docker/wp-env is
  unavailable, so no WordPress-rendered or headless logo screenshot evidence was produced. These remain explicit
  follow-up evidence, never reported as passing.

## Guard status

Recorded under T065 and re-affirmed for this batch: `clean-code-guard`, `wp-guard`, `test-guard`, and `docs-guard`
run on the US3 diff (see the session commit / PROGRESS entry for outcomes).

## Status

US3 (T056-T065) is implementation-complete with the approved production package. The remaining Spec 057 final-gate
tasks (T080-T090) still depend on US4 (T066-T079), which is unchanged by this work.

## Later change: spec 134 slice 3 (2026-11-17)

The set above is the state of 2026-06-20. Spec 134 slice 3 (FR-018 to FR-024, `design/handoffs/spec-134-logo-set.md`)
draws "Core" in the neutral colour with only the `x` and the centre in brass, adds fixed-colour lockups for light and
dark backgrounds, stacked lockups, the symbol in both, a knockout on brass and a favicon set (SVG and PNG at 16, 32,
180 and 512px), removes `corex-mark.svg`, and names the monochrome lockup "CoreX". The current list, with every
file's use, colours and hash, is `plugins/corex-config/assets/brand/logo-manifest.json`; the table above is kept as
the record of spec 057.
