# Phase 1 Data Model: Theme + Design Tokens

No persisted entities — JSON token files + a resolver.

## Entity map

```text
wp_theme_json_data_theme filter
  └─ ThemeServiceProvider → BrandResolver.read(brand.json) → merge(defaults, brand) → updated theme.json data
theme/theme.json (defaults) ← single source of tokens (CSS custom properties)
theme/styles/dark.json ← example style variation (auto-discovered)
```

## 1. BrandResolver  *(FR-004, FR-005, FR-007)*

- `merge(array $defaults, array $brand): array` — recursive: assoc arrays merged key-by-key; scalars/
  lists replaced; unknown keys added; deepest key wins; siblings preserved. Pure.
- `read(string $path): array` — missing → `[]`; valid JSON array → the array; malformed → `[]` + log.
- Constructed with `BootLogger` (for the malformed-file warning).

## 2. theme.json (defaults)  *(FR-001–FR-003)*

- A v3 theme.json: `settings.color.palette`, `settings.typography.fontSizes`, `settings.spacing.spacingSizes`,
  layout — the token defaults, exposed as `--wp--preset--*`. The styling (style.css / parts / patterns)
  consumes only those variables (no hardcoded values).

## 3. brand.json (per site)  *(FR-004)*

- A partial JSON mirroring the token tree (e.g. `settings.color.palette` entries) at the active theme root
  (or `theme.brand_path`). Deep-merged onto the defaults at runtime.

## 4. Style variation  *(FR-008, FR-009)*

- `theme/styles/<name>.json` — a full alternate style (token-only). WordPress auto-registers it; selecting
  it applies its tokens site-wide without changing the default theme.json.

## 5. ThemeServiceProvider  *(FR-006, FR-012)*

- `register()`: bind `BrandResolver`. `boot()`: add the `wp_theme_json_data_theme` filter that reads
  brand.json and merges it onto the theme.json data. Added to `Boot`'s provider list. Lives in corex-core
  — the theme stays logic-free.

## Error paths

| Trigger | Handling | FR |
|---|---|---|
| missing brand.json | defaults unchanged | FR-005 |
| malformed brand.json | ignored + logged; defaults stand | FR-005, SC-004 |
| override of unknown path | added by the merge; siblings intact | FR-004 |
| nested override | only deepest key changes; siblings preserved | FR-004, SC-003 |
| theme deactivated | presentation only; data/API unaffected | FR-011 |
