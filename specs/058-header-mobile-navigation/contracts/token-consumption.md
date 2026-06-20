# Contract: Token Consumption

Navigation and footer surfaces consume the merged M2 (Spec 057) tokens and add only layout-level custom properties.
Verified by Pest (theme.json shape) and by scanning CSS/patterns for raw literals.

## C1. Consumed M2 tokens (no redefinition)

- **Color** (`--wp--preset--color--*`): `surface`, `surface-alt`, `surface-raised`, `surface-strong`, `ink`,
  `ink-soft`, `inverse`, `border`, `primary`, `primary-dark`, `accent`, `accent-dark`, `overlay`, plus status colors
  where a badge needs them. The transparent-header solid state MUST resolve to a `surface*` background with `ink`/
  `inverse` text meeting WCAG 2.2 AA.
- **Typography** (`--wp--preset--font-family--*`): `heading` for nav/section headings, `body` for links/labels,
  `arabic` for Arabic content, `technical` only where monospace is meaningful. Sizes via `--wp--preset--font-size--*`.
- **Spacing**: `--wp--preset--spacing--*` for padding/gaps.
- **Radius / border / shadow / focus / motion / z**: existing `--wp--custom--{radius,border,motion,focus,z}--*`. The
  mega-menu panel and mobile overlay MUST use `--wp--custom--z--dropdown` / `--wp--custom--z--overlay`; sticky header
  uses `--wp--custom--z--sticky`. Focus rings use `--wp--custom--focus--*`. Transitions use
  `--wp--custom--motion--duration--*` / `--wp--custom--motion--easing--*`.

## C2. Added layout-only custom properties

Added under `theme.json` `settings.custom` (same convention as existing `radius`/`motion`/`focus`/`z`):

| Token (theme.json path) | CSS custom property | Default | Purpose |
|---|---|---|---|
| `custom.header.height` | `--wp--custom--header--height` | `4rem` | base header row height |
| `custom.header.heightCompact` | `--wp--custom--header--height-compact` | `3.25rem` | scrolled/compact header |
| `custom.nav.breakpoint` | `--wp--custom--nav--breakpoint` | `782px` | desktop→mobile switch (aligns with core `overlayMenu:"mobile"`) |

- These are the **only** new tokens. No new color, type, spacing, radius, shadow, or focus tokens.
- The breakpoint value MUST match the point where `core/navigation` `overlayMenu:"mobile"` collapses, so the CoreX
  CSS breakpoint and core's overlay trigger stay consistent.

## C3. No raw literals (Principle V)

- `theme/assets/css/corex-navigation.css` and every `theme/patterns/*.php` MUST NOT contain a raw hex color
  (`#rgb`/`#rrggbb`/`rgb()`/`hsl()` literal), a hard-coded font family name, or a hard-coded font size; all such
  values MUST be `var(--wp--…)` references. (Layout values like `100%`, `0`, `1px` borders via the border token, and
  the breakpoint media query reading `--wp--custom--nav--breakpoint` are permitted.)
- No build-time token system, Tailwind/Bootstrap variable, or component-local palette may be introduced.

## C4. Acceptance (test hooks)

- Pest: `theme.json` contains the three new `custom` tokens with the documented defaults and introduces no new
  color/type/spacing presets for navigation.
- Scan: `corex-navigation.css` and `theme/patterns/*.php` contain zero raw hex/`rgb(`/`hsl(` literals and zero
  hard-coded `font-family`/`font-size` values (CI grep + Pest assertion).
