# Phase 1 Data Model: Header, Mobile Navigation, Mega Menu, and Footer System

This feature has **no database, options, or custom post types**. The "entities" are file-based presentation
artifacts (template parts, patterns) and the structural shapes they compose. This document defines those shapes and
their relationships so the contracts and tasks stay consistent.

## Entities

### Header template part

- **Represents**: the site header region (FSE `area: header`).
- **Source**: `theme/parts/header.html` (default markup) + declared in `theme.json` `templateParts`.
- **Composition**: a brand slot, a primary navigation region, and an actions area (zero or more action slots).
- **Variants**: delivered as block patterns (see Pattern), not as separate parts. The default part renders the
  simple-company composition.
- **Relationships**: contains exactly one Navigation menu; references the M2 logo via the brand slot.

### Footer template part

- **Represents**: the site footer region (FSE `area: footer`), a `contentinfo` landmark.
- **Source**: `theme/parts/footer.html` (default markup) + declared in `theme.json` `templateParts`.
- **Composition**: zero or more footer column/region groups followed by exactly one legal/utility row.
- **Variants**: delivered as block patterns.

### Pattern (per variant)

- **Represents**: a registered block pattern that composes core blocks + M2 tokens into one header/footer/mega-menu
  variant.
- **Source**: `theme/patterns/<name>.php` (auto-registered by WordPress for block themes).
- **Fields** (pattern file header): `Title`, `Slug` (`corex/<name>`), `Categories` (CoreX category + a core category
  such as `header`/`footer`/`call-to-action`), `Block Types` (e.g. `core/template-part/header` for header patterns),
  `Description`.
- **Invariants**: contains only core blocks (+ existing CoreX blocks like `corex/copyright`); no raw hex/size/font;
  all visible strings translatable; logical CSS only.

### Navigation menu

- **Represents**: the ordered set of navigation destinations shared by desktop nav, mega menus, and the mobile menu.
- **Source**: a `core/navigation` block instance (the site's navigation menu), not owned by this feature's data.
- **Structure**: top-level items; an item may be a plain link, a simple submenu, or a **mega-menu trigger** owning a
  Mega-menu panel.
- **Invariant**: every destination is reachable as a link on every surface (desktop, mega, mobile) — no
  hover-only destinations.

### Mega-menu item

- **Represents**: one entry inside a mega-menu panel.
- **Fields** (all optional except link): `icon` (decorative SVG/emoji slot), `title` (text), `description` (text),
  `badge` (short label), `link` (URL/anchor — required), `featuredCard` (optional promo block), `cta` (optional
  button).
- **Rendering**: a list of grouped items; on desktop a multi-column panel, on mobile an accordion section.

### Action slot

- **Represents**: an optional, structural placeholder in the header actions area.
- **Types**: `search` (overlay trigger), `language` (switcher placeholder), `cta` (primary button), `account`
  (placeholder), `cart` (placeholder).
- **Invariant**: structural only — no business logic, no optional-plugin calls; each is an accessible, labelled
  control or link.

## Tokens (consumed, not owned)

- **From M2 (Spec 057)**: `--wp--preset--color--*`, `--wp--preset--font-family--*`, `--wp--preset--spacing--*`, and
  existing `--wp--custom--{radius,border,motion,focus,z}--*`. Consumed, never redefined.
- **Added by this feature (layout-only)**: `--wp--custom--header--height`, `--wp--custom--header--height-compact`,
  `--wp--custom--nav--breakpoint`. See [contracts/token-consumption.md](./contracts/token-consumption.md).

## State (behavioral, not persisted)

- **Header scroll state**: `top | scrolled` (attribute on the header), drives the transparent→solid swap. Ephemeral,
  client-side only.
- **Disclosure state**: `expanded | collapsed` per mega-menu trigger and per mobile accordion section, reflected via
  `aria-expanded`. Ephemeral, client-side only.

No state is stored server-side; nothing is written to the database.
