# Quickstart & Validation: Theme + Design Tokens

Runnable scenarios. Types live in [contracts/theme-contracts.md](./contracts/theme-contracts.md) and
[data-model.md](./data-model.md).

## Prerequisites

- corex-core active (spec 001); the Corex theme active; WordPress ≥ 7.0 at `./wp`. `composer install`.

## Run the tests

```bash
composer test   # headless: BrandResolver deep-merge + read; theme.json + dark.json validity
```

## Scenario 1 — Tokens from one source (US1, SC-001, SC-002)

```text
theme/theme.json defines color/typography/spacing/layout palettes → exposed as --wp--preset--* vars.
The theme's styling uses only those variables (no hardcoded hex/px/font, no CSS framework).
```
**Expected**: theme.json validates (v3); a scan finds no raw hex/px in the token-consuming styling.

## Scenario 2 — Rebrand via brand.json (US2, SC-003, SC-004)

```php
$resolver->merge($defaults, []);                              // → $defaults unchanged
$resolver->merge($defaults, ['settings' => ['color' => ['x' => 'brand']]]); // only that key changes
$resolver->read('/missing/brand.json');                       // → []
$resolver->read('/path/to/malformed.json');                   // → [] (logged)
```
**Expected**: a nested override changes only that token (siblings intact); a missing/malformed brand.json
leaves the defaults standing (logged on malformed). On the live site, the override appears as the
corresponding CSS custom property via the `wp_theme_json_data_theme` filter.

## Scenario 3 — Style variation (US3, SC-005)

```text
theme/styles/dark.json (token-only) is auto-discovered → selectable as a style variation;
selecting it applies its tokens site-wide; the default theme.json is unchanged.
```
**Expected**: the variation is valid v3, token-only, and selectable.

## Scenario 4 — The theme is a skin (US4, FR-010/FR-011)

```text
The theme contains no business logic / CPT registration / plugin bootstrapping; the brand resolver
lives in corex-core. Deactivating the theme affects presentation only.
```
**Expected**: a scan of theme/ finds no register_post_type/register_taxonomy/plugin bootstrap.

## Acceptance → scenario map

| Success criterion | Scenario |
|---|---|
| SC-001 token-only styling | 1 |
| SC-002 theme.json valid v3 | 1 |
| SC-003 deep-merge preserves siblings | 2 |
| SC-004 missing/malformed safe | 2 |
| SC-005 variation + no CPT/bootstrap | 3, 4 |
| SC-006 passes with no optional plugins | `composer test` |
| SC-007 resolver + validity covered | `composer test` |
