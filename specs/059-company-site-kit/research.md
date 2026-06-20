# Phase 0 Research: Company Site Kit v1

Decisions resolving the plan's unknowns. Format: Decision · Rationale · Alternatives.

## R1. Reuse vs. rebuild the provisioning / conflict layer

**Decision**: Reuse `corex-core`'s `Corex\Provisioning\*` — `KitProvisioner` (apply), `ApplyPreview` (summary
before mutation), `ApplyOutcome`, `KitSummary`, and `PageDisposition` (reset/adopt/skip/conflict). M4 adds **no new
provisioning engine**; it only supplies richer blueprint data and any thin SEO/demo wiring.
**Rationale**: the preview/apply/conflict behavior (FR-001/FR-006) already exists and is tested; rebuilding it would
duplicate knowledge (DRY) and risk regressions.
**Alternatives**: a new company-specific apply flow — rejected (YAGNI, duplicates the core layer).

## R2. How demo levels attach without duplicating structure

**Decision**: Represent each page's content as a base structure plus level-scoped content depth, so `minimal`,
`standard`, and `full` produce the **same page set and section order** with increasing example content. Implement via
a blueprint method that takes the level and returns the page list; the section composition is shared, only the
inner example content varies.
**Rationale**: FR-005 requires identical structure across levels; a single structure with leveled content keeps
parity guaranteed and avoids three divergent copies (the wrong-abstraction trap if copied).
**Alternatives**: three separate page lists — rejected (drift risk, triples maintenance).

## R3. SEO starter representation

**Decision**: Each page definition carries optional editable SEO starter fields (title, description, and an OG
default) applied as standard post meta / document title defaults that common SEO plugins read and override. No SEO
engine, no plugin dependency.
**Rationale**: FR-008 wants editable, plugin-compatible defaults, not a hard SEO dependency (Principle IX).
**Alternatives**: bundling an SEO plugin or a custom meta UI — rejected (scope, coupling).

## R4. Pages vs. existing universal templates

**Decision**: The universal FSE templates already shipped by the theme/kit (`front-page`, `page`, `single`,
`archive`, `search`, `404`, `index`) cover the **system surfaces** (Search Results, No Results, 404, single/archive).
M4 adds the **content pages** as blueprint `pages()`: Home, About, Services, Single Service (pattern/page), Case
Studies/Work, Single Case Study, Industries, FAQ, Blog/News landing, Team, Testimonials, Locations/Branches,
Contact, Privacy Policy, Terms, Cookie Policy, Maintenance.
**Rationale**: avoids duplicating template-level surfaces as pages; matches how WordPress resolves system views.
**Alternatives**: creating pages for 404/search — rejected (templates own those).

## R5. Composition + the minimal M5 block gaps M4 surfaces

**Decision**: Compose pages from the confirmed `corex/*` patterns (`hero, features, cta, testimonial, contact, faq,
news, stats, content-split, section-header`), the M3 nav/footer, and core blocks. Where no pattern fits, compose
`corex/section-header` + core blocks now and **record the gap** for M5 rather than building new blocks here.
**Surfaced M5 gaps (record, don't build)**: services grid + single service, team grid, logo cloud/trust, case-study/
project grid + single, locations/map section, pricing. (These match ROADMAP §8 candidates.)
**Rationale**: the handoff says reuse first and record gaps; building new blocks now is M5 scope (YAGNI for M4).
**Alternatives**: building the missing section blocks inside M4 — rejected (scope creep into M5).

## Cross-cutting

- **i18n**: all page titles/content strings use the `corex` text domain; escaped where dynamic.
- **a11y/RTL**: inherited from M2 tokens + M3 parts + the token-only patterns; pages add correct heading order and
  landmarks; logical properties throughout.
- **Environment gating**: rendered/wp-env apply + browser a11y evidence recorded ENVIRONMENT-GATED where Docker/
  browser runtime is unavailable, never PASS.
