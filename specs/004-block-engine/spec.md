# Feature Specification: corex-blocks (Block Engine)

**Feature Branch**: `004-block-engine`

**Created**: 2026-06-08

**Status**: Draft

**Input**: User description: "corex-blocks (block engine) — block auto-discovery + registration from block.json (zero central registry); conditional asset loading (block CSS/JS load only when the block is present — Principle VI); dynamic PHP-rendered block support with a container-injectable render callback; a model→block connector seam (register a Corex data source editors can bind block attributes to). Built on corex-core + the data layer. Honors the constitution (conditional assets, theme.json tokens, RTL, injected, i18n, WCAG); discovery/registration/connector logic unit-testable headlessly; JS behavior via the Interactivity API. Delivers the block ENGINE + one minimal example block."

## Overview

corex-blocks turns the framework's presentation layer on: it discovers and registers WordPress blocks
by convention, loads each block's assets only when that block actually renders, lets a dynamic block's
server render resolve through the container (so it stays thin and injectable), and exposes a connector
seam so editors can bind block attributes to Corex data (Models/Repositories) without writing a bespoke
custom block. This feature delivers the **engine** — discovery, conditional assets, dynamic render,
and the connector seam — plus one minimal example block that proves it. It builds on corex-core
(container, providers, config) and the data layer (the Models/Repositories connectors expose). The
"users" are Corex theme/module developers; the indirect beneficiaries are site editors.

## Clarifications

### Session 2026-06-08

- Q: What is the one example block this spec ships? → A: A dynamic, server-rendered block that displays a Corex entity field via a connector — it exercises discovery + conditional assets + container-resolved render + the connector seam end-to-end with **no JS build required**.
- Q: How does an editor bind a block attribute to a connector? → A: Connectors register through the WP Block Bindings API (WP 7.0); the example binds a core block attribute to a Repository-backed connector field. If core binding support is partial in the target WP, a thin Corex binding source fills the gap behind the same registration.
- Q: How much of the JS build pipeline is in scope? → A: None — spec 004 delivers **server-rendered PHP** blocks/engine only (no webpack/Jest build). Interactive (Interactivity-API) blocks and the build pipeline are a later concern; the example block needs no JS build.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Blocks register themselves by convention (Priority: P1)

A developer adds a block as a folder (with a `block.json`) under the conventional blocks directory. On
load, the framework discovers every such folder and registers each block from its `block.json`
metadata — no central list to edit. A folder that is not a valid block is ignored.

**Why this priority**: Auto-registration is the irreducible core — every other capability (assets,
render, connectors) operates on registered blocks. It mirrors the controller auto-discovery convention
from spec 001.

**Independent Test**: place a folder with a valid `block.json` in the blocks location, load the
framework, and confirm the block is registered (appears in the block registry); place a folder without
a valid `block.json` and confirm it is ignored with no error.

**Acceptance Scenarios**:

1. **Given** a folder with a valid `block.json` in the blocks directory, **When** the framework loads,
   **Then** that block is registered from its metadata.
2. **Given** a directory entry that is not a valid block (no `block.json`), **When** discovery runs,
   **Then** it is skipped with no error.
3. **Given** no blocks exist yet, **When** discovery runs, **Then** the framework loads normally with an
   empty block set.
4. **Given** a registered block, **When** its registration is inspected, **Then** its name/attributes
   match its `block.json`.

### User Story 2 - A block's assets load only when it renders (Priority: P1)

A block declares its styles/scripts in its `block.json`. Those assets are enqueued only on pages where
the block is actually present — never globally. No global CSS/JS library is loaded for the framework.

