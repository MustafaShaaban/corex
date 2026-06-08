---
description: "Task list for Forms Engine (spec 007)"
---

# Tasks: Forms Engine

**Input**: Design documents from `specs/007-forms-engine/`

**Tests**: REQUIRED (constitution). Headless cores are unit-tested first (TDD); the secured lifecycle
has an integration test against real `./wp`.

**Guard Gate (per story)**: `clean-code-guard` + `wp-guard` (production), `test-guard` (tests),
`docs-guard` (docs). ABSPATH guard on every src class file. WP API only in boundary classes.

## Format: `[ID] [P?] [Story?] Description` — corex-core under `plugins/corex-core/src/Events`;
forms under `plugins/corex-forms/src`; tests under repo-root `tests/` (`Corex\Tests`).

---

## Phase 1: Setup

- [ ] T001 Scaffold `plugins/corex-forms/corex-forms.php` (WP plugin header — Requires Plugins: corex-core; guarded shared-autoloader fallback; ABSPATH guard; `Corex\Forms\Boot::init()`-style hook onto the corex-core provider list via its own ServiceProvider) mirroring `plugins/corex-blocks/corex-blocks.php`.
- [ ] T002 Add `"Corex\\Forms\\": "plugins/corex-forms/src/"` to root `composer.json` autoload and run `composer dump-autoload`; create `tests/Unit/Events/`, `tests/Unit/Forms/`, `tests/Integration/Forms/`.
- [ ] T003 Create `plugins/corex-forms/src/FormsServiceProvider.php` (skeleton `register()`/`boot()`, ABSPATH guard) and register it in `plugins/corex-core/src/Boot.php`'s provider list (after SecurityModule/ThemeServiceProvider).

## Phase 2: Foundational (blocking prerequisites)

- [ ] T004 [P] Create `plugins/corex-forms/config/forms.php` (`['email' => ['recipient' => '']]`, ABSPATH guard) for the email listener recipient (Config-overridable).

---

## Phase 3: User Story 1 — Validate submitted values (Priority: P1) 🎯 MVP

**Goal**: pure rules→errors validator (bail per field) + schema resolver. **Independent test**: unit
tests run rule sets against payloads and assert exact per-field errors; no WP.

- [ ] T005 [P] [US1] Write failing `tests/Unit/Forms/SchemaResolverTest.php`: fields → `FieldSchema` map; duplicate field name throws; unknown rule throws; `required` derived (FR-005).
- [ ] T006 [P] [US1] Write failing `tests/Unit/Forms/ValidatorTest.php`: each rule (`required`/`email`/`max:N`/`min:N`/`numeric`) valid→no error, invalid→exact field error; **bail per field** (one error/field, rule order); absent optional field is valid; field not in schema ignored (FR-002, FR-003, SC-002, SC-006).
- [ ] T007 [US1] Implement `plugins/corex-forms/src/Validation/Rule.php` (contract) + `Rules/{Required,Email,Max,Min,Numeric}.php` (pure; return i18n message key or null).
- [ ] T008 [US1] Implement `plugins/corex-forms/src/Validation/RuleRegistry.php` (name→Rule, parses `name:param`; unknown rule reported) and `Validation/ValidationResult.php` (valid/errors/values).
- [ ] T009 [US1] Implement `plugins/corex-forms/src/Schema/FieldSchema.php` + `Schema/SchemaResolver.php` (normalize; reject dup names / unknown rules) to green T005.
- [ ] T010 [US1] Implement `plugins/corex-forms/src/Validation/Validator.php` (bail per field) to green T006.
- [ ] T011 [US1] Bind Validator/SchemaResolver/RuleRegistry in `FormsServiceProvider::register()`; guard gate (clean-code + test-guard).

**Checkpoint**: validator + resolver fully unit-tested headlessly — the MVP core.

---

## Phase 4: User Story 2 — Ordered, best-effort event dispatch (Priority: P1)

