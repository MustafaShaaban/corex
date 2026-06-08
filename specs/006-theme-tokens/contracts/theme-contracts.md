# Phase 1 Contracts: Theme + Design Tokens

The stable public API. Signatures are the agreed shape; implementation lives in `tasks.md`.

## C1 — BrandResolver

```php
namespace Corex\Theme;

final class BrandResolver
{
    public function __construct(private \Corex\Support\BootLogger $logger) {}

    /**
     * Deep-merge $brand onto $defaults: assoc arrays merged key-by-key (deepest key wins,
     * siblings preserved, unknown keys added); scalars and lists replaced.
     *
     * @param array<string, mixed> $defaults
     * @param array<string, mixed> $brand
     * @return array<string, mixed>
     */
    public function merge(array $defaults, array $brand): array;

    /**
     * Read + decode a brand.json. Missing → []; malformed → [] (logged).
     *
     * @return array<string, mixed>
     */
    public function read(string $path): array;
}
```

## C2 — ThemeServiceProvider

```php
namespace Corex\Theme;

final class ThemeServiceProvider extends \Corex\Foundation\ServiceProvider
{
    public function register(): void;   // bind BrandResolver
    public function boot(): void;       // add_filter('wp_theme_json_data_theme', …) → merge brand.json
}
```

## C3 — Token files (artifacts)

- `theme/theme.json` — v3 token defaults (`settings.color.palette`, `typography.fontSizes`,
  `spacing.spacingSizes`, layout); the single source of CSS custom properties.
- `theme/styles/dark.json` — a v3 style variation (token-only) WordPress auto-discovers.

## Contract test matrix

| Contract | Asserts | Spec ref |
|---|---|---|
| C1 merge no override | brand `[]` → defaults unchanged | FR-005 |
| C1 merge nested | one nested override changes only that key; siblings intact | FR-004, SC-003 |
| C1 merge unknown path | added; existing tree intact | FR-004 |
| C1 merge list | a list value is replaced wholesale | FR-004 |
| C1 read missing | `[]` | FR-005 |
| C1 read malformed | `[]` + logged | FR-005, SC-004 |
| C3 theme.json valid | valid JSON, v3 `version` | FR-003, SC-002 |
| C3 dark.json valid | valid JSON, v3 `version`, token-only | FR-008, FR-009 |
| Skin | theme registers no CPT / bootstraps no plugin | FR-010, SC-005 |
