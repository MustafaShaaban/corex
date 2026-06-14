# Feature Specification: Corex Full Design Language System

**Feature Branch**: `feature/054-corex-full-dls`

**Created**: 2026-06-14

**Status**: Draft (forward spec — precedes code; full Spec Kit flow)

**Input**: User description: "Turn Corex's thin design-system catalog (spec 051) into a genuinely full,
WordPress-native Design Language System, grounded in the real UI inventory — inventory → gap analysis → roadmap
→ build only the justified items, preferring WordPress-native approaches over custom blocks."

## Overview

Spec 051 delivered a **taxonomy catalog** (`DesignSystemCatalog`) and two component atoms (alert, badge). It is
organized, but thin: the component layer is mostly empty, the foundations are partly undocumented, and there is
no single place a developer or designer can learn "what Corex gives me, when to use it, and when not to." This
feature makes the DLS **complete and navigable** — without rebuilding what WordPress already provides.

The method is **evidence-first**: enumerate the real inventory, classify every candidate UI element by a fixed
decision rule, build only what that analysis justifies, and document the whole system. The guiding judgment:
**prefer WordPress-native** (theme.json tokens, block styles, block patterns, FSE templates, core blocks); add a
custom Corex block **only** when Corex needs reusable server-rendered data, accessibility behavior, or a repeated
agency pattern that core cannot express.

### The real inventory today (the evidence base)

- **16 registered `corex/*` blocks** — catalog Components: `alert`, `badge`, `breadcrumbs`, `copyright`; catalog
  marketing/section blocks: `hero`, `cta`, `stat`, `testimonial`, `pricing`, `accordion`, `tabs`, `team`,
  `gallery`, `posts`; uncatalogued: `entity-field` (corex-blocks), `corex-form` (corex-forms), `jobs` (careers),
  `projects` (portfolio).
- **5 patterns** in corex-ui `PatternLibrary`: hero, features, cta, testimonial, contact.
- **9 FSE templates** (front-page, page, single, archive, search, 404, index, archive-project, single-project) +
  **2 parts** (header, footer) + **2 style variations** (dark, editorial).
- **theme.json tokens**: color (13-swatch palette incl. success/warning/error/info), typography (font families +
  sizes), spacing, shadow (sm/md/lg). **Missing token groups**: radius, motion/transition, focus, z-index/layout.
- **Form controls** live in corex-forms (`FieldRenderer`): text/email/number/tel/url/password/date/file/textarea/
  select/radio/checkbox-group/checkbox/toggle.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - The complete, classified inventory + gap analysis + catalog (Priority: P1) 🎯 MVP

A developer or designer opens the Corex design system and finds **every** existing UI element enumerated under a
clear taxonomy (Foundations, Components, Blocks, Patterns, Templates, Guidelines), each tagged with what it is and
where it lives — and a **gap analysis** that states, for every candidate element a real agency site needs, the
explicit decision: it already exists (and is good), it needs polish, it is a core block to document (not rebuild),
it should become a new Corex block, it should be a pattern, it stays theme/template responsibility, or it is
deferred. The expanded catalog is **drift-checked** so it can never list a `corex/*` block that is not registered.

**Why this priority**: It is the spine of the whole feature and prevents inventing a component list. Every later
build is justified by an entry here. It is independently valuable as documentation even if no new component ships.

**Independent Test**: The catalog enumerates the full taxonomy (all six layers) and every catalog Block/Component
maps to a real registered `corex/*` block (drift test passes). A gap-analysis document assigns one of the seven
decisions to each candidate UI element with a one-line rationale; no element is left unclassified.

**Acceptance Scenarios**:

1. **Given** the design system, **When** the catalog is built, **Then** it enumerates the full taxonomy across
   Foundations, Components, Blocks, Patterns, Templates, and Guidelines — not only the thin 051 set.
2. **Given** the catalog's Blocks/Components, **When** checked against reality, **Then** every listed `corex/*`
   block is actually registered (the drift test fails if any entry is invented or any block is missing).
3. **Given** the candidate component list (button, link, card, modal, drawer, tooltip, popover, dropdown, toast,
   pagination, stepper, table/list, validation summary, upload, search, empty state, loading/skeleton/spinner, …),
   **When** the gap analysis is read, **Then** each candidate carries exactly one decision (exists-good / polish /
   document-core / new-block / pattern / theme-responsibility / defer) with a rationale.

---

### User Story 2 - Complete, documented foundations (Priority: P1)

