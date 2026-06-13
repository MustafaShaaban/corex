# Tasks: Request/Response contract + Frontend runtime kit

**Feature**: 043-response-runtime-kit · **Branch**: `feature/043-response-runtime-kit`
**Input**: [spec.md](./spec.md) · [plan.md](./plan.md) · [research.md](./research.md) ·
[data-model.md](./data-model.md) · [contracts/](./contracts) · [quickstart.md](./quickstart.md)

**Tests**: REQUIRED — the constitution's Definition of Done mandates Pest + Jest (and an env-gated Playwright smoke).

**Story legend**: US1 = accessible form feedback (P1, MVP) · US2 = one predictable response shape (P1) ·
US3 = reusable runtime for custom forms/requests (P2) · US4 = admin screens speak the contract (P2).

---

## Phase 1: Setup

- [x] T001 Create `plugins/corex-core/assets/js/` and `plugins/corex-core/assets/css/` and add an `HttpServiceProvider` in `plugins/corex-core/src/Foundation/HttpServiceProvider.php` that **registers** (not enqueues) script handle `corex-runtime` (deps `wp-i18n`, `wp-api-fetch`; in-footer) and style handle `corex-runtime`; wire it into the corex-core provider list (`Boot`/`CoreServiceProvider`).

---

## Phase 2: Foundational (blocking prerequisites — MUST complete before any user story)

- [x] T002 [P] Write Pest `tests/Unit/Http/ResponseEnvelopeTest.php` in `plugins/corex-core/` covering `success()/error()/validation()` shapes, immutability, success-has-no-code/errors, error-has-no-data, and `toArray()` exposing only contract keys (no secret) — per [contracts/response-envelope.md](./contracts/response-envelope.md).
- [x] T003 Implement `Corex\Http\ResponseEnvelope` in `plugins/corex-core/src/Http/ResponseEnvelope.php` (pure immutable value object; static factories; `toArray()`) until T002 is green.
- [x] T004 [P] Write Pest `tests/Unit/Http/EnvelopeResponderTest.php` asserting the status map (success→200, `validation_failed`→422, forbidden→403, other→400) and body = `toArray()`.
- [x] T005 Implement `Corex\Http\EnvelopeResponder` in `plugins/corex-core/src/Http/EnvelopeResponder.php` (`toRest(ResponseEnvelope): WP_REST_Response`) until T004 is green.
- [x] T006 [P] Write Jest `tests/corex-runtime.test.js` for `Corex.api`: normalises a bare body to an envelope; a non-2xx / non-JSON(HTML) / timeout / network failure resolves to an error `Result` (never throws); attaches the nonce; fires `corex:request:start`/`corex:request:end` — per [contracts/runtime-api.md](./contracts/runtime-api.md).
- [x] T007 Implement the runtime skeleton in `plugins/corex-core/assets/js/corex-runtime.js` (buildless IIFE over `window.wp`; `window.Corex = { api }`; `wp.apiFetch` when present else `fetch`; `wp.i18n.__` with identity fallback; `AbortController` timeout) until T006 is green.
- [x] T008 [P] Create token-styled `plugins/corex-core/assets/css/corex-runtime.css` defining `.corex-is-loading`, `.corex-spinner`, `.corex-form__status`, `.corex-form__overlay` using `var(--corex-…, <admin-palette fallback>)` and logical CSS properties (RTL-safe) — per [data-model.md](./data-model.md) CSS surface + research D9.

**Checkpoint**: envelope + responder + `Corex.api` + token CSS exist and are unit-green. All stories can start.

---

## Phase 3: User Story 1 — Accessible form feedback (P1) 🎯 MVP

**Goal**: a Corex form validates instantly, shows accessible loading + per-field/global errors, dedupes submits.
**Independent test**: submit (a) client-invalid → field error, no request; (b) server-rejected → server field
errors; (c) valid → success status; button disables/restores each time; double-click sends one request.

