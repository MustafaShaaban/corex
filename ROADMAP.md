# Corex Product and Engineering Roadmap

This roadmap is the durable, owner-friendly view of where Corex is, what must happen next, and what is intentionally deferred. It tracks milestones and dependencies rather than repeating completed Spec Kit history.

## 1. Roadmap purpose

Corex uses distinct documents for distinct planning needs:

- `ROADMAP.md` is the durable product and engineering roadmap: completed foundation, active milestones, dependencies, priorities, and future boundaries.
- `PROGRESS.md` is the immediate session/resume file: the latest verified state and one recommended next action.
- `CHANGELOG.md` records actual released and unreleased product changes, not plans.
- `DECISIONS.md` records important architectural and product decisions and their rationale, not task status.
- `specs/` contains reviewed implementation contracts. A roadmap milestone is not authorization to implement it.
- `design/` contains the separate design roadmap, inventory, and approved design-to-engineering handoffs.

Approved design work moves from design inventory to a focused handoff, then to an engineering spec. Design exploration is not implementation scope by itself.

## Roadmap at a glance

| Milestone | Status | Priority | Main dependency |
|---|---|---|---|
| M0 - Stabilization, Security, and Release Hygiene | Closed in v0.27.0 | Complete | Environment-gated wp-env/browser/deployment evidence remains follow-up verification |
| M1 - Design Inventory and Design-to-Engineering Pipeline | Current design package frozen; handoff intake active | High | Approved Claude Design inventory and handoffs |
| M2 - CoreX Brand Tokens and Visual Foundation | Closed: Spec 057 (T001-T090) merged via PR #54 (`f9994f8`); env-gated wp-env/browser evidence remains follow-up | Complete | Done; remaining follow-up is env-gated wp-env/browser evidence and an owner release/version decision |
| M3 - Header, Mobile Navigation, Mega Menu, and Footer System | Design needed | High | M1, M2 |
| M4 - Full Company Site Kit v1 | Planned | High | M0, M2, M3; selected M5 blocks |
| M5 - Blocks and Components Expansion | Planned in batches | High | M1 and approved component handoffs |
| M6 - CoreX Admin Product Experience | Planned | Medium-high | M2 and stable admin contracts |
| M7 - Forms and Email Experience | Planned | Medium-high | M2 and existing forms/mail foundations |
| M8 - Portfolio Kit Completion | Planned | Medium | M2, M3, reusable M5 blocks |
| M9 - WooCommerce Kit Completion | Waiting for Woo design and stable gating | Medium | M0, M2, M3, WooCommerce gating |
| M10 - Docs and Marketing Productization | Later; before public/commercial launch | Medium | Stable product surfaces and visual system |
| M11 - Pro and Commercial Layer | Future | Low until Core/Core kits are stable | Stable Free/Core product |

## Current focus and execution order

- **Done:** the core framework foundations, stable-client readiness work, Spec 056 dependency/security remediation,
  CI and CodeQL verification, GitHub branch-protection review, and the repository-side design inventory/handoff
  structure.
- **Active now:** M2 — Spec 057 is **implementation-complete (T001-T090)**: canonical tokens, accessible
  modes/typography/RTL, the four-file font package, the approved Core X logo package, the brand-override validator,
  the scoped `--corex-admin-*` admin adapter, and the design-system/branding documentation. Full `composer test`
  661 pass, `test:js` 97 pass, build + docs-app build + `verify:dependencies` PASS. M0 remains closed (v0.27.0).
- **Next:** PR #54 review (it is ready to mark ready-for-review); collect the env-gated wp-env/browser evidence
  when Docker + a compatible browser runtime are available. No asset or code blockers remain.
- **Blocked:** M3 cannot enter engineering without an approved navigation handoff and the reviewed M2 token
  contract. M4 cannot start until the minimum M2/M3 foundations and selected M5 components are ready.
- **Not authorized:** roadmap presence does not authorize implementation, Pro work, builders, or bulk spec creation.

