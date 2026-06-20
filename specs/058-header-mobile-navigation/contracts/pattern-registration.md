# Contract: Pattern & Asset Registration

Defines how header/footer/mega-menu variants are registered and how their behavior assets load. Verified by Pest
(registration + conditional enqueue) and by inspecting rendered output.

## C1. Pattern category

- A CoreX block-pattern category MUST be registered: slug `corex` (label "CoreX", translatable).
- Registration MUST occur in the plugin (`Corex\Theme\NavigationServiceProvider`), never in the theme.
- The category MUST register on a core hook available in all contexts (`init`), independent of the active theme.

## C2. Patterns

- Each variant MUST be a file `theme/patterns/<name>.php` with a valid pattern header: `Title`, `Slug`
  (`corex/<name>`), `Categories`, optionally `Block Types`, `Description`.
- Patterns MUST be discoverable through WordPress core's block-theme pattern auto-registration (no per-pattern PHP
  `register_block_pattern` call).
- Header patterns MUST include the core `header` category (and `corex`); footer patterns the core `footer` category
  (and `corex`); mega-menu patterns the `corex` category.
- Required pattern slugs (initial set):
  - Header: `corex/header-simple`, `corex/header-corporate`, `corex/header-saas`, `corex/header-docs`,
    `corex/header-transparent`, `corex/header-minimal`.
  - Mega menu: `corex/megamenu-simple`, `corex/megamenu-services`, `corex/megamenu-product`, `corex/megamenu-docs`.
  - Footer: `corex/footer-simple`, `corex/footer-corporate`, `corex/footer-saas`, `corex/footer-newsletter`,
    `corex/footer-locations`, `corex/footer-legal`.
- Each pattern MUST contain only core blocks (and existing CoreX blocks); MUST NOT contain raw hex/size/font; MUST
  wrap visible strings in `corex` text-domain i18n functions; MUST use logical CSS in any inline style.

## C3. Conditional behavior assets (Principle VI)

- The presentation stylesheet (`theme/assets/css/corex-navigation.css`) MUST be registered and attached so it loads
  **only** where a navigation header/footer renders — via `wp_enqueue_block_style('core/navigation', …)` and/or
  block-style attachment — never on `wp_enqueue_scripts` globally.
- The behavior script (`theme/assets/js/corex-navigation.js`) MUST be registered and enqueued only on the surface
  that renders a CoreX header needing it (render-scoped), never globally.
- Both assets MUST be versioned with the plugin/theme version and MUST have no third-party dependency.
- With the script absent or failed, the markup MUST remain fully usable (no destination lost; menus operable via
  core nav + native disclosure).

## C4. Template parts

- `theme.json` `templateParts` MUST continue to declare `header` (area `header`) and `footer` (area `footer`).
- `theme/parts/header.html` MUST render the simple-company composition (brand + `core/navigation` with
  `overlayMenu` + CTA). `theme/parts/footer.html` MUST render the simple footer (regions + legal row).

## C5. Acceptance (test hooks)

- Pest: the `corex` pattern category is registered; each required pattern slug is registered after theme pattern
  registration runs; the nav CSS/JS handles are registered; neither is enqueued on a page with no nav.
- Pest/markup: each pattern parses as valid block markup and contains no raw hex (`#rrggbb`) or hard-coded
  `px` font sizes.
