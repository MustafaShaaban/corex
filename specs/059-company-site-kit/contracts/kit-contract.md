# Contract: Company Site Kit v1

Testable contracts for Spec 059. Verified by Pest (blueprint manifest + page markup), the existing provisioning
tests (apply/conflict), and ENV-gated browser checks.

## C1. Page coverage

- `CompanyBlueprint` MUST provide content pages for the v1 set: Home (front), About, Services, Single Service, Case
  Studies/Work, Single Case Study, Industries, FAQ, Blog/News, Team, Testimonials, Locations/Branches, Contact,
  Privacy Policy, Terms, Cookie Policy, Maintenance. (System surfaces — Search Results, No Results, 404,
  single/archive — are owned by the universal templates the kit already declares.)
- Exactly one page MUST be marked `front`.
- Every page slug MUST be unique.

## C2. Composition & tokens

- Each page's content MUST compose only registered `corex/*` patterns (`hero, features, cta, testimonial, contact,
  faq, news, stats, content-split, section-header`), existing core blocks, and/or M3 nav-footer — no references to
  unregistered patterns.
- Page markup MUST contain no raw hex color or hard-coded `px`/font literals (token-only, Principle V).
- Visible strings MUST use the `corex` text domain; dynamic output MUST be escaped.

## C3. Demo levels

- `minimal`, `standard`, `full` MUST yield the **same page set and section order**; only example-content depth
  differs. `standard` is the default.

## C4. Apply safety (reuses core provisioning)

- Applying MUST produce an `ApplyPreview` summary before any mutation (FR-001).
- Existing slugs MUST be handled by `PageDisposition` (`reset|adopt|skip|conflict`) — never silently overwritten
  (FR-006).
- Re-applying MUST be idempotent under `skip`/`adopt`.

## C5. SEO starter

- Each content page MAY carry editable SEO starter fields (title/description/OG) applied as standard meta/title
  defaults that common SEO plugins read and override; no plugin dependency (FR-008, Principle IX).

## C6. Accessibility / RTL / responsive

- Pages MUST have correct landmarks/heading order, meet WCAG 2.2 AA via M2 tokens, mirror in RTL (logical
  properties), and avoid horizontal scroll at 320px / clipping at 200% zoom (browser-verified where available, else
  ENVIRONMENT-GATED).

## C7. Scope

- M4 MUST NOT add a page builder, a new broad block library (M5 selects only proven gaps), Portfolio/Woo kits, or Pro
  features; MUST NOT hard-depend on any optional plugin.

## Acceptance (test hooks)

- Pest: blueprint exposes the full v1 page set; one `front`; unique slugs; page markup references only registered
  patterns and contains no raw hex/px/font; demo levels produce identical structure; SEO fields (where present) are
  well-formed.
- Reuse existing provisioning tests for preview/apply/PageDisposition.