Before the first real company websites, CoreX requires the M0 release, an approved and implementable M2 visual
foundation, reusable M3 navigation/template parts, the complete M4 company-page contract, and only the M5 component
batches proven necessary by that kit.

## Company Website Start Track — June 21–22, 2026

CoreX v0.27.0 is stable enough to begin first company-site project planning, content architecture, local setup,
brand gathering, and implementation preparation. Full launch readiness for those sites still depends on completing
the minimum company-site path: M2 brand tokens and visual foundation; M3 header/mobile navigation/footer/template
parts; M4 Company Site Kit v1; and selected M5 blocks required by M4.

CoreX is not yet fully finished or public/commercial-launch ready. M6-M11 are later productization, future, and
commercial scope unless the active company project demonstrates a specific dependency. Their open status does not
block all first-client preparation or require unrelated scope to move into M2-M5.

## 2. Current foundation status

The repository contains substantial implemented foundations. This is a high-level planning summary, not a release certification.

| Foundation | Current status |
|---|---|
| Core framework | Exists: boot, container, services/repositories, events, security, and support layers are present. The M0 release baseline is v0.27.0. |
| Data layer | Exists: models, fields, query/data tooling, and data-management foundations are present. Advanced workflows remain separate scope. |
| CLI / `make:site` | Exists with scaffold and readiness validation. Verify generated sites in real client use. |
| Block engine | Exists with discovery and conditional-asset foundations. New visual blocks remain M5 scope. |
| Forms | Exists as a framework package. Complete visitor/admin states and email presentation in M7. |
| Config/admin foundation | Exists. Product-level visual consistency and full state coverage remain M6 scope. |
| Add-ons architecture | Exists with optional add-on packages and dependency metadata. Continue validating packaging and disabled-state safety. |
| Runtime add-on gating | Implemented under stable-client readiness and included in v0.27.0. Continue regression verification, especially WooCommerce absence/inactive cases. |
| Company / Portfolio / Woo kits | Foundations exist. They are not yet equivalent to the complete page coverage in M4, M8, and M9. |
| Design-system / DLS | A substantial token, component, pattern, and documentation foundation exists. Final CoreX identity and approved external design intake remain M1/M2. |
| Docs | In-repo and published-docs foundations exist. Productization and marketing surfaces remain M10. |
| Readiness checks | Exist and cover multiple release categories. Local readiness passed for Spec 056; Docker/wp-env, browser automation, and deployment-profile evidence remain environment-gated. |
| Free/Pro boundary matrix | Exists and protects adoption/security basics in Free/Core. Commercial implementation remains M11. |
| Tests and release workflow | Broad Pest/Jest/build/readiness coverage exists. Dependency policy, CI, CodeQL, branch protection, required CI, Dependabot security updates, and secret scanning were verified during Spec 056 delivery. The clean v0.27.0 release is published; environment-dependent E2E evidence remains explicit follow-up verification. |

The current release baseline and exact verification counts belong in `PROGRESS.md` and `CHANGELOG.md`, not here.

## 3. M0 - Stabilization, Security, and Release Hygiene

**Status:** Closed in v0.27.0 on 2026-06-19.
**Outcome:** A clean, evidence-backed post-readiness release suitable for the first client work.

- **Complete:** Dependabot/security triage and Spec 056 exposure-aware dependency remediation.
- **Complete:** current vulnerability findings are either resolved or explicitly classified with bounded,
  fail-closed policy entries and review triggers.
- **Complete:** GitHub branch protection, the required CI context, Dependabot security updates, and secret scanning
  were verified after Spec 056 merged.
- **Complete:** CI, CodeQL, dependency-security validation, full headless tests, builds, docs, and local readiness
  passed for the merged remediation.
- **Complete:** the v0.27.0 release commit was merged through PR #52, required CI and CodeQL passed, the annotated
  tag was pushed, and the GitHub release was published and verified against merge commit `a9abdcb`.
