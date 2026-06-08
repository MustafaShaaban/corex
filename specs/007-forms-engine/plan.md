# Implementation Plan: Forms Engine

**Branch**: `main` | **Date**: 2026-06-08 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/007-forms-engine/spec.md`

## Summary

Deliver forms as an *application* of the existing architecture. Three pure, headless cores —
a **Validator** (rules → at-most-one-error-per-field), a **SchemaResolver** (form definition →
canonical, validated rule set), and an **EventDispatcher** (register → ordered, best-effort
dispatch) — plus the WordPress boundary: a REST submit route guarded by the existing spec-005
middleware (`nonce`/`sanitize`/`throttle`) with a honeypot, a `FormSubmittedEvent` dispatched to
email + store listeners, and an FSE form block that renders a registered form from its schema.
The event seam is foundational and shared, so it lands in **corex-core** (`Corex\Events`); the
forms module ships as a new **`plugins/corex-forms`** plugin (`Corex\Forms`). One example
**contact** form proves the lifecycle end-to-end.

## Technical Context

**Language/Version**: PHP 8.3 (strict_types), block markup via FSE/`block.json`.

**Primary Dependencies**: corex-core (Container, Config, BootLogger, ServiceProvider, the spec-005
middleware: `Pipeline`/`MiddlewareResolver`/`Request`/`Response` + `nonce`/`sanitize`/`throttle`
aliases), spec-002 data layer (Repository/Model for `corex_submission`), spec-004 block engine
(`DynamicBlockRegistrar`), spec-006 theme tokens. No optional plugin is a hard dependency.

**Storage**: submissions as a `corex_submission` custom post type (via the data layer); no custom table.

**Testing**: Pest (Unit, headless — Validator/SchemaResolver/EventDispatcher/listeners) + Pest
Integration (`phpunit-integration.xml.dist`, real `./wp`) for the secured submit lifecycle.

**Target Platform**: WordPress 7.0+ (front-end render + REST submit), works in REST/admin/CLI/cron.

**Project Type**: WordPress framework module (single monorepo; new first-party plugin).

**Performance Goals**: form script loads only on pages with the block; submit path does bounded work
(validate + dispatch); no unbounded queries; store write is a single insert.

**Constraints**: WP API calls confined to boundary classes (REST handler, block registrar, listeners);
validator/resolver/dispatcher are pure (no WP). Token-only styling, logical/RTL CSS, WCAG 2.2 AA, i18n.

**Scale/Scope**: one example form; five v1 rules (`required`/`email`/`max`/`min`/`numeric`), open registry.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

Derived from `.specify/memory/constitution.md`. Mark each PASS / N/A / VIOLATION.

- [x] **I. Theme is a skin** — forms logic lives in `plugins/corex-forms` + corex-core; the theme only styles via tokens. PASS.
- [x] **II. Plugins boot themselves** — `FormsServiceProvider` (+ the corex-core `EventServiceProvider`) self-init on the standard boot pass; CPT + REST register on `init`/`rest_api_init`. PASS.
- [x] **III. Thin controllers, fat services** — the REST handler only adapts the request and delegates to the submission service; validation/dispatch/persistence live in services/listeners. PASS.
- [x] **IV. Everything injected** — Validator, SchemaResolver, EventDispatcher, FormRegistry, listeners all container-resolved; no `new` of a dependency in a method. PASS.
- [x] **V. Runtime tokens** — the block/form styling uses only `var(--wp--preset--*)`; no raw hex/size/font, no CSS framework. PASS.
- [x] **VI. Conditional assets** — the form script/style declared in the block's `block.json`, loaded only when the block renders. PASS.
- [x] **VII. Declarative security** — the submit route runs the existing `nonce`/`sanitize`/`throttle` middleware via the Pipeline + honeypot; no hand-rolled nonce/cap checks. PASS.
- [x] **VIII. RTL-first** — form markup uses logical properties; Arabic correct by default. PASS.
- [x] **IX. No optional dep is hard** — works on a stock install with corex-core; no ACF/Woo/builder dependency. PASS.
- [x] **X. Spec is source of truth** — this plan traces to spec 007 (clarified). PASS.
- [x] **Guard Gate + Definition of Done** acknowledged: clean-code-guard + wp-guard (production), test-guard (tests), docs-guard (docs); Pest tests; i18n; RTL; WCAG AA; PROGRESS/DECISIONS updated.

**Result**: PASS — no violations; Complexity Tracking not required.

## Project Structure

### Documentation (this feature)

```text
specs/007-forms-engine/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output (forms-contracts.md)
└── tasks.md             # Phase 2 output (/speckit-tasks)
```

### Source Code (repository root)

```text
plugins/corex-core/src/
├── Events/
│   ├── Event.php                  # marker interface (immutable event object)
│   ├── ListenerProvider.php       # registers listeners by event type
│   ├── EventDispatcher.php        # ordered, best-effort dispatch (pure; logs a throwing listener)
│   └── EventServiceProvider.php   # binds the dispatcher + provider (corex-core)