**Why this priority**: Constitution Principle VI ("assets load conditionally; pay only for what
renders; never load a global library"). This is the headline value over hand-registered blocks.

**Independent Test**: render a page containing the block and confirm its declared CSS/JS is enqueued;
render a page without the block and confirm its CSS/JS is absent; confirm no framework-global asset is
enqueued on either page.

**Acceptance Scenarios**:

1. **Given** a page that contains the block, **When** it renders, **Then** the block's declared assets
   are enqueued.
2. **Given** a page that does not contain the block, **When** it renders, **Then** the block's assets
   are not enqueued.
3. **Given** any page, **When** it renders, **Then** no framework-wide global CSS/JS library is
   enqueued.

### User Story 3 - Dynamic blocks render through the container (Priority: P2)

A dynamic (server-rendered) block's render is resolved through the container so it can depend on
framework services (injected), and the render stays thin — it delegates to a service/renderer rather
than embedding business logic or direct data-source calls.

**Why this priority**: Constitution Principles III/IV (thin presentation, everything injected). Dynamic
blocks are how Corex data reaches the page; doing it injectably keeps the layering intact. Depends on
US1 (a block must be registered to render).

**Independent Test**: register a dynamic block whose render delegates to a container-resolved service;
render it and confirm the service-produced output appears, and that the render callback obtained its
dependency from the container (not hand-constructed).

**Acceptance Scenarios**:

1. **Given** a dynamic block, **When** it renders, **Then** its render callback is resolved through the
   container with its dependencies injected.
2. **Given** the render callback, **When** it runs, **Then** it delegates output to a service/renderer
   and contains no data-source calls or business rules.
3. **Given** the block's output, **When** it is produced, **Then** all dynamic values are escaped and
   user-facing strings are translation-ready.

### User Story 4 - Editors bind blocks to Corex data via a connector (Priority: P2)

A developer registers a connector that exposes a Corex data source (a Model/Repository) to the editor.
A site editor can then bind a core block's attribute to that data source, so the block displays Corex
data without anyone building a bespoke custom block.

**Why this priority**: The connector seam is the framework's bridge between the data layer and the
editor — the reason a content team can surface Corex entities without engineering. It builds on the
registered-block + data-layer foundation.

**Independent Test**: register a connector backed by a Repository, bind a block attribute to a named
field of that source, render the bound block, and confirm the field's value from the data layer appears
in the output.

**Acceptance Scenarios**:

1. **Given** a registered connector backed by a Repository, **When** a block attribute is bound to one
   of its fields, **Then** the rendered block shows that field's value from the data layer.
2. **Given** a bound field that has no value, **When** the block renders, **Then** it shows a safe
   empty/fallback state, not an error.
3. **Given** the connector, **When** it resolves a value, **Then** the value is escaped on output and
   the Repository is the only thing that touched the data source.

### Edge Cases

- **Malformed `block.json`**: a folder whose `block.json` is invalid is skipped and the problem logged;
  it does not abort discovery or registration of other blocks.
- **Duplicate block name**: two blocks declaring the same name — the conflict is reported; the first
  registration wins (no silent double-register).
- **Missing asset file**: a `block.json` referencing an asset that does not exist is reported; the block
  still registers (its other assets/markup work).
- **Dynamic render throws**: a render callback that errors yields a safe empty output (and a logged
  problem), never a fatal page.
- **Connector bound to a missing field/record**: renders a safe empty/fallback, not an error.
- **No optional plugins**: with ACF/Woo/Polylang absent, discovery, registration, assets, render, and
  connectors all work (the data layer already runs ACF-optional).
- **RTL / tokens**: block styles use logical properties and `theme.json` CSS variables — correct in RTL
  by default, no hardcoded colors/sizes.

## Requirements *(mandatory)*

### Functional Requirements

**Discovery & registration**

- **FR-001**: The system MUST discover blocks by scanning a conventional blocks directory (one folder
  per block) and register each block from its `block.json` metadata, with no hand-maintained central
  list.
- **FR-002**: A directory entry without a valid `block.json` MUST be ignored by discovery without error;
  an empty block set MUST NOT prevent the framework from loading.
- **FR-003**: A malformed `block.json` MUST be skipped and the problem logged, without aborting
  discovery of other blocks.
- **FR-004**: A duplicate block name MUST be reported and registered at most once (first wins).

**Conditional assets**

- **FR-005**: A block's styles/scripts MUST be declared in its `block.json` and enqueued **only** when
  the block is present on the page (never globally).
- **FR-006**: The framework MUST NOT enqueue any global CSS/JS library.
- **FR-007**: A `block.json` referencing a missing asset MUST be reported; the block MUST still register.

**Dynamic render**

- **FR-008**: A dynamic block's render callback MUST be resolved through the container (its dependencies
  injected); it MUST NOT be hand-constructed.
- **FR-009**: A render callback MUST stay thin — delegate output to a service/renderer, with no
  data-source calls or business rules in the callback.
- **FR-010**: Rendered dynamic output MUST escape all dynamic values and keep user-facing strings
  translation-ready; a render that throws MUST yield safe empty output (logged), never a fatal.

**Connectors**

- **FR-011**: The system MUST let a developer register a connector that exposes a Corex data source
  (Model/Repository) which editors can bind block attributes to.
