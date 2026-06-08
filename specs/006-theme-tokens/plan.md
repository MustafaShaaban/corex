# Implementation Plan: Theme + Design Tokens

**Branch**: `006-theme-tokens` | **Date**: 2026-06-08 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/006-theme-tokens/spec.md`

## Summary

Make the look configurable, not coded: `theme/theme.json` (v3) is the single source of design tokens
(exposed as CSS custom properties); a per-site `theme/brand.json` overrides token values at runtime via
a **headless deep-merge resolver** in corex-core, applied through the `wp_theme_json_data_theme` filter;
alternate looks ship as style variations (`theme/styles/*.json`); the theme stays a skin. The testable
core is the `BrandResolver` (recursive merge + malformed-file handling); theme.json and the example
variation are validated as artifacts.

## Technical Context

**Language/Version**: PHP 8.3 (resolver) + JSON (theme.json/brand.json/variations) + CSS (token-only).

**Primary Dependencies**: corex-core (`ServiceProvider`, `Config`, `BootLogger`); WP block-theme APIs
(`wp_theme_json_data_theme` filter, style-variation discovery in `styles/`).

**Storage**: None. JSON files in the theme.

**Testing**: Pest. The `BrandResolver` deep-merge + malformed handling is unit-tested headlessly; a
theme.json/variation validity check (valid JSON + v3 `version`) runs in the suite; the filter application
is a thin integration assertion.

**Target Platform**: WordPress ≥ 7.0 (theme.json v3), PHP ≥ 8.3.

**Project Type**: resolver in `Corex\Theme` (`plugins/corex-core/src/Theme`); tokens/variations in
`theme/`.

**Constraints**: token-only styling (no hardcoded values/CSS framework); logical CSS/RTL; WCAG AA; the
theme holds no logic; works with no optional plugin.

**Scale/Scope**: tokens + brand.json overrides + style variations + skin discipline + one example
variation. No full design system, forms, JS, or build pipeline. No NEEDS CLARIFICATION (clarified).

## Constitution Check

- [x] **I. Theme is a skin** — **PASS (headline)**: theme is presentation only; the resolver lives in
  corex-core, not the theme (FR-010/FR-012).
- [x] **II. Plugins boot themselves** — PASS: resolver registers via a provider and hooks the theme.json filter.
- [x] **III/IV** — PASS: resolver injected; no business logic.
- [x] **V. Runtime tokens** — **PASS (headline)**: theme.json is the single source; brand.json overrides at
  runtime; no build-time token system (FR-001/FR-002).
- [x] **VI. Conditional assets** — PASS: no global library; token-driven CSS only.
- [x] **VII** — N/A (no request handling).
- [x] **VIII. RTL-first** — PASS: logical CSS; RTL-correct by default (FR-013).
- [x] **IX. No optional dep is hard** — PASS: no optional plugin referenced (FR-014, SC-006).
- [x] **X. Spec is source of truth** — PASS: traces to spec 006; implements FRAMEWORK §10.
- [x] **Guard Gate + Definition of Done** — guards per task; WCAG AA palettes; i18n template strings.

**Result: PASS** — no violations.

## Project Structure

```text
plugins/corex-core/src/Theme/
├── BrandResolver.php          # deep-merge brand.json onto theme.json data; malformed → ignore + log (headless)
└── ThemeServiceProvider.php   # bind BrandResolver; hook wp_theme_json_data_theme to apply it

plugins/corex-core/config/theme.php   # ['brand_path' => '']  (default: active theme root)

theme/
├── theme.json                 # the single source of tokens (v3) — colors/typography/spacing/layout
├── styles/dark.json           # example style variation (token-only, dark palette)
└── (templates/parts/patterns already present — token-consuming)

tests/Unit/Theme/              # BrandResolver deep-merge + malformed; theme.json + variation validity
```

**Structure Decision**: The resolver is corex-core (`Corex\Theme`) so the theme stays logic-free
(Principle I); `ThemeServiceProvider` joins `Boot`'s provider list and applies the merge via the
`wp_theme_json_data_theme` filter. Tokens + variations are JSON in `theme/`.

## Key design decisions

1. **Headless deep-merge resolver** — `BrandResolver::merge(array $defaults, array $brand): array` is a
   pure recursive merge (associative arrays merged key-by-key; deepest key wins; unknown paths added;
   scalars/lists replaced). `read(string $path): array` reads + json-decodes brand.json → `[]` for a
   missing file, `[]` + log for a malformed one (FR-004, FR-005).
2. **Filter application** — `ThemeServiceProvider` hooks `wp_theme_json_data_theme`: read brand.json, merge
   onto `$data->get_data()`, `update()` — overrides reach editor + front end as CSS custom properties (FR-006).
3. **theme.json as the source** — a v3 `theme.json` with color/typography/spacing/layout palettes; styling
   consumes only `--wp--preset--*` variables (FR-001/FR-002).
4. **Style variation** — `theme/styles/dark.json`, a full token-only alternate style WordPress
   auto-registers from `styles/` (FR-008).
5. **Validity as a test** — the suite asserts `theme.json` + `dark.json` are valid JSON with a v3
   `version` (SC-002), so a malformed token file fails CI.

## Phase 0 — Research

See [research.md](./research.md). No open NEEDS CLARIFICATION.

## Phase 1 — Design & Contracts

- [data-model.md](./data-model.md) · [contracts/theme-contracts.md](./contracts/theme-contracts.md) ·
  [quickstart.md](./quickstart.md).

## Complexity Tracking

No constitution violations — section intentionally empty.
