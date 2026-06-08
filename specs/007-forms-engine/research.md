# Phase 0 Research: Forms Engine

All spec clarifications were resolved in `/speckit-clarify`; this records the technical decisions.

## D1 — Event seam location: corex-core (`Corex\Events`)

- **Decision**: The `EventDispatcher` + `ListenerProvider` live in corex-core, bound by a new
  `EventServiceProvider` added to `Boot`. Forms is the first consumer; Corex Mail (spec 009) and
  add-ons will reuse it.
- **Rationale**: COREX-FRAMEWORK §11 calls it an "event bus" reused across modules. Putting it in
  corex-core avoids a forms→mail dependency inversion later. The seam is tiny and dependency-free.
- **Alternatives considered**: (a) seam inside corex-forms — rejected: Corex Mail would then depend
  on the forms plugin. (b) WordPress `do_action`/`add_action` as the bus — rejected: not headless-
  testable in isolation, no typed event object, ordering/első-class listener semantics are implicit;
  the constitution wants WP API confined to boundaries. We keep a pure dispatcher and may *also*
  fire a WP action from a boundary later if interop is needed.

## D2 — PSR-14 shape, but minimal

- **Decision**: Model the dispatcher on PSR-14 (`EventDispatcherInterface::dispatch(object $event)`
  + a listener provider), but keep it minimal: listeners are `callable`s registered by event class
  name; no stoppable-event support in v1.
- **Rationale**: Familiar, future-compatible shape without pulling a dependency. Stoppable events are
  YAGNI for forms (best-effort, all listeners run).
- **Alternatives**: full `psr/event-dispatcher` package — rejected: an extra dependency for an
  interface we can satisfy headlessly; we can adopt the interface later without breaking callers.

## D3 — Best-effort, isolated dispatch

- **Decision**: `dispatch()` invokes each listener in registration order inside a try/catch; a throwing
  listener is logged via `BootLogger` and the loop continues. The submission is still accepted.
- **Rationale**: Spec clarification — one failing side effect (e.g. SMTP down) must not lose the
  submission or block the store listener. Matches "dispatch is best-effort, not transactional."
- **Alternatives**: fail-fast (abort on first throw) — rejected: loses later listeners and the
  accepted submission. Queue/retry — out of scope (Corex Mail owns delivery guarantees).

## D4 — Validator: bail per field, rule registry

- **Decision**: For each field, evaluate rules in declared order and stop at the first failure
  (record one error/field). Rules implement a `Rule` contract (`validate(mixed $value, array $params,
  array $all): ?string` returning an error key or null). A `RuleRegistry` maps rule name → Rule, and
  parses `name:param` (e.g. `max:120`). Pure, no WP.
- **Rationale**: Spec clarification (one error per field) → deterministic, simplest messages, matches
  SC-002. The registry keeps the rule set open (OCP) — new rules add a class, never edit the validator.
- **Alternatives**: collect-all-errors-per-field — rejected by clarification (and noisier UX). A giant
  switch in the validator — rejected: violates OCP and clean-code complexity ceiling.

## D5 — Submission transport: REST under `corex/v1`

- **Decision**: A REST route `POST corex/v1/forms/{slug}` is the submit boundary. `SubmitController`
  builds a `Corex\Http\Middleware\Request` (method/input/nonce/nonceAction/throttleKey) and runs the
  existing `Pipeline` with `MiddlewareResolver->resolveAll(['nonce','sanitize:…','throttle'])`; the
  handler calls `FormSubmissionService`. `permission_callback` is `__return_true` because the **nonce
  middleware** (not the permission callback) proves intent — a public form has no capability gate, but
  the WP REST nonce (`wp_rest`) is verified by the `nonce` middleware (fail-closed).
- **Rationale**: REST is the modern, testable boundary (spec 004 already uses REST patterns); reusing
  the spec-005 middleware honors Principle VII instead of hand-rolling checks.
- **Alternatives**: `admin-ajax.php` — rejected: legacy, weaker testability. A custom endpoint without
  the middleware — rejected: would re-implement nonce/throttle/sanitize (Principle VII violation).

## D6 — Honeypot

- **Decision**: The block renders a visually-hidden honeypot field (e.g. `corex_hp`); the
  `FormSubmissionService` rejects (silently, as if success to the bot — but no side effect) when it is
  non-empty, before validation. Hidden via a token-styled off-screen utility (logical CSS), not
  `display:none` only, and `tabindex=-1`/`autocomplete=off` for accessibility.
- **Rationale**: Cheap, JS-free spam defense layered on top of the nonce + throttle. Confined to the
  service (logic) + block (markup) boundaries.
- **Alternatives**: CAPTCHA — out of scope, adds a third-party/optional dependency (Principle IX).

## D7 — Store listener: `corex_submission` CPT via the data layer

- **Decision**: `StoreSubmissionListener` persists a non-public `corex_submission` post (title = form
  slug + timestamp, the validated values saved as post meta) through the spec-002 repository/data
  layer. CPT registered by `FormsServiceProvider` on `init` (`public=false`, `show_ui` deferred to the
  later submissions-viewer spec).
- **Rationale**: Spec clarification — reuses existing data infrastructure, queryable by slug, no custom
  schema/migration. Keeps the admin viewer cleanly out of scope.
- **Alternatives**: custom table — rejected: migration + bespoke queries for no present benefit (YAGNI).
  Options/transient — rejected: not list-queryable.

## D8 — Block: server-rendered, conditional assets

- **Decision**: A dynamic block `corex/form` with an attribute `formSlug`; `render_callback` resolves
  `FormBlockRenderer` from the container (spec-004 pattern) and renders the schema server-side
  (labels with `for`/`id`, required markers, nonce field via `wp_nonce_field`/REST nonce, honeypot).
  A tiny `view.js` (declared in `block.json` → conditional load) posts to the REST route and swaps in
  the response. Styling: token-only, logical CSS.
- **Rationale**: Reuses the spec-004 engine; progressive enhancement keeps the form meaningful without
  JS; `block.json` gives per-block asset loading (Principle VI) for free.
- **Alternatives**: a full JS-built interactive block — rejected: a build pipeline is out of scope; the
  framework prefers server-rendered PHP blocks for WP 7.0.

## Cross-cutting

- **Reuse, don't reinvent**: middleware (spec 005), data layer (spec 002), block engine (spec 004),
  tokens (spec 006), `BootLogger`, the Container, `ServiceProvider`/`Boot` provider list.
- **Boundaries**: WP_Query/`register_post_type`/`register_rest_route`/`register_block_type`/`wp_mail`
  appear only in `FormsServiceProvider`, `SubmitController`, the listeners, and the block registrar —
  never in the Validator/SchemaResolver/EventDispatcher/Service core logic.
