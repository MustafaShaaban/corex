# M4 Company Site Kit v1 Handoff

**Status:** Approved

**Approved:** 2026-06-20

**Engineering target:** Spec 059 - Company Site Kit v1 Structure and Page Coverage

## Approval evidence

The product owner directed completion of the company-site readiness path through the first real `wp corex make:site`,
and approved authoring structural/behavioral design handoffs from the roadmap scope where no external design package
exists (the pattern established for the M3 navigation/footer handoff). This handoff records that direction inside the
repository. No external company-kit visual package exists and none is invented: this handoff is **structural,
behavioral, and content-architecture** only — it composes the approved M2 brand tokens (Spec 057) and the M3
navigation/footer system (Spec 058) into a neutral, brand-aware company kit. It is an input to the engineering spec
and does not authorize implementation without the reviewed Spec 059.

## Approved direction

- A neutral, brand-aware **Company Site Kit v1** that can launch the first real Corex company websites without a page
  builder.
- All visuals come from M2 tokens; navigation/footer come from M3 template parts/patterns; sections reuse existing
  CoreX blocks and patterns (e.g. `corex/hero`, `corex/contact`) and WordPress core blocks first.
- Brand-aware setup fields that personalize a site **without** embedding any client brand into Corex itself.
- An explicit **preview → apply** kit flow with a summary before any mutation, and safe handling of existing content.

## Scope — page coverage (v1)

Templates and/or page patterns for: Home, About, Services, Single Service, Case Studies / Work, Single Case Study,
Industries, FAQ, Blog / News, Single Post, Team, Testimonials, Locations / Branches, Contact, Privacy Policy, Terms,
Cookie Policy, Search Results, No Results, 404, Maintenance.

Pages reuse the M3 header/footer parts and compose existing CoreX/core blocks; each page is token-only, RTL-correct,
responsive, and accessible.

## Scope — kit behavior

- **Preview/apply UX:** an explicit summary of what will be created/changed before mutation; nothing mutates without
  confirmation.
- **Demo content levels:** `minimal`, `standard`, `full` — the same structure with increasing example content.
- **Safe content handling:** defined `reset`, `adopt`, `skip`, and `conflict` behavior for existing pages/menus so a
  re-run or an existing site is never silently clobbered.
- **Brand-aware setup:** site name, tagline, logo, primary contact, and brand color/typography selection map onto M2
  tokens / `brand.json`, never hardcoded into the framework.
- **SEO starter metadata:** titles/descriptions/Open Graph defaults that remain editable and compatible with common
  SEO plugins (no hard dependency).

## Interaction, accessibility, responsive, RTL

- WCAG 2.2 AA: headings/landmarks per page, visible focus (M2 focus tokens), accessible names, no color-only meaning.
- Responsive: fluid layouts, no horizontal scroll at 320px, usable at 200% zoom, content-overflow safe.
- RTL: logical properties throughout; Arabic typography via the M2 Arabic role; mirrored layout.
- Reduced motion: any motion respects `prefers-reduced-motion`.
- No-JS: pages are server-rendered and usable without JavaScript.

## Reuse and constraints

- Reuse M3 navigation/footer and approved CoreX blocks/patterns; prefer WordPress core blocks/patterns when they
  satisfy the requirement. Do not introduce a parallel token registry or a page builder.
- No optional plugin (ACF/Woo/Polylang/WPML) as a hard dependency (Principle IX).
- The kit applies through the existing CoreX kit/provisioning foundations; it must not edit framework internals to
  function.

## Explicit exclusions

- The Portfolio (M8) and WooCommerce (M9) kits — only the Company kit is in scope.
- A page builder, drag/drop editor, or header/mega-menu builder.
- M5 broad block library — only the specific blocks proven necessary by this kit (selected in M5) are added.
- Pro/commercial features, licensing, white-label, and admin-builder/editor-workspace scope.
- A full SEO engine, analytics, or marketing automation.

## Open questions

- Which pages ship as FSE **templates** vs. inserted **page patterns** vs. created **pages** at apply time (planning
  to decide per page; default: reusable patterns + a small set of templates, pages created at apply).
- The minimal set of net-new section blocks M4 actually needs (feeds the M5 batch); default: reuse `corex/hero`,
  `corex/contact`, core blocks, and M3 parts first, and record gaps rather than pre-building M5.