- **Environment-gated:** run wp-env and browser E2E when Docker and a compatible browser-automation Node runtime are
  available; unavailable checks must remain explicit rather than being reported as passing.

**Blocked or environment-dependent:** available Docker/wp-env runtime, browser automation, and external deployment
profiles. These do not reopen M0 or completed Spec 056 work, but they remain explicit readiness evidence gaps to
collect when those environments are available.

## 4. M1 - Design Inventory and Design-to-Engineering Pipeline

**Status:** Repository structure complete; current external design package reported frozen after final closure;
focused handoff intake remains active.
**Outcome:** Design exploration stays separate from engineering while approved work becomes implementable in controlled increments.

- Treat Claude Design as the source for design exploration, not as repository implementation authority.
- Maintain a design inventory covering foundations, navigation, blocks, kits, admin UI, forms/email, docs, marketing, responsive behavior, RTL, accessibility, states, and performance notes.
- Keep the design roadmap under `design/` separate from this engineering roadmap.
- Maintain `design/ROADMAP.md`, `design/INVENTORY.md`, and focused documents in `design/handoffs/`.
- Convert only approved, implementation-ready design areas into handoffs and then engineering specs.
- Do not implement every design idea immediately or create detailed specs for the entire inventory at once.

Claude Design remains outside the repository. Its explorations become engineering inputs only after an approval
record is captured in the design inventory and a focused handoff defines responsive behavior, states,
accessibility, RTL, performance constraints, and implementation boundaries.

## 5. M2 - CoreX Brand Tokens and Visual Foundation

**Status:** Closed. Spec 057 (T001-T090) merged to `main` via PR #54 (merge commit `f9994f8`): canonical tokens,
accessible modes/typography/RTL, the four-file font package, the approved Core X logo package (five SVG variants +
provenance manifest), the brand-override validator, the scoped `--corex-admin-*` admin adapter, and the
design-system/branding documentation. Only environment-gated wp-env/browser evidence remains as explicit follow-up,
plus an owner release/version decision (no version is cut here). The legacy navy/cyan SVG is retained only as
rollback evidence.
**Outcome:** One accessible, brandable visual foundation shared by front-end, admin product UI, docs, and marketing.

- New CoreX logo system and usage rules.
- Final typography system, including Arabic font roles and fallbacks.
- Final semantic color tokens and brass/gold accent behavior.
- `theme.json` and runtime token alignment.
- Admin/product visual base.
- Dark-first and light-mode behavior.
- RTL typography and mixed-script rules.
- Accessibility baseline for color contrast, focus, type scaling, and motion.
- Design-system documentation update.

Implementation must preserve client brandability: CoreX product identity must not become a hardcoded client-site identity.

## 6. M3 - Header, Mobile Navigation, Mega Menu, and Footer System

**Status:** Design needed; high priority.
**Outcome:** Reusable template parts that cover company, product, docs, and commerce navigation without requiring a builder.

- Header variants: simple company, corporate with top bar, SaaS/product, docs, WooCommerce, transparent hero, minimal landing, and RTL examples.
- Mobile navigation: drawer, full-screen menu, nested accordion, and mobile mega-menu accordion.
- Mega menus: simple dropdown, services, product/features, docs/resources, and WooCommerce categories.
- Mega-menu item anatomy: optional icon, title, description, badge, link, featured card, and CTA.
- Sticky and transparent-to-solid behavior.
- Search overlay, language switcher, CTA slot, account slot, and cart slot.
- Footer variants: simple, corporate, SaaS, WooCommerce, newsletter, locations, legal, and RTL examples.
- Reusable FSE template parts and patterns.
- Defined keyboard, focus, escape, outside-click, mobile, reduced-motion, and RTL behavior.

Do not turn this milestone into a header builder or mega-menu builder.

## 7. M4 - Full Company Site Kit v1

**Status:** High priority after M0, M2, and M3.
**Outcome:** A neutral, brand-aware company kit that can launch the first real Corex company websites.

Required page coverage:

- Home
- About
- Services
- Single Service
- Case Studies / Work
- Single Case Study
- Industries
- FAQ
- Blog / News
- Single Post
- Team
- Testimonials
- Locations / Branches
- Contact
- Privacy Policy
- Terms
- Cookie Policy
- Search Results
- No Results
- 404
- Maintenance

Required kit behavior:

- Preview/apply UX with an explicit summary before mutation.
- Brand-aware setup fields without embedding a client brand in Corex.
- Demo content levels: minimal, standard, and full.
- Safe reset, adopt, skip, and conflict behavior for existing content.
- SEO starter metadata that remains editable and plugin-compatible.
- Accessibility, RTL, responsive, content-overflow, and mobile checks.

M4 should reuse approved native blocks and patterns. It must not absorb unrelated Pro workflows.

## 8. M5 - Blocks and Components Expansion

**Status:** High priority; implement in reviewed batches.
**Outcome:** Fill proven company-site and product-UI gaps without creating a page-builder-sized library.

Front-end block/component candidates:

- Services grid and service detail
- Process/steps and icon box
- Logo cloud and trust badges
- Case study grid and project card
- Rich tabs with InnerBlocks/full content support
- Shared slider/carousel system
- Testimonial, gallery, and logo carousels
- Featured posts and newsletter signup
- Contact info cards and map/location section
- Timeline, video modal, and before/after
- Pricing comparison
- Related posts/projects

Admin/product component candidates:

- Add-on card, dependency card, readiness checklist, status card, and metric card
- Empty state, skeleton, toast, data table, and filter bar

Required rules:

- Do not load slider JavaScript globally.
- Use no autoplay by default; any enabled autoplay must expose pause and stop on interaction.
- Respect `prefers-reduced-motion`.
- Provide keyboard operation, visible focus, usable labels, and announcements where needed.
- Support RTL direction, arrow semantics, swipe direction, and item order.
- Provide a server-rendered, usable fallback where possible.
- Batch work by real kit need; prefer WordPress core blocks, styles, and patterns when they satisfy the requirement.

## 9. M6 - CoreX Admin Product Experience

**Status:** Medium-high priority.
**Outcome:** A coherent operational surface for setup, configuration, add-ons, data, and readiness.

- Dashboard polish.
- Add-ons UI.
- Data UI.
- Settings UI.
- Setup wizard.
- Readiness/status screen.
- Free and Pro badges.
- Dependency missing, feature flag off, WooCommerce missing, and Pro required states.
- Installed/not installed and active/inactive states.
- Loading, empty, error, success, and permission-denied states.
- Future-aware license-expired state without implementing licensing early.

Heavy animation and commercial licensing workflows are not part of this milestone.

## 10. M7 - Forms and Email Experience

**Status:** Medium-high priority.
**Outcome:** Accessible form interactions and consistent branded transactional communication.

- Field anatomy, help text, required/optional treatment, and grouping.
- Validation states and accessible error summaries.
- Submit loading, success, and failure states.
- Captcha failed, honeypot triggered, and file upload rejected states.
- Admin notification email.
- User/contact confirmation email.
- Newsletter confirmation email.
- Shared branded email tokens with safe light/dark email-client behavior.

Password/reset email styling may be documented for future auth work but does not authorize a full auth/profile system.

## 11. M8 - Portfolio Kit Completion

**Status:** Medium priority.
**Outcome:** A neutral portfolio kit for studios, agencies, consultants, and independent professionals.

- Home
- Projects
- Single Project
- About
- Services
- Process
- Testimonials
- Contact
- Project archive with accessible filters
- Project categories

Reuse M3 navigation and approved M5 project/case-study components.

## 12. M9 - WooCommerce Kit Completion

**Status:** After WooCommerce design and runtime gating are stable.
**Outcome:** A complete, performant WooCommerce presentation kit that remains optional.