plugins/corex-forms/                # NEW first-party plugin (Corex\Forms)
├── corex-forms.php                 # WP plugin header + guarded autoloader + Boot hook
├── src/
│   ├── FormsServiceProvider.php    # binds registry/validator/resolver/handler/listeners; registers CPT, REST, block
│   ├── Form.php                    # abstract base: $slug, $fields, $listeners
│   ├── FormRegistry.php            # holds registered forms; lookup by slug (unknown → non-fatal)
│   ├── Schema/
│   │   ├── SchemaResolver.php      # fields → canonical rule set (rejects dup names / unknown rules); pure
│   │   └── FieldSchema.php         # normalized field value object
│   ├── Validation/
│   │   ├── Validator.php           # rules → ValidationResult (bail per field); pure
│   │   ├── ValidationResult.php    # valid flag, per-field errors, normalized values
│   │   ├── Rule.php                # rule contract: validate(value, params): ?errorKey
│   │   └── Rules/                  # Required, Email, Max, Min, Numeric (pure)
│   ├── Submission/
│   │   ├── SubmitController.php    # REST boundary: build Request → Pipeline(nonce,sanitize,throttle) → handle
│   │   ├── FormSubmissionService.php # honeypot + validate + dispatch; returns a result (no WP)
│   │   └── FormSubmittedEvent.php  # immutable: slug + validated values
│   ├── Listeners/
│   │   ├── StoreSubmissionListener.php  # persists corex_submission via the data layer (boundary)
│   │   └── SendEmailListener.php        # wp_mail notify (boundary)
│   ├── Forms/ContactForm.php       # example form (name/email/message)
│   └── Block/
│       ├── FormBlockRenderer.php   # renders a registered form's schema (accessible, token-styled)
│       └── blocks/corex-form/      # block.json (+ view.js, style) — conditional assets
└── tests live under repo-root tests/ (Corex\Tests)

tests/
├── Unit/Events/EventDispatcherTest.php
├── Unit/Forms/ValidatorTest.php
├── Unit/Forms/SchemaResolverTest.php
├── Unit/Forms/FormSubmissionServiceTest.php   # honeypot + validate + dispatch (fakes listeners)
└── Integration/Forms/SubmitLifecycleTest.php   # real WP: nonce/honeypot/validation + listeners observed
```

**Structure Decision**: The **event seam is foundational** → `Corex\Events` in corex-core (an
`EventServiceProvider` added to `Boot`), so Corex Mail and other add-ons consume the same dispatcher.
The **forms module** is a new first-party plugin `plugins/corex-forms` (`Corex\Forms`), mirroring the
corex-blocks bootstrap (WP header + guarded shared-autoloader fallback). Composer root autoload gains
`"Corex\\Forms\\": "plugins/corex-forms/src/"`. The three cores (Validator, SchemaResolver,
EventDispatcher) are pure and unit-tested; only `SubmitController`, the block registrar/renderer, and
the two listeners touch WordPress — each a thin boundary.

## Complexity Tracking

> No constitution violations — section intentionally empty.