**Goal**: the shared event seam in corex-core. **Independent test**: register listeners, dispatch,
assert order + once-each + best-effort isolation; no WP.

- [ ] T012 [P] [US2] Write failing `tests/Unit/Events/EventDispatcherTest.php`: listeners run once each in registration order; another event type's listener untouched; no-listener dispatch is a no-op; a throwing listener is logged (BootLogger) and the rest still run (FR-006, FR-007, FR-012a, SC-003, SC-008).
- [ ] T013 [US2] Implement `plugins/corex-core/src/Events/Event.php` (marker) + `Events/ListenerProvider.php` (listen / listenersFor, registration order).
- [ ] T014 [US2] Implement `plugins/corex-core/src/Events/EventDispatcher.php` (ordered dispatch; try/catch per listener → BootLogger; returns the event) to green T012.
- [ ] T015 [US2] Implement `plugins/corex-core/src/Events/EventServiceProvider.php` (singletons for ListenerProvider + EventDispatcher); register it in `Boot.php`; guard gate.

**Checkpoint**: event seam usable by any module (Corex Mail will reuse it).

---

## Phase 5: User Story 3 — Secured submission lifecycle (Priority: P1)

**Goal**: contact form end-to-end — security gate + validate + dispatch → email/store. **Independent
test**: integration POST to the route; valid→success+listeners; bad nonce/honeypot/invalid→rejected,
no side effect.

- [ ] T016 [P] [US3] Write failing `tests/Unit/Forms/FormSubmissionServiceTest.php`: honeypot filled → reject + zero dispatch; validation failure → reject + zero dispatch; valid → dispatch one `FormSubmittedEvent` with the validated values (fake dispatcher/registry) (FR-008, FR-010, FR-011, SC-006).
- [ ] T017 [P] [US3] Implement `plugins/corex-forms/src/Form.php` (abstract: slug/fields/listeners) + `FormRegistry.php` (register/find→null on unknown/all) (FR-001, FR-018).
- [ ] T018 [US3] Implement `plugins/corex-forms/src/Submission/FormSubmittedEvent.php` (implements `Corex\Events\Event`) + `Submission/FormSubmissionService.php` (honeypot → resolve schema → validate → dispatch; returns `Response::ok(values)`/`reject`) to green T016.
- [ ] T019 [US3] Implement `plugins/corex-forms/src/Forms/ContactForm.php` (name/email/message + rules) and register it with the FormRegistry in `FormsServiceProvider::boot()` (FR-012).
- [ ] T020 [US3] Implement `plugins/corex-forms/src/Listeners/StoreSubmissionListener.php` (persist `corex_submission` via the data layer) + `Listeners/SendEmailListener.php` (`wp_mail` to `config('forms.email.recipient')`); both `__invoke(FormSubmittedEvent)`; register on the ListenerProvider in `boot()` (FR-012, FR-012a, FR-016).
- [ ] T021 [US3] Register the `corex_submission` CPT (`public=false`) in `FormsServiceProvider::boot()` on `init` (boundary).
- [ ] T022 [US3] Implement `plugins/corex-forms/src/Submission/SubmitController.php`: `register_rest_route('corex/v1','/forms/(?P<slug>[a-z0-9-]+)')` on `rest_api_init`; `submit()` builds a `Corex\Http\Middleware\Request` (method/input/nonce=`wp_rest`/throttleKey) → `MiddlewareResolver->resolveAll(['nonce','sanitize','throttle'])` → `Pipeline->run()` with a handler calling `FormSubmissionService`; map the `Response` to a `WP_REST_Response` (200/403/422) (FR-009, FR-016).
- [ ] T023 [US3] Write `tests/Integration/Forms/SubmitLifecycleTest.php`: valid nonced submit → success + both listeners observed (a `corex_submission` exists); bad/missing nonce → rejected, no side effect; honeypot filled → rejected; empty message → 422 with `{message:'required'}`, no side effect (FR-009, FR-010, FR-011, SC-004).
- [ ] T024 [US3] Guard gate (clean-code + wp-guard on the controller/listeners/CPT; test-guard).