The foundations are whole and documented: the token system gains the **missing groups** (radius, motion, focus,
z-index/layout) so components never hardcode them, and a consolidated **Foundations guide** documents color,
typography, spacing, radius, shadow, grid/layout, icon guidance, motion guidance, focus states, RTL, and
accessibility — each with how to consume the token and the rules that govern it.

**Why this priority**: Components depend on foundations; a "shadow" or "radius" a component needs must exist as a
runtime token first (Principle V). Documenting the existing color/type/spacing/shadow tokens alongside the new
ones gives the system a single source of truth.

**Independent Test**: theme.json exposes radius, motion, focus, and z-index/layout tokens as CSS custom
properties; a Foundations doc page documents every token group with its consumption pattern; an existing block
re-styled to a new token (e.g. a radius token) renders unchanged visually but now reads the token, proving the
token resolves at runtime.

**Acceptance Scenarios**:

1. **Given** theme.json, **When** the new token groups are added, **Then** radius, motion/transition, focus, and
   z-index/layout are exposed as CSS custom properties and resolve at runtime (no build-time tokens).
2. **Given** the Foundations guide, **When** a developer reads it, **Then** every token group (existing + new) is
   documented with its CSS variable, allowed values, and usage rule, plus RTL, focus-state, and accessibility
   guidance.
3. **Given** a component consuming a new token, **When** brand.json overrides that token, **Then** the component
   reflects the override at runtime without a recompile.

---

### User Story 3 - The justified missing components, built native-first (Priority: P2)

The component layer gains the **UI atoms a real site needs that do not already exist and that the gap analysis
justified building** — each created by the lightest mechanism that satisfies its need: a **block style** or
theme.json entry where CSS suffices, a **core-block documentation** entry where WordPress already provides it, and
a **new server-rendered `corex/*` block** only where Corex needs reusable data, accessibility behavior, or a
repeated agency pattern. Every built component is token-only, accessible (WCAG 2.2 AA), i18n-ready, and RTL-correct
by default.

**Why this priority**: This is the substantive build, but it must follow US1's analysis so nothing is built that
core already covers. It is bounded to the justified set, prioritized by reuse value.

**Independent Test**: For each component the gap analysis marked "new-block", inserting it registers a dynamic
`corex/*` block that renders token-only, accessible, RTL-correct markup, and appears in the catalog (drift test
still passes). For each "document-core" or "block-style" component, the documented approach renders correctly with
no new custom block.

**Acceptance Scenarios**:

1. **Given** a component classified "new Corex block", **When** built, **Then** it is a dynamic, server-rendered
   `corex/*` block — escaped, token-only, i18n-ready, logical-CSS/RTL, with the correct ARIA semantics for its
   role — and the catalog lists it with no drift.
2. **Given** a component classified "WordPress core block to document", **When** delivered, **Then** no new block
   is created; instead a documented usage (with any Corex block style) covers it.
3. **Given** a component classified "block style / CSS only", **When** delivered, **Then** it ships as a
   registered block style or token-only CSS, not a new block.
4. **Given** any built or styled component, **When** inspected, **Then** it conveys state by more than color
   (icon/text), is keyboard-operable, and is RTL-correct by default.

---

### User Story 4 - Patterns, templates, and the documented design-system section (Priority: P2)

The composition layers are filled in and the whole system is documented for a team: the **patterns** cover the
sections a marketing/agency site assembles (section header, content split, media/text, feature grid, stats, CTA,
FAQ, team, gallery, pricing, posts/news, contact), the **templates** cover the page types (homepage, inner page,
landing, contact, listing, detail, form page), and a **docs-app design-system section** documents Foundations,
every Component, Patterns, Templates, and Guidelines — each with usage, accessibility, RTL notes, attributes/props,
examples, and explicit **when-to-use / when-not-to-use** guidance.

**Why this priority**: Patterns and templates are how the components become pages; the documentation is what makes
the system usable by a team and by AI agents. It depends on US1–US3 being settled.

**Independent Test**: The patterns and templates the gap analysis justified exist (each composed only of real
blocks/parts — a pattern-drift test passes); the docs-app builds a design-system section with a page per layer,
and every documented component links to a real catalog entry.

**Acceptance Scenarios**:

1. **Given** the pattern library, **When** the justified patterns are added, **Then** each composes only real,
   registered blocks/parts (a pattern-accuracy test fails on drift), token-only and RTL-correct.
2. **Given** the templates, **When** the justified page-type templates are added, **Then** each is a valid FSE
   template that assembles parts/patterns (no business logic in the theme — Principle I).