- **FR-012**: A block attribute bound to a connector field MUST render that field's value sourced
  through the Repository (the only layer that touches the data source).
- **FR-013**: A bound field with no value/record MUST render a safe empty/fallback state, not an error;
  the value MUST be escaped on output.

**Cross-cutting**

- **FR-014**: Discovery, registration, conditional-asset wiring, dynamic-render resolution, and connector
  registration MUST be registered through a corex-core service provider and resolve collaborators via
  the container.
- **FR-015**: Block styling MUST use `theme.json` CSS custom properties and logical CSS properties
  (RTL-correct by default); no hardcoded colors/sizes/fonts; no CSS framework.
- **FR-016**: Blocks MUST be i18n-ready and meet WCAG 2.2 AA; JS behavior MUST use the Interactivity API
  (no jQuery, no Bootstrap JS).
- **FR-017**: Everything MUST work with no optional plugin (ACF/Woo/Polylang) installed.
- **FR-018**: The PHP discovery/registration/connector logic MUST be exercisable in headless automated
  tests; the feature ships **one dynamic, server-rendered example block** (no JS build required) that
  proves discovery + conditional assets + container-resolved render + a Repository-backed connector
  binding end-to-end.
- **FR-019**: Connectors MUST register through the WP Block Bindings API (WP 7.0); where core binding
  support is partial, a thin Corex binding source MUST fill the gap behind the same registration so
  calling/editor code is unchanged.

### Key Entities

- **Block**: a registrable unit defined by a folder + `block.json` (name, attributes, assets, render).
- **BlockMap**: the discovered set of blocks (the registry; convention-based discovery).
- **Asset policy**: the rule that a block's declared assets enqueue only when the block renders.
- **Block renderer**: the container-resolved service a dynamic block's render callback delegates to.
- **Connector**: a registered, editor-facing data source backed by a Corex Repository, bindable to block
  attributes.
- **Binding**: the link from a block attribute to a connector field.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Adding a block requires **zero** edits to any central registry — placing a valid block
  folder is sufficient for it to register.
- **SC-002**: A block's assets are enqueued on **100%** of pages that contain it and **0%** of pages that
  do not; **no** framework-global library is enqueued on any page.
- **SC-003**: A dynamic block's render resolves through the container and produces the service's output;
  a render that throws yields empty output with **0** fatal errors.
- **SC-004**: A block attribute bound to a connector field displays the data-layer value in **100%** of
  bound renders; a missing value yields a safe fallback in **100%** of cases.
- **SC-005**: With ACF, WooCommerce, and Polylang uninstalled, **100%** of the engine's automated tests
  pass headlessly.
- **SC-006**: The example block renders correctly in RTL with **zero** hardcoded colors/sizes (all values
  resolve from `theme.json` tokens) and passes an automated accessibility check.
- **SC-007**: Every discovery/registration/asset/render/connector behavior is covered by a headless
  automated test that passes with no optional plugins present.

## Assumptions

- **Audience**: Corex theme/module developers; site editors are the indirect beneficiaries.
- **Block model**: blocks follow the WordPress block-metadata (`block.json`) convention; discovery scans
  one level of folders under `plugins/corex-blocks/src/blocks`; connectors live under
  `plugins/corex-blocks/src/connectors`; namespace `Corex\Blocks`.
- **Registration mechanism**: blocks register via the core block-registration path from `block.json`;
  conditional assets ride on the block-metadata asset fields (loaded only when the block renders).
- **Dynamic render**: a block.json `render`/render-callback resolves to a container-bound renderer; the
  PHP render is unit-testable by invoking the resolved renderer directly.
- **Connector direction**: connectors expose Corex data to the editor for binding (the WP block-bindings
  / connectors direction); the example proves a Repository-backed field binding.
- **Build tooling**: the JS build pipeline (bundling the example block's Interactivity script + editor
  script) reuses the monorepo's existing tooling; the *engine* (PHP discovery/registration/connectors)
  is independently testable without a JS build. Full build-pipeline details are a later concern.
- **Scope boundary**: a full library of finished blocks, the theme/design-token module, forms, and the
  detailed build pipeline are **out of scope**; this spec delivers the engine + one example block.
- **Foundation dependency**: built on corex-core (container, providers, config, hook registry) and the
  data layer (Models/Repositories the connectors expose); registered as a service provider.
- **Environment**: developed against the working WordPress install (WP ≥ 7.0, which provides the
  block-bindings/connector APIs); Environment Gate satisfied.