**Checkpoint**: a real, secured contact form works end-to-end.

---

## Phase 6: User Story 4 — Form block (Priority: P2)

**Goal**: render a registered form as an FSE block, token-styled, conditional asset.

- [ ] T025 [P] [US4] Write failing `tests/Unit/Forms/FormBlockRenderTest.php`: render output for the contact form contains each field with an associated `<label for>`/`id`, required markers, a nonce field, the honeypot, and references only `var(--wp--preset--*)` (no raw hex/px) (FR-013, FR-015, SC-005).
- [ ] T026 [US4] Implement `plugins/corex-forms/src/Block/FormBlockRenderer.php` (resolve form via registry; accessible, i18n, logical-CSS, token-only markup; nonce + honeypot) to green T025.
- [ ] T027 [US4] Create `plugins/corex-forms/src/Block/blocks/corex-form/block.json` (attribute `formSlug`; `viewScript` + `style` for conditional load) + a minimal `view.js` (POST to the REST route, swap response) + token-only `style`.
- [ ] T028 [US4] Register the block in `FormsServiceProvider::boot()` via the spec-004 `DynamicBlockRegistrar` (container-resolved `render_callback` → FormBlockRenderer) on `init`; verify the view script enqueues only when the block is present (FR-014).
- [ ] T029 [US4] Guard gate (clean-code + wp-guard; RTL/WCAG/i18n check on the markup).

---

## Phase 7: Polish & Cross-Cutting

- [ ] T030 [P] Create `plugins/corex-forms/README.md` (Form schema, validator rules, event seam, the submit route, the block); docs-guard.
- [ ] T031 [P] Update `plugins/corex-core/README.md` with a short "Events" section (the dispatcher seam); docs-guard.
- [ ] T032 Run the full headless suite + integration; confirm site HTTP 200 and a live contact submission stores + emails (manual/`wp eval`); confirm the form script loads only on a page with the block (SC-001/004/005/007).
- [ ] T033 Update `PROGRESS.md` (spec 007 complete) + `DECISIONS.md` (event seam in corex-core; submission CPT; bail-per-field); verify Definition of Done.

---

## Dependencies & Execution Order

Setup (T001–T003) → Foundational (T004) → **US1** (T005–T011, MVP) ∥ **US2** (T012–T015, independent,
corex-core) → **US3** (T016–T024, needs US1+US2) → **US4** (T025–T029, needs US1+US3) → Polish
(T030–T033). US1 and US2 touch disjoint trees and may proceed in parallel.

## Inline analyze — FR/SC coverage

- FR-001 T017/T019 · FR-002 T006/T010 · FR-003 T006/T007/T008 · FR-004 T007 (i18n keys) · FR-005 T005/T009 ·
  FR-006 T012/T013 · FR-007 T012/T014 · FR-008 T016/T018 · FR-009 T022/T023 · FR-010 T016/T018/T023 ·
  FR-011 T006/T016/T023 · FR-012 T019/T020 · FR-012a T012/T020 · FR-013 T025/T026 · FR-014 T027/T028 ·
  FR-015 T025/T026 · FR-016 T020/T022 · FR-017 (no optional dep) all · FR-018 T009/T017.
- SC-001 T032 · SC-002 T006 · SC-003 T012 · SC-004 T023 · SC-005 T025/T028 · SC-006 T006/T016 ·
  SC-007 T025/T029 · SC-008 T012/T014. **All FRs and SCs covered; 0 critical.**

## Notes
- WP API (`register_post_type`/`register_rest_route`/`register_block_type`/`wp_mail`/`WP_Query`) only in
  `FormsServiceProvider`, `SubmitController`, the listeners, the block registrar. Validator/SchemaResolver/
  EventDispatcher/FormSubmissionService stay pure. One task at a time; guard before each commit; commit per story.