- Store Home
- Shop
- Product Category
- Single Product
- Cart
- Checkout
- My Account
- Order Received
- Support / Contact
- Shipping Policy
- Returns Policy
- Store FAQ
- Privacy Policy
- Terms
- Search / no products
- 404

The kit must retain dual gating: WooCommerce availability and Corex add-on activation. Advanced WooCommerce internals are not included.

## 13. M10 - Docs and Marketing Productization

**Status:** Later; required before public/commercial launch.
**Outcome:** Product-quality learning, reference, and launch surfaces aligned with the final visual foundation.

- Docs homepage and article layout.
- Component and pattern reference pages.
- Command/reference pages.
- Search/command palette.
- GitHub README banner.
- Open Graph cards.
- ThemeForest preview.
- Release cards.

Keep generated API reference separate from curated product documentation.

## 14. M11 - Pro and Commercial Layer

**Status:** Future. Do not build before Core/Core kits are stable.
**Outcome:** Commercial packaging and advanced vertical capabilities built on a trustworthy Free/Core product.

- Add-on edition metadata and runtime Pro gating.
- Admin Pro labels.
- License key screen, validation, update entitlement, and grace behavior.
- Pro package distribution.
- White-label admin and client portal.
- Advanced newsletter and advanced WooCommerce capabilities.
- Bookings and careers/ATS.
- Multi-company identity/dashboard.

### Free/Core boundary

Free/Core retains the framework, basic blocks/DLS, forms/contact form, config/options, basic media fields, captcha/honeypot, accessibility, RTL, i18n, basic `make:site`, and basic docs/deployment guidance. Security and accessibility basics must never require Pro.

### Pro/future boundary

Advanced vertical workflows, operational automation, commercial distribution, white-labeling, entitlement, and multi-company/client operations are Pro candidates. Classification does not equal implementation approval.

## 15. Deferred / do not implement now

- Front-office editor workspace.
- Header builder.
- Mega menu builder.
- Full client portal.
- Full auth/profile system.
- Full Pro licensing UI.
- Heavy animation in wp-admin.
- Advanced WooCommerce custom internals.
- Building all design ideas at once.

These items require later validation and dedicated specs. They must not leak into M0-M5.

## 16. Spec creation policy

- Keep one master engineering roadmap: this file.
- Create detailed specs only for the next two or three implementation items.
- Implement one reviewed spec at a time.
- Every implementation spec must include goal, scope, out of scope, files likely affected, acceptance criteria, tests/checks, and rollback notes.
- Design-approved items become focused handoff documents first, then engineering specs.
- Do not create code directly from design exploration without a reviewed engineering spec.
- Update roadmap status when priorities or dependencies change; do not rewrite completed spec history into the roadmap.

## 17. Current and next recommended specs

Create and implement one reviewed spec at a time:

1. **Spec 057 - Brand Tokens and Logo System** — done: merged via PR #54 (`f9994f8`), M2 closed.
   Remaining follow-up is env-gated wp-env/browser evidence and an owner release/version decision.
2. **Spec 058 - Header, Mobile Navigation, Mega Menu, and Footer Patterns** — **blocked, not yet creatable.** The M3
   navigation/footer design handoff does not exist (`design/INVENTORY.md` lists Navigation and Footer as `missing`;
   `design/handoffs/` holds only `brand-foundation.md`). Per this section, do not create Spec 058 until an
   owner-approved navigation/footer handoff (responsive, states, keyboard/focus/escape/outside-click,
   sticky/transparent, RTL, reduced-motion, performance) is recorded — like the brand handoff was for Spec 057.
3. **Spec 059 - Company Site Kit v1 Structure and Page Coverage** — do not create until the required
   M2/M3 contracts identify the first M5 component batch.

Spec number 056 is unavailable: both `056-dependency-security-remediation` and the already-merged
`056-design-roadmap-inventory` directory exist. Do not reuse or create another 056 spec. The immediate sequence is
Spec 057 foundational contracts, then its authorized implementation batches. M0 is closed; no later spec listed
here authorizes product code.