3. **Given** the docs-app, **When** built, **Then** it has a design-system section with Foundations / Components /
   Patterns / Templates / Guidelines pages, every component documented with when-to-use / when-not-to-use, and the
   build is green with no broken internal links.

---

### Edge Cases

- **A candidate that core already covers (e.g. button, columns, image, table)** → classified "document core", not
  rebuilt; the docs say which core block + which Corex block style to apply, with when-not-to-use.
- **A component that needs JS interactivity (modal, drawer, tooltip, dropdown, toast)** → built only if justified;
  uses the WordPress Interactivity API or a progressive-enhancement pattern, degrades accessibly without JS, and
  is recorded as needing browser verification (env-gated).
- **A "component" that is really a layout** (grid, stack, section) → stays theme/template/CSS responsibility,
  documented under Foundations/Layout, not shipped as a block.
- **The catalog references a block that is later renamed or removed** → the drift test fails, forcing the catalog
  and the block to stay in sync (the 051 guarantee, preserved as the system grows).
- **A token a component needs is missing** → it is added to theme.json first (US2) before the component consumes
  it; a component never hardcodes a value to avoid adding a token.
- **A pattern composes a block that does not exist** → the pattern-accuracy test fails (no drift into broken
  patterns).
- **Icon guidance** → Corex documents an approach (which icon set / how to embed accessibly), it does not bundle a
  heavyweight icon library as a hard dependency.

## Requirements *(mandatory)*

### Functional Requirements

**Inventory, classification, catalog (US1)**

- **FR-001**: The system MUST produce a complete, classified inventory of every existing `corex/*` block, pattern,
  template, template part, style variation, token group, and form control.
- **FR-002**: The system MUST produce a gap analysis that assigns every candidate UI element exactly one decision
  from a fixed set — exists-good · needs-polish · document-core · new-Corex-block · pattern · theme-responsibility
  · deferred — each with a rationale, and records per item: type, location (corex-ui / corex-forms / theme /
  docs-only / generated-client-site / deferred), why, status, tests needed, docs needed.
- **FR-003**: The catalog (`DesignSystemCatalog`) MUST expand to enumerate the full taxonomy (Foundations,
  Components, Blocks, Patterns, Templates, Guidelines) and MUST remain **drift-checked**: every `corex/*` block it
  lists is actually registered, and the test fails on any mismatch (in either direction for the blocks it claims).
- **FR-004**: The classification MUST prefer WordPress-native solutions; a new custom block is justified ONLY by a
  reusable server-rendered data need, an accessibility behavior need, or a repeated agency pattern core cannot
  express — and the rationale MUST state which.

**Foundations (US2)**

- **FR-005**: theme.json MUST add the missing token groups — radius, motion/transition, focus, and
  z-index/layout — exposed as CSS custom properties, resolving at runtime (no build-time tokens; brand.json may
  override).
- **FR-006**: A Foundations guide MUST document every token group (color, typography, spacing, shadow, radius,
  motion, focus, z-index/layout) with its CSS variable, allowed values, and usage rule.
- **FR-007**: The foundations MUST document grid/layout, icon guidance, motion guidance, focus states, RTL
  (logical properties), and accessibility (WCAG 2.2 AA) as guideline pages.

**Components (US3)**

- **FR-008**: For each component classified "new Corex block", the system MUST deliver a dynamic, server-rendered
  `corex/*` block that is escaped, token-only, i18n-ready, logical-CSS/RTL, and carries the correct ARIA semantics
  for its role; the catalog MUST list it with no drift.
- **FR-009**: For each component classified "document core" or "block style", the system MUST deliver the
  documented core-block usage or a registered block style / token-only CSS — and MUST NOT create a redundant
  custom block.
- **FR-010**: Every built or styled component MUST convey state by more than color, be keyboard-operable, and be
  RTL-correct by default; interactive components MUST degrade accessibly without JavaScript.
- **FR-011**: No component MUST hardcode a color, size, font, radius, shadow, or motion value; each consumes a
  theme.json token (Principle V).

**Patterns, templates, documentation (US4)**

- **FR-012**: The pattern library MUST gain the justified section patterns (composed only of real registered
  blocks/parts), token-only and RTL-correct; a pattern-accuracy test MUST fail on drift.
- **FR-013**: The theme MUST gain the justified page-type templates (homepage, inner page, landing, contact,
  listing, detail, form page) as valid FSE templates with no business logic in the theme.
