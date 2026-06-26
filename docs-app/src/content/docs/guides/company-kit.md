---
title: Company Site Kit v1
description: Apply a neutral, brand-aware company website — full page coverage, demo levels, and safe preview/apply — from CoreX patterns, without a page builder.
---

The **Company Site Kit v1** (milestone M4) turns the CoreX foundations into a complete, brand-aware company website
without a page builder. It is the `corex-kit-company` add-on's `CompanyBlueprint`, composed from the M2 brand tokens,
the M3 header/footer, and the registered `corex/*` section patterns, and applied through CoreX's existing
provisioning (preview → apply with safe conflict handling).

## Page coverage

Content pages created by the kit: **Home** (front), About, Services, Single Service, Work, Case Study, Industries,
FAQ, Blog, Team, Testimonials, Locations, Contact, Privacy Policy, Terms, Cookie Policy, Maintenance. System surfaces
— Search Results, No Results, 404, single, and archive — are served by the theme's universal FSE templates, not
duplicated as pages.

Each page composes only registered patterns (`corex/hero`, `features`, `cta`, `testimonial`, `contact`, `faq`,
`news`, `stats`, `content-split`, `section-header`) plus core blocks, is token-only, RTL-correct, and accessible.
Pages whose dedicated section block does not exist yet (services grid, team grid, case-study/project grid,
locations/map) reuse an existing pattern for now; those blocks are the recorded M5 batch.

## Demo levels

Choose a demo content level when applying: `minimal`, `standard` (default), or `full`. All levels produce the **same
page set and section order**; only the home page's optional example sections vary in depth.

## Safe preview / apply

Applying always shows a **summary** of what will be created or changed before any mutation (`ApplyPreview`). Existing
pages are handled by an explicit disposition — `reset`, `adopt`, `skip`, or `conflict` (`PageDisposition`) — so a
re-run or an existing site is never silently overwritten. Apply is idempotent under `skip`/`adopt`.

## Brand-aware setup & SEO

Brand setup (site name, tagline, logo, brand color/typography) maps onto the M2 tokens / per-site `brand.json` — it
personalizes the site without hardcoding a client brand into CoreX. Each content page carries editable SEO starter
metadata (title/description) that common SEO plugins can read and override; CoreX requires no SEO plugin.

## Starting a real site

`wp corex make:site "<CompanyName>"` scaffolds an isolated client plugin + theme + governance (see
[Build a client site](/guides/client-site/)). The company kit then provides the page coverage above. Bridging the
scaffolded client theme to automatically inherit the M2 tokens and M3 nav/footer is a tracked follow-up
(`specs/059-company-site-kit/make-site-verification.md`).

## What each layer owns

It is easy to confuse CoreX UI, the Company Kit, the parent theme, and the generated client site. Each owns a
distinct thing:

| Layer | Provides | Does **not** |
|---|---|---|
| **CoreX UI** (`corex-ui`) | Reusable server-rendered blocks, section patterns, and block styles. | Create pages; own the final site header/footer; lock in brand or layout. |
| **Company Kit** (`corex-kit-company`) | A starter company blueprint — creates/adopts starter pages and a front page composed from CoreX UI patterns. | Replace your final design; it is a starting point you adopt and edit. |
| **CoreX parent theme** | Default FSE templates and template parts (header/footer) — safe defaults. | Hold one client's bespoke structure. |
| **Generated client site** (`make:site`) | The client's final brand, custom header/footer structure, page layouts, assets, and content. | — this is where client-specific work belongs. |

### Header and footer customization

- The default CoreX header/footer are **safe starting points**.
- **Brand restyling** (colours, fonts, spacing) is usually a tokens/style-variation change — **no framework
  template edits are needed**.
- **Structural changes** (a different header layout, extra footer regions) belong in the **generated client
  theme's** template parts — e.g. `sites/acme/acme-theme/parts/header.html` and
  `sites/acme/acme-theme/parts/footer.html` — which override the parent theme's parts.
- **Do not edit CoreX framework internals for one client.** No framework template edits are needed for normal
  brand restyling; client theme template-part overrides are expected when the approved client design changes
  structure.

## Common company-site pages

The kit's page coverage maps to what a typical company site needs:

- **Core pages:** Home, About, Contact.
- **System templates** (served by the theme, not duplicated as pages): 404 / Page not found, Search results,
  the Page template, the Single post template, and the Archive template when a blog/news section is enabled.
- **Legal placeholder pages:** Privacy Policy, Terms, and Cookie Policy. These are **placeholders only** —
  CoreX does not provide final legal text or legal advice. Replace the placeholder content with copy reviewed by
  a qualified professional before launch, and prefer WordPress's native Privacy Policy tooling where it fits.
  Footer legal links are added only when the corresponding pages exist. Apply/reset stays idempotent and safe.

## Out of scope

No page builder; no Portfolio (M8) or WooCommerce (M9) kit; no broad M5 block library (only the gaps this kit
proves); no Pro features. The kit never hard-depends on an optional plugin.
