# Corex Theme

The CoreX FSE block theme — presentation only. It ships templates, template parts,
patterns, and `theme.json` design tokens; all domain logic lives in the plugins and
add-ons (constitution Principle I). Nothing here reads a database or defines business
behavior.

## Templates (`templates/`)

| Template | Covers |
| --- | --- |
| `front-page` | Home |
| `page-about` | About (custom template) |
| `page-services` | Services (custom template) |
| `page-contact` | Contact (custom template) |
| `page-landing` | Landing (custom template) |
| `page-account` | Account — hosts the `corex/account` block (custom template) |
| `page-form` | A single embedded form (custom template) |
| `index` / `archive` | Blog and archives, with a truthful `query-no-results` state |
| `single` | Single post — includes comments and a related-posts row |
| `single-corex_project` / `archive-corex_project` | Portfolio / project |
| `search` | Search results, with a no-results fallback |
| `404` | Not found |

Custom page templates are declared in `theme.json` → `customTemplates`.

## Parts (`parts/`)

`header` and `footer` — the two template-part areas. Header and footer **variants** are
patterns (below) the site owner assigns to these parts.

## Patterns (`patterns/`)

- **Headers** — `header-simple`, `header-corporate` (top bar), `header-saas`
  (mega-menu + search overlay), `header-docs`, `header-transparent`, `header-minimal`,
  and `header-sticky` (solid sticky header with a scrolled-elevation state).
- **Mega menus** — `megamenu-simple`, `megamenu-product`, `megamenu-services`,
  `megamenu-docs` (native `<details>`; JS adds single-open + Escape/outside-close).
- **Footers** — `footer-simple`, `footer-corporate`, `footer-saas`, `footer-legal`,
  `footer-locations`, `footer-newsletter`.
- **Sections** — `section-services-grid`, `section-process-steps`, `section-selected-work`,
  `section-contact-info`, `section-logo-cloud`, `section-newsletter`, plus `project-card`.
- **States** — `maintenance` (mirrors the live `Corex\Admin\StandalonePage` surface) and
  `loading` (an accessible, animation-free skeleton).

Richer composition patterns (`corex/hero`, `corex/features`, `corex/cta`,
`corex/testimonial`, `corex/contact`, `corex/stats`, `corex/faq`, `corex/content-split`,
`corex/section-header`, `corex/news`) are registered by the **Corex UI** add-on.

## Navigation behavior (`assets/js/corex-navigation.js`)

Buildless, framework-free progressive enhancement, loaded only where a CoreX header
renders (Principle VI):

- **Mega menus** — opening one panel closes its siblings; Escape or an outside click
  closes the open panel and returns focus to its summary. Fully usable with no JS.
- **Sticky / transparent headers** — a passive, rAF-throttled scroll listener flips
  `data-corex-header-state="top|scrolled"` on `.corex-header--transparent` and
  `.corex-header--sticky` so CSS can resolve the elevation; reduced-motion is CSS-gated.
- **Search overlay** — a hidden-until-enhanced toggle reveals the search panel, moving
  focus into it; Escape or an outside click closes it and returns focus to the toggle.
  With no JS the panel is a normal inline search form.

## Tokens

All colors, sizes, spacing, radii, shadows, and fonts come from `theme.json` presets and
custom properties. No raw literals ship in CSS (enforced by the token-consumer contract
tests). Layout is RTL-first via logical properties.

## The gutter

The space between what a visitor reads and each edge of the window is the theme's root
padding: `styles.spacing.padding` in `theme.json`, left and right, both
`var(--wp--preset--spacing--40)`. WordPress gives it to every constrained block, so a
template, a part or a pattern gets it by being constrained and writes no side padding of
its own. A block set to full width runs to both edges and keeps the gutter for what is
inside it.

Two rules follow:

- A template that prints a post's content prints it full width
  (`<!-- wp:post-content {"align":"full","layout":{"type":"constrained"}} /-->`). Inside
  the constrained `<main>` a content block that is not full width holds a full-width block
  in it to the text column. `tests/Unit/Theme/ThemePageCoverageTest.php` holds this for
  every template.
- A full-width section placed inside a block that is neither full width nor constrained
  keeps the gutter as padding and stands out of line with the text around it. Make the
  blocks around it full width, as `templates/search.html` does for what it shows when
  nothing is found.

`tests/e2e/theme-gutter.spec.js` measures every template and every registered pattern at
375 and 320 pixels, left to right and right to left.
