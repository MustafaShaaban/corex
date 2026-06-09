# Feature Specification: Company Website Kit (MVP)

**Feature Branch**: `010-company-kit`

**Created**: 2026-06-09

**Status**: Draft

**Input**: A neutral company-website blueprint composing the spec-009 blocks/patterns into the universal
FSE templates + a front-page composition + a Blueprint manifest. Token-only, RTL, WCAG, neutral, no
business logic. Add-on `corex-kit-company` (`Corex\Kit`) + theme FSE files.

## Clarifications

### Session 2026-06-09 (informed defaults)

- **Q: Where do the templates live?** → The universal **FSE templates + parts live in the theme**
  (`theme/templates`, `theme/parts`) — the constitution's home for presentation; the **Blueprint manifest +
  registry** (the only code) ship in `addons/corex-kit-company` (`Corex\Kit`).
- **Q: Which templates?** → `front-page`, `page`, `single`, `archive`, `search`, `404` (plus the existing
  `index`), and enhanced `header`/`footer` parts. The front page composes the spec-009 section patterns.
- **Q: Verification?** → Headless: the Blueprint manifest/registry, template-file presence, and a token-only
  scan of the template/part markup. **Visual/editor correctness needs a browser** (flagged, not claimed).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Every page type has a template (Priority: P1) 🎯 MVP

A new site has a complete set of universal FSE templates — home, page, single post, archive, search, and
404 — each composing the header, the relevant content, and the footer, token-styled and accessible.

**Why this priority**: Without the template set, the kit cannot present a real site; these are the
structural minimum.

**Independent Test**: Each template file exists in the theme, references the header/footer parts and the
correct content blocks, and contains no hardcoded color/size/font.

**Acceptance Scenarios**:

1. **Given** the theme, **When** the template set is inspected, **Then** front-page, page, single, archive,
   search, and 404 templates exist and each wraps content with the header and footer.
2. **Given** any template/part, **When** scanned, **Then** it uses only design tokens (no raw hex/px).

---

### User Story 2 - A composed, accessible home page (Priority: P1)

The front-page template composes the spec-009 section patterns (hero → features → CTA → contact) into a
complete company home.

**Why this priority**: The home page is the kit's headline deliverable; it proves composition of the block
library.

**Independent Test**: The front-page markup references the Corex section patterns/blocks and forms a single
accessible document (one h1 via the hero, ordered headings, landmarks).

**Acceptance Scenarios**:

1. **Given** the front page, **When** rendered, **Then** it shows the composed company sections within the
   header/footer.

---

### User Story 3 - A discoverable Blueprint manifest (Priority: P2)

The kit exposes a machine-readable Blueprint — its required/recommended modules, templates, parts, and the
patterns it relies on — so tooling and a future setup wizard can introspect it.

**Why this priority**: Enables future onboarding/selection; the templates work without it, hence P2.

**Independent Test**: The CompanyBlueprint manifest lists the kit's templates, parts, required modules
(corex-ui), and recommended modules (forms, mail); the registry holds it by name.

**Acceptance Scenarios**:

1. **Given** the BlueprintRegistry, **When** the company kit is registered, **Then** it is found by name and
   its manifest enumerates the templates/parts/modules it provides.

---

### Edge Cases

- The corex-ui add-on inactive → the patterns referenced by the front page are unavailable; the template
  still loads (WP shows the available blocks); no fatal.
- An RTL locale → templates/parts mirror correctly (logical CSS; the theme is RTL-first).
- No menu assigned → the header navigation degrades gracefully.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The theme MUST provide the universal FSE templates — `front-page`, `page`, `single`,
  `archive`, `search`, `404` — plus enhanced `header` and `footer` parts.
- **FR-002**: Every template and part MUST compose the header and footer and use only design tokens (no
  hardcoded colors/sizes/fonts), with logical CSS (RTL-correct) and accessible structure (landmarks, a
  single h1 where applicable, labelled controls).
- **FR-003**: The `front-page` template MUST compose the spec-009 section patterns into a company home.
- **FR-004**: The kit MUST expose a machine-readable **Blueprint** — name, required modules (`corex-ui`),
  recommended modules (`corex-forms`, `corex-email`), the templates, the parts, and the patterns it relies
  on — held in a **BlueprintRegistry**.
- **FR-005**: The kit MUST add no business logic; it composes presentation only. It MUST register on a stock
  install with corex-core active and MUST NOT hard-depend on any optional plugin (degrades gracefully).
- **FR-006**: The kit MUST be neutral/un-branded — its look derives entirely from theme/brand tokens, so a
  client brand restyles it without editing any template.

### Key Entities *(include if feature involves data)*

- **Blueprint**: a kit manifest — name, required/recommended modules, templates, parts, patterns. Read-only.
- **BlueprintRegistry**: holds registered blueprints by name (lookup, all).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: The theme presents a complete set of universal templates (home/page/single/archive/search/404).
- **SC-002**: 100% of the kit's templates and parts contain no hardcoded color/size/font.
- **SC-003**: The front page composes the Corex section patterns into a single accessible home document.
- **SC-004**: The CompanyBlueprint manifest matches the templates/parts/modules the kit actually provides.
- **SC-005**: The headless suite covers the Blueprint registry/manifest + the token-only template scan.
- **SC-006**: Deactivating the kit add-on removes the Blueprint registration only; the theme templates
  remain (they are the skin) and no data/behavior is affected.

## Assumptions

- **Templates in the theme; manifest in the add-on.** FSE templates/parts are the theme's (the skin); the
  Blueprint manifest + registry are the `corex-kit-company` add-on's code.
- **Composition only.** Reuses spec 009 (blocks/patterns), 006 (tokens/brand), 007 (contact form). No new
  domain logic.
- **Visual verification deferred to a browser.** Template *presence*, structure references, token-only, and
  the manifest are headless-tested; visual/editor correctness needs a browser to confirm.
- **Out of scope (deferred)**: the setup wizard / onboarding UI; demo content import; additional kits;
  bundled media; multiple style variations beyond the neutral default + existing dark.