- [x] T009 [P] [US1] Write Jest specs (extend `tests/corex-runtime.test.js` or add `tests/corex-forms.test.js`) for `Corex.forms.bind` + `Corex.loading` + `Corex.notices`: client error blocks submit and renders field messages + focus-first; valid submit posts once (dedupe blocks the 2nd); server envelope `errors`/`message` render; `corex:form:success`/`error` fire; loading sets/clears `aria-busy` + disabled.
- [x] T010 [US1] Implement `Corex.forms` (bind + the schema-mirrored validator relocated from `corex-form/validation.js`: `required/email/numeric/max/min`, bail-per-field), `Corex.loading`, and `Corex.notices` in `plugins/corex-core/assets/js/corex-runtime.js`; auto-bind `.corex-form` on load — until T009 is green. Reuse the existing DOM hooks (`[data-corex-field]`, `.corex-form__error`, `.corex-form__status`, `aria-invalid`).
- [x] T011 [P] [US1] Write/extend Pest for `plugins/corex-forms/src/Submission/SubmitController.php`: `toRest()` emits the **envelope** (success → `{ok:true,message,data}` with `values` mirrored for back-compat; validation → 422 `{ok:false,code:'validation_failed',message,errors}`) — additive, existing assertions still pass.
- [x] T012 [US1] Update `SubmitController::toRest()` to build via `Corex\Http\ResponseEnvelope` + `EnvelopeResponder` until T011 is green (no security/middleware change).
- [x] T013 [US1] Rewrite `plugins/corex-forms/src/Block/blocks/corex-form/view.js` to a thin bootstrap delegating to `window.Corex.forms.bind` (remove the duplicated fetch/validation/error code); make `validation.js` a re-export of the runtime validator or delete it and update `validation.test.js`/`index.test.js` references.
- [x] T014 [US1] In `plugins/corex-forms/src/Block/FormBlockRenderer.php` (and the form `block.json` viewScript), declare `corex-runtime` as a script **dependency** so it loads only where the form renders (Principle VI); rebuild the block (`npm run build`).

**Checkpoint**: a contact form is fully driven by the runtime end-to-end (US1 independently testable).

---

## Phase 4: User Story 2 — One predictable response shape (P1)

**Goal**: every Corex response-emitting endpoint returns the one documented success/error envelope; no secrets.
**Independent test**: trigger success + failure on the form endpoint; bodies conform to the contract; no secret key.

- [x] T015 [P] [US2] Add a Pest contract test (`plugins/corex-core/tests/Unit/Http/EnvelopeContractTest.php`) asserting the three documented shapes round-trip through `ResponseEnvelope`→`EnvelopeResponder` exactly as in [contracts/response-envelope.md](./contracts/response-envelope.md), and that no non-contract key can leak.
- [x] T016 [US2] Audit the forms submit path for any field that could carry a secret (none expected) and confirm `details`/`data` only carry safe values; document the success `values`→`data` back-compat mirror in the forms README.

**Checkpoint**: the contract is proven uniform on the form endpoint; US4 extends conformance to admin endpoints.

---

## Phase 5: User Story 3 — Reusable runtime for any custom form/request (P2)

**Goal**: a developer wires a custom form/request with one `bind`/`api` call — nonce, loading, validation, events
all free, no jQuery, no build.
**Independent test**: bind a second custom form (not the built-in) → it gets validation/loading/error/events with
no bespoke fetch code.

- [x] T017 [P] [US3] Add a Jest spec proving a **second, custom** form element (its own `data-corex-schema`) bound via `Corex.forms.bind` gets validation + loading + error rendering + `corex:form:*` events with no extra code, and that `Corex.api.post` alone attaches the nonce + normalises the envelope.
- [x] T018 [US3] Finalise/stabilise the public `window.Corex` surface (api/forms/loading/notices + the four events) to match [contracts/runtime-api.md](./contracts/runtime-api.md); ensure `bind()` is idempotent and callable for dynamically added forms until T017 is green.

**Checkpoint**: the runtime is a reusable primitive (the spec-049 starter slice can depend on it).

---

## Phase 6: User Story 4 — Admin screens speak the contract (P2)