- **FR-014**: The docs-app MUST gain a design-system section with a page per taxonomy layer; every Component page
  MUST include usage, accessibility, RTL notes, attributes/props, an example, and explicit when-to-use /
  when-not-to-use guidance.
- **FR-015**: All documentation MUST be applied in the same change as the code per the documentation-in-every-PR
  rule (COREX-WORKING-GUIDE §D.5); the generated class reference is left to `docs:generate`.

**Cross-cutting**

- **FR-016**: This feature MUST NOT rebuild what WordPress core blocks already provide (document those instead),
  MUST NOT copy any external design system's code, brand, or component names, and MUST NOT re-do the spec-053
  closeout items (Data UI, captcha button, make:site starter, README).
- **FR-017**: Every change MUST satisfy the constitution's Definition of Done (relevant guards clean, tests green,
  i18n, RTL, WCAG 2.2 AA, docs updated in the same change, PROGRESS/DECISIONS updated).

### Key Entities *(include if data involved)*

- **Catalog entry**: one element of the design system — a name, a taxonomy category (Foundation / Component /
  Block / Pattern / Template / Guideline), and, for Components/Blocks, the `corex/*` block it maps to (or null for
  core-block-backed / CSS-only entries).
- **Gap-analysis record**: a candidate UI element + its single decision + rationale + (type, location, status,
  tests-needed, docs-needed) — the evidence that authorizes (or declines) each build.
- **Design token group**: a named set of theme.json values exposed as CSS custom properties (color, typography,
  spacing, shadow, radius, motion, focus, z-index/layout), runtime-overridable via brand.json.
- **Component**: a UI atom delivered as a `corex/*` block, a registered block style, a documented core block, or a
  CSS/token-only treatment — with its role, ARIA semantics, attributes, and when-to-use / when-not-to-use.
- **Pattern / Template**: a composition of real blocks/parts (pattern) or an FSE page-type layout (template).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: The catalog enumerates **100%** of the taxonomy's six layers, and the drift test confirms **every**
  listed `corex/*` block is registered (zero invented or missing entries).
- **SC-002**: **Every** candidate UI element in the gap analysis carries exactly one decision with a rationale
  (zero unclassified candidates).
- **SC-003**: theme.json exposes the four previously-missing token groups (radius, motion, focus, z-index/layout),
  and a brand.json override of any new token changes the rendered result without a recompile.
- **SC-004**: A developer can find, for any component in the system, its usage + accessibility + RTL notes +
  attributes + an example + when-to-use / when-not-to-use, in one documented place.
- **SC-005**: **Zero** components ship that duplicate a WordPress core block; each such candidate is documented
  instead (verifiable against the gap analysis).
- **SC-006**: Every newly built `corex/*` block conveys state beyond color, is keyboard-operable, and is
  RTL-correct — verified by tests (and the env-gated console/a11y sweep where a browser is available).
- **SC-007**: The docs-app builds green with the design-system section and no broken internal links; the catalog,
  pattern, and (where applicable) block tests are green.

## Assumptions

- The DLS home is **corex-ui** (the 051 decision); the catalog stays the single registry and stays drift-checked.
- Styling is **token-only via theme.json/brand.json at runtime** — no build-time token system (Principle V);
  components consume CSS custom properties.
- The **exact build list is determined by US1's gap analysis**, not pre-fixed here: the spec sets the decision
  rule and the coverage target; the plan enumerates the specific new blocks / block styles / documented core
  blocks. A reasonable default is that **most** of the candidate atoms resolve to core-block-documentation or
  block-styles, and only a **small, high-reuse set** (e.g. feedback/navigation atoms not in core, such as
  toast/notification, tooltip, or a skeleton/loading treatment) become new `corex/*` blocks — confirmed per item
  in the plan.
- Interactive components (modal, drawer, tooltip, dropdown, toast) use the WordPress Interactivity API or
  progressive enhancement and degrade accessibly without JS; their **visual/browser verification is env-gated**
  (spec-052 Playwright/console sweep) and recorded honestly, not silently skipped.
- Icon and motion guidance are **documented approaches**, not bundled heavyweight dependencies (Principle IX /
  conditional-assets spirit).
- Form controls remain in **corex-forms** (`FieldRenderer`); the DLS documents them and references them, it does
  not move them.
- Out of scope: rebuilding core-covered elements, copying any external system's code/brand/names, the spec-053
  closeout items, and a public marketing site. New blocks beyond the justified set are deferred to a later spec.
