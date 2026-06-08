# Phase 0 Research: Theme + Design Tokens

No NEEDS CLARIFICATION (clarified 2026-06-08).

## R1 — Deep-merge semantics

**Decision**: `BrandResolver::merge($defaults, $brand)` recurses: for each key in `$brand`, if both sides
are associative arrays, merge recursively; otherwise the brand value replaces the default (scalars and
lists replaced wholesale; unknown keys added). Deepest specified key wins; sibling keys at every level
are preserved (FR-004, SC-003).

**Rationale**: Token trees are nested maps; a true recursive merge is the only way an override of one
nested token leaves siblings intact. Lists (e.g. the full palette array) are replaced, not element-merged,
which matches how a brand redefines a whole palette.

**Alternatives**: `array_merge_recursive` (rejected — duplicates scalars into arrays); shallow
`array_merge` (rejected — loses sibling nesting).

## R2 — brand.json read + malformed handling

**Decision**: `read($path)` returns `[]` if the file is absent; `json_decode` to an array; if decode
fails or the result is not an array, log via `BootLogger` and return `[]` (defaults stand, FR-005).

## R3 — Applying overrides (wp_theme_json_data_theme)

**Decision**: `ThemeServiceProvider::boot()` adds a `wp_theme_json_data_theme` filter that reads the
theme's `brand.json` (path from `config('theme.brand_path')` or the active theme root) and, if non-empty,
`$data->update_with($this->resolver->merge($data->get_data(), $brand))`. WordPress then exposes the merged
tokens as `--wp--preset--*` CSS custom properties (FR-006).

**Rationale**: `wp_theme_json_data_theme` is the canonical, supported hook to amend theme.json data at
runtime — the WP-native way to apply overrides without editing files or recompiling.

## R4 — Style variations

**Decision**: A full alternate style ships as `theme/styles/dark.json` (token-only). WordPress
auto-discovers style variations from the theme's `styles/` folder — no registration code needed (FR-008).

## R5 — Validity + headless tests

**Decision**: Unit-test `merge` (no override, single nested override preserving siblings, unknown-path
add, list replace) and `read` (missing → [], malformed → [] + log). A validity test asserts
`theme/theme.json` and `theme/styles/dark.json` are valid JSON with a v3 `version`. All headless
(FR-007, SC-002, SC-006).

## Summary

| Concern | Choice |
|---|---|
| Merge | recursive; deepest key wins; unknown added; scalars/lists replaced |
| brand.json | read → [] missing; malformed → [] + log |
| Apply | `wp_theme_json_data_theme` filter → update merged data |
| Variations | `theme/styles/*.json`, auto-discovered |
| Tests | merge/read unit + theme.json/variation validity (headless) |

No new Composer dependencies; `config/theme.php` ships `brand_path`.
