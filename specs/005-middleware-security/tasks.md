---
description: "Task list for Middleware + Security (spec 005)"
---

# Tasks: Middleware + Security

**Input**: Design documents from `specs/005-middleware-security/`

**Tests**: REQUIRED (constitution). Every implementation task is preceded by a failing test (TDD). All
seams are headless-testable (WP functions stubbed via Brain Monkey).

**Guard Gate (per story)**: `clean-code-guard` + `wp-guard` (production), `test-guard` (tests). ABSPATH
guard on every src class file.

## Format: `[ID] [P?] [Story] Description` — code under `plugins/corex-core/src/Http/Middleware` + `Security`.

---

## Phase 1: Setup

- [X] T001 [P] Create `plugins/corex-core/config/security.php` (`['throttle' => ['limit' => 60, 'window' => 60]]`, ABSPATH guard); create `tests/Unit/Security/`.

## Phase 2: Foundational

- [X] T002 [P] `Http/Middleware/Middleware.php` interface (`process(Request, callable $next): Response`).
- [X] T003 [P] `Http/Middleware/Request.php` (immutable: method/input/nonce/nonceAction/throttleKey; `withInput`).
- [X] T004 [P] `Http/Middleware/Response.php` (`ok()`/`reject()`/`isOk()`).

## Phase 3: User Story 1 — Pipeline (Priority: P1) 🎯 MVP

- [X] T005 [P] [US1] Write failing `tests/Unit/Security/PipelineTest.php`: order (outer→inner, handler reached when none reject); a rejecting middleware stops inner + handler; empty list → handler; a throwing middleware → reject + logged, handler not run (FR-002–FR-004, FR-006, SC-001, SC-004).
- [X] T006 [US1] Implement `Http/Middleware/Pipeline.php` (right-fold onion; try/catch → `Response::reject`, logged).
- [X] T007 [US1] Guard gate.

## Phase 4: User Story 2 — The four core middleware (Priority: P1)

- [ ] T008 [P] [US2] Write failing `tests/Unit/Security/MiddlewareTest.php`: Nonce (non-GET no/invalid → reject, valid → pass), Capability (current_user_can), Throttle (transient count vs limit; reset after window), Sanitize (handler sees only cleaned shape) — WP funcs stubbed (FR-007–FR-010, SC-002).
- [ ] T009 [P] [US2] `Http/Middleware/NonceMiddleware.php` (wp_verify_nonce; GET passes; configurable methods).
- [ ] T010 [P] [US2] `Http/Middleware/CapabilityMiddleware.php` (current_user_can($cap)).
- [ ] T011 [P] [US2] `Http/Middleware/ThrottleMiddleware.php` (get_transient/set_transient; config limit/window).
- [ ] T012 [P] [US2] `Http/Middleware/SanitizeMiddleware.php` (reduce input to expected shape via WP sanitizers).
- [ ] T013 [US2] Guard gate (wp-guard: nonce/cap/transient/sanitizer usage).

## Phase 5: User Story 3 — Declarative resolution (Priority: P2)

- [ ] T014 [P] [US3] Write failing `tests/Unit/Security/MiddlewareResolverTest.php`: resolve `alias:param` (param passed); `resolveAll`; unknown alias → RejectingMiddleware (fail closed) (FR-012, FR-014, FR-015, SC-004).
- [ ] T015 [US3] `Http/Middleware/RejectingMiddleware.php` + `Http/Middleware/MiddlewareResolver.php`.
- [ ] T016 [US3] Guard gate.

## Phase 6: User Story 4 — SecurityModule (Priority: P2)

- [ ] T017 [US4] `Security/SecurityModule.php` — bind alias factories `nonce`/`auth`/`throttle`/`sanitize`, `MiddlewareResolver`, `Pipeline`; add to `Boot`'s provider list.
- [ ] T018 [P] [US4] Write `tests/Unit/Security/SecurityModuleTest.php` or extend resolver test: each standard alias resolves to its middleware (FR-016, SC-005). (Headless: build the resolver with the alias bindings.)
- [ ] T019 [US4] Guard gate.

## Phase 7: Polish

- [ ] T020 [P] Update `plugins/corex-core/README.md` with a middleware/security section; docs-guard.
- [ ] T021 Final guard pass; confirm the headless unit suite passes with no optional plugins (SC-006); site still boots.
- [ ] T022 Update `PROGRESS.md` + `DECISIONS.md`; verify Definition of Done.

---

## Dependencies & Execution Order

Setup → Foundational (interface/Request/Response) → US1 Pipeline (MVP) → US2 four middleware → US3
resolver → US4 SecurityModule → Polish. US2 middleware are parallel (different files); the resolver +
module depend on them.

## Notes
- WP security functions (`wp_verify_nonce`, `current_user_can`, transients, sanitizers) sit at the
  middleware boundary; the pipeline/resolver are pure. Fail-closed on throw/unknown. Controllers declare
  middleware, never enforce (Principle VII). One task at a time; guards before each commit.