**Goal**: Insights + Data admin scripts use the shared runtime + envelope; their fetch/error duplication removed;
their endpoints conform.
**Independent test**: run an Insights check + a Data list/delete → both show shared loading + envelope-driven
results/errors; neither holds its own fetch/nonce/error code.

- [x] T019 [P] [US4] Migrate `plugins/corex-config/assets/insights.js` to issue requests via `Corex.api` and read results from the envelope (`data`); remove its bespoke `apiFetch`/catch plumbing; keep its accessible card rendering + loading state (now via `Corex.loading`).
- [x] T020 [P] [US4] Migrate the Data React app `plugins/corex-config/src/admin/index.js` so its list/delete `apiFetch` calls go through `Corex.api` and read envelopes (no React rewrite); rebuild (`npm run build`).
- [x] T021 [US4] In `plugins/corex-config/src/Insights/InsightsScreen.php` and `plugins/corex-config/src/Data/DataAdminScreen.php`, add `corex-runtime` to the enqueued script's dependency array (+ enqueue the `corex-runtime` style) so the runtime loads on those screens only.
- [x] T022 [US4] Ensure the Insights `run` controller and the Data list/delete controllers emit the envelope via `ResponseEnvelope`/`EnvelopeResponder` (additive; cap+nonce unchanged); extend their Pest tests for the new shape.

**Checkpoint**: front-end + admin all speak one contract through one runtime (SC-002 satisfied framework-wide).

---

## Phase 7: Polish & Cross-Cutting Concerns

- [x] T023 [P] Author `docs-app/src/content/docs/guides/frontend-runtime.md`: the envelope contract, the `window.Corex` surface, how to add a validated custom form, the CSS classes + tokens, the events, and the no-JS degradation. Link it from the forms guide.
- [x] T024 [P] Update READMEs: `plugins/corex-core/README.md` ("Response envelope + runtime"), `plugins/corex-forms/README.md` (view.js now delegates), `plugins/corex-config/README.md` (admin scripts on the runtime).
- [x] T025 Run the Guard Gate on the full diff and fix findings: `clean-code-guard` (envelope/responder/runtime), `wp-guard` (enqueue dep, nonce, escaping, REST mapping), `test-guard` (Pest+Jest), `docs-guard` (guide + READMEs).
- [x] T026 Run a token-only scan over `corex-runtime.css` (no raw hex/size/font outside the documented `var(--corex-…, fallback)` admin fallbacks) and confirm logical-property/RTL usage.
- [x] T027 Full suites green: **426 Pest** + **40 Jest**. The Playwright smoke (`npm run test:e2e`) remains **environment-gated** (needs Apache up + `npx playwright install`) — recorded as env-gated per the project-wide limitation, consistent with every spec since 018.
- [x] T028 Update `PROGRESS.md` (043 status + remaining env-gated items) and log `DECISIONS.md` #77 (the envelope + runtime contract + migration); end the session response with the NEXT STEP block.

---

## Dependencies & execution order

- **Setup (T001)** → **Foundational (T002–T008)** block everything.
- **US1 (T009–T014)** depends on Foundational; is the MVP and should land first.
- **US2 (T015–T016)** mostly satisfied by Foundational; can run alongside US1's tail.
- **US3 (T017–T018)** depends on US1's `Corex.forms` existing.
- **US4 (T019–T022)** depends on Foundational (`Corex.api`/envelope); independent of US1's form work, so it can
  proceed in parallel with US3.
- **Polish (T023–T028)** last.

**Parallel opportunities**: T002/T004/T006/T008 (different files, all `[P]`); within US1, T009 and T011 `[P]`;
US4's T019 and T020 `[P]`; docs T023/T024 `[P]`.

## Implementation strategy

- **MVP = Phase 1 + 2 + US1** — a contact form fully driven by the runtime + envelope, accessible and deduped.
  Ship/verify that first; it independently delivers the headline value.
- Then **US2** (prove the shape is uniform), **US4** (admin parity), **US3** (developer-reuse polish), then Polish.
- Honour TDD where the guard skills expect it (write the Pest/Jest task before its implementation task — already
  ordered that way: T002→T003, T004→T005, T006→T007, T009→T010, T011→T012, T017→T018).
