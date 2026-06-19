# M2 Brand Foundation Handoff

**Status:** Approved

**Approved:** 2026-06-19

**Engineering target:** Spec 057 - Brand Tokens and Logo System

## Approval evidence

The product owner approved this direction for M2 in the 2026-06-19 planning request. This handoff records that
direction inside the repository; it does not authorize implementation without the reviewed engineering spec.

## Approved direction

- Dark-first CoreX product identity with a disciplined, premium developer/product character.
- Brass/gold as the restrained accent direction.
- Product line: “Discipline, at every layer.”
- A geometric Core X logo direction with a symbol, wordmark, lockup, and monochrome usage system.
- Space Grotesk for display and heading roles.
- JetBrains Mono for code and technical-interface roles.
- IBM Plex Sans Arabic for Arabic and RTL roles.
- Accessible dark and light behavior built from semantic runtime tokens.
- LTR, RTL, and mixed-script support from the same token contract.
- WordPress-native, FSE-compatible output that remains brandable for client websites.
- Calm wp-admin alignment: shared visual primitives without a decorative dashboard redesign.
- Future-safe status and edition naming without implementing Pro or commercial interfaces.

## Scope

- Define the CoreX logo family, clear-space/minimum-size guidance, contrast variants, and accessible text alternatives.
- Reconcile color, typography, spacing, radius, shadow/elevation, border, and focus-ring tokens.
- Define semantic dark and light roles rather than component-specific color values.
- Align the theme token source, generated custom properties, style variations, admin product base, and block styles.
- Define font loading, fallback, Arabic, RTL, and mixed-script behavior.
- Document contrast, focus visibility, responsive typography, reduced-motion boundaries, and client-brand overrides.

## Explicit exclusions

- Full admin dashboard redesign.
- Header, mobile navigation, mega-menu, footer, or template-part redesign.
- Company, Portfolio, or WooCommerce kit expansion.
- New blocks or component redesign beyond token alignment.
- Forms, documentation-site, or marketing-site redesign.
- Pro licensing, entitlement, or commercial product UI.
- Front-office editor workspace or a heavy animation system.

## Token contract

`theme.json` remains the canonical runtime token source. Existing WordPress preset/custom-property mechanisms and
per-site `brand.json` overrides remain authoritative. Implementation must reconcile existing slugs before adding
tokens and must not introduce a parallel CoreX token registry, build-time token source, or component-local palette.

The system must distinguish:

- primitive values used to construct the scale;
- semantic roles consumed by interfaces and blocks;
- component usage, which references semantic roles rather than owning new brand values.

Admin screens may use documented WordPress admin fallbacks when theme tokens are unavailable, but those fallbacks
must map to the same semantic roles and remain visually restrained.

## Typography and RTL

- Space Grotesk covers Latin display and heading roles, with system fallbacks.
- JetBrains Mono is limited to code, commands, identifiers, metrics, and technical labels where monospace improves
  comprehension.
- IBM Plex Sans Arabic covers Arabic content and interface roles; Arabic is not forced through the Latin display
  face.
- Direction follows content and document context. Layout styling uses logical properties.
- Mixed Arabic/Latin strings preserve readable shaping, numerals, code fragments, and bidirectional isolation.
- Font subsets and weights must be bounded to those actually used, with system fallbacks preventing invisible text.

## Dark and light behavior

Dark is the CoreX product default, but light mode must be a complete semantic mapping rather than an inverted or
partially overridden palette. Both modes must define surfaces, text hierarchy, borders, interactive states, status
colors, overlays, shadows, and focus rings. Client themes may select or override a mode without inheriting a
hardcoded CoreX product identity.

## Accessibility

- Text and interactive states meet WCAG 2.2 AA contrast requirements in both modes.
- Focus indicators remain visible against every supported surface and do not rely on color alone.
- Status meaning uses text or iconography in addition to color.
- Logo variants retain legibility at documented minimum sizes and have suitable accessible-name guidance.
- Typography supports zoom, text resizing, long labels, Arabic shaping, and content expansion without clipping.
- Motion is not part of this handoff beyond preserving reduced-motion behavior for existing interfaces.

## Responsive behavior

- Typography scales remain fluid but bounded, preserving readable line length and hierarchy from mobile through
  wide layouts.
- Logo lockups define compact and full variants without relying on viewport-specific raster assets.
- Token changes must not introduce horizontal scrolling or fixed physical-direction assumptions.

## Performance

- Prefer optimized, self-hosted font assets with only required weights and scripts.
- Prevent invisible-text blocking through appropriate fallbacks and loading behavior.
- Use reusable SVG logo assets; do not ship a raster set for each size or mode.
- Do not add a client-side theme framework, global JavaScript, icon font, or build-time token dependency.

## Clarified implementation contract

Spec 057 clarification established these planning constraints:

- retain stable token slugs, add missing semantic roles, alias legacy references, and keep deprecations for at least
  one minor release until no first-party consumer remains;
- require an owner-approved production vector package with provenance, treating the existing navy/cyan SVG only as
  migration evidence;
- keep Latin body/interface text on the system stack and ship at most four self-hosted WOFF2 files for the approved
  Space Grotesk, JetBrains Mono, and IBM Plex Sans Arabic roles;
- use a scoped `--corex-admin-*` adapter with centralized WordPress admin fallbacks rather than loading front-end
  theme tokens into wp-admin; and
- preserve associative-map merging and wholesale list replacement in `brand.json`, with complete preset arrays,
  validation, compatibility fixtures, and migration guidance.

The implementation plan must turn these constraints into an inventory and task sequence without broadening visual
scope.
