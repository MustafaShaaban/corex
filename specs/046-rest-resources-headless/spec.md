# Feature Specification: REST resources & headless

**Feature Branch**: `feature/046-rest-resources-headless`

**Created**: 2026-06-14

**Status**: Draft (forward spec — precedes code; full Spec Kit flow)

**Input**: User description: "Make REST/headless usage Laravel-like but WordPress-native — a `make:api-resource`
generator (controller + routes + request validation + response resource + permissions + tests), a `routes:list`
command, an API docs/OpenAPI reference, and a documented headless mode (content/CPTs/forms/options/menus) with
nonce + application-password auth. Build on the spec-005 middleware + the spec-043 response envelope; JWT/OAuth
deferred."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Scaffold a complete REST resource from one name (Priority: P1) 🎯 MVP

A developer runs `wp corex make:api-resource Project` and gets a **complete, secured REST resource**: a thin
controller, its route registration (declaring the spec-005 middleware), a request-validation shape, a response
**resource** (DTO shaping the output — no raw model leak), a permission boundary, and a test — all wired to the
app's namespace/prefix and answering with the spec-043 envelope. They fill in the service call, not the
boilerplate.

**Why this priority**: REST is hand-rolled today; a generator that produces the *correct* Corex-shaped resource
(thin controller, declared middleware, envelope response, resource DTO) is the headline DX win and the foundation
for headless.

**Independent Test**: Run the generator for a name; the generated files are valid PHP, register a route under the
app's REST namespace with declared middleware + a permission callback, shape the response through a resource +
the envelope, and the generated test passes.

**Acceptance Scenarios**:

1. **Given** a resource name, **When** `make:api-resource` runs, **Then** it scaffolds a controller, route
   registration, a request shape, a response resource, and a test — in the app's paths/namespace/prefix.
2. **Given** the generated controller, **When** inspected, **Then** it is **thin** (route → validate → one service
   call → resource → envelope), declares its middleware, and hand-rolls no security.
3. **Given** the generated route, **When** registered, **Then** it lives under the app's REST namespace and carries
   a permission callback (not `__return_true` for a writing route).
4. **Given** the generated resource, **When** it shapes output, **Then** it exposes only declared fields (no raw
   model / no secret) inside the spec-043 envelope.
5. **Given** the generated test, **When** run, **Then** it passes (the scaffold is green out of the box).

---

### User Story 2 - See every Corex REST route (Priority: P1)

A developer runs `wp corex routes:list` and sees every registered Corex REST route — method, path, and the
permission/middleware it carries — so the API surface is discoverable instead of scattered across providers.

**Why this priority**: You cannot reason about (or document) an API you cannot enumerate; `routes:list` is the
discovery primitive the docs (US3) and headless (US4) build on.

**Independent Test**: With Corex routes registered, `routes:list` prints each route's method + path + permission;
a route added by a new resource appears in the list.

**Acceptance Scenarios**:

1. **Given** registered Corex routes, **When** `routes:list` runs, **Then** it prints each route's method, path,
   and permission/middleware, grouped readably.
2. **Given** a newly generated resource, **When** `routes:list` runs, **Then** its route appears.
3. **Given** no WP-CLI, **When** the framework loads, **Then** the command is simply absent (WP-CLI-gated) — the
   route registry that powers it is pure and usable without WP-CLI.

---

### User Story 3 - A reference of the API (Priority: P2)

A developer generates an **API reference** (`wp corex api:docs`) — an OpenAPI document / endpoint reference of the
Corex REST API (paths, methods, the envelope response shape, auth) — to share with frontend/integration
consumers.

**Why this priority**: A documented contract is what makes the API usable by others; it follows directly from the
route registry (US2). P2 because US1/US2 deliver the build + discovery first.

**Independent Test**: Run `api:docs`; it emits a valid OpenAPI/endpoint document describing the registered routes,
their methods, the envelope response shape, and the auth scheme.

**Acceptance Scenarios**:

1. **Given** the registered routes, **When** `api:docs` runs, **Then** it emits a valid OpenAPI (or a clear
   endpoint-reference) document listing paths/methods/auth and the spec-043 envelope response schema.
2. **Given** the document, **When** read, **Then** it describes the auth scheme (nonce / application password) and
   contains no secret.

---

### User Story 4 - A documented headless mode (Priority: P2)

A developer building a headless frontend reads a documented **headless mode**: which Corex data is exposed as a
read API (content / CPTs / forms / options / menus / blocks), the **envelope** shape it returns, and how to
authenticate (WordPress **nonce** for same-origin, **application passwords** for server-to-server). JWT/OAuth are
explicitly a later increment.

**Why this priority**: Headless is the strategic use case, but it is mostly *exposing + documenting* the existing
data through the contract (US1–US3) — so it lands after the generator + discovery + docs.

**Independent Test**: Following the headless docs, a consumer reads exposed content/CPTs/options via cap-gated,
envelope-shaped endpoints authenticated by a nonce or an application password; nothing private leaks.

**Acceptance Scenarios**:

1. **Given** headless mode, **When** a consumer reads an exposed resource, **Then** it returns the spec-043
   envelope and exposes only public/permitted data (no private field, no secret).
2. **Given** the auth docs, **When** followed, **Then** same-origin requests authenticate by nonce and
   server-to-server requests by an application password; an unauthenticated request to a protected resource is
   refused.
3. **Given** the headless docs, **When** read, **Then** the exposed surface (content/CPTs/forms/options/menus) and
   its limits are clearly stated; JWT/OAuth are noted as out of scope.

---

### Edge Cases

- A `make:api-resource` for a name that already exists → does not overwrite without `--force` (idempotent/safe).
- A generated writing route MUST NOT default to a public permission callback.
- `routes:list` with no Corex routes → an empty, clear listing, not an error.
- `api:docs` for a route lacking metadata → it still appears with what is known, never crashes.
- A headless read of a private/forbidden resource → refused (cap/permission), never a partial leak.
- Auth: an expired nonce / wrong application password → a clear `401/403` envelope, not a silent failure.

## Requirements *(mandatory)*

### Functional Requirements

**Generator (US1)**

- **FR-001**: `wp corex make:api-resource <Name>` MUST scaffold, in the app's paths/namespace/prefix: a thin
  controller, route registration declaring the spec-005 middleware, a request-validation shape, a response
  **resource** (output DTO), a permission boundary, and a test.
- **FR-002**: The generated controller MUST be thin (route → validate → one service call → resource → envelope)
  and MUST NOT hand-roll security; the route MUST declare its middleware and carry a permission callback (never a
  public callback for a writing route).
- **FR-003**: The generated resource MUST expose only declared fields (no raw model, no secret) and respond
  through the spec-043 envelope.
- **FR-004**: The generator MUST be idempotent/safe (no overwrite without `--force`) and produce **valid PHP** + a
  **green test** out of the box; the generator engine MUST be pure (headless-testable), with the WP-CLI command a
  thin gated boundary (the spec-003 pattern).

**Discovery (US2)**

- **FR-005**: A **route registry/reader** MUST enumerate the registered Corex REST routes (method, path,
  permission/middleware) as pure data; `wp corex routes:list` MUST print it readably.
- **FR-006**: The route reader MUST be usable without WP-CLI; the command MUST be WP-CLI-gated (absent otherwise).

**Docs (US3)**

- **FR-007**: `wp corex api:docs` MUST emit a valid OpenAPI document (or a clear endpoint reference) of the
  registered routes — paths, methods, the envelope response schema, and the auth scheme — containing **no secret**.

**Headless (US4)**

- **FR-008**: Headless mode MUST expose selected Corex data (content / CPTs / forms / options / menus / blocks) as
  **cap-gated, envelope-shaped** read endpoints, exposing only public/permitted data (no private field, no secret).
- **FR-009**: Authentication MUST support the WordPress **nonce** (same-origin) and **application passwords**
  (server-to-server); a protected resource MUST refuse an unauthenticated/under-permitted request with an envelope
  error. **JWT/OAuth are out of scope** (a later increment, documented as such).
- **FR-010**: The exposed headless surface, its limits, and the auth schemes MUST be **documented**.

**Cross-cutting**

- **FR-011**: All generated/exposed routes MUST run through the spec-005 middleware (nonce/auth/throttle/sanitize)
  and answer with the spec-043 envelope; **no secret** appears in any response or document (Principle VII).
- **FR-012**: No optional dependency MUST be required — WP-CLI is gated (`class_exists('WP_CLI')`); the framework
  runs fully without it; headless adds no hard dependency (Principle IX).

### Key Entities *(include if feature involves data)*

- **API resource (generated)**: controller + route registration + request shape + response resource (output DTO) +
  permission + test — in the app namespace/prefix, envelope-shaped.
- **Route descriptor**: a registered route as pure data — method, path, permission/middleware — enumerable by the
  route reader (powers `routes:list` + `api:docs`).
- **API document**: an OpenAPI/endpoint reference derived from the route descriptors + the envelope schema + the
  auth scheme — no secret.
- **Headless surface**: the documented set of exposed read resources (content/CPTs/forms/options/menus/blocks),
  their permission boundary, and the auth schemes (nonce / application password).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A developer scaffolds a complete, secured, envelope-shaped REST resource with **one command**, and
  the generated test passes **without edits**.
- **SC-002**: Every registered Corex REST route is **discoverable** via `routes:list` (method + path + permission)
  — **100%** of registered Corex routes appear.
- **SC-003**: `api:docs` produces a valid OpenAPI/endpoint document of the API in **one command**, describing the
  envelope shape + auth, with **no secret**.
- **SC-004**: A headless consumer can read exposed content/CPTs/options through cap-gated, envelope-shaped
  endpoints authenticated by a nonce or application password, with **no private field or secret** ever exposed.
- **SC-005**: The framework runs fully with **no** WP-CLI and **no** headless configuration (everything gated/
  optional); a generated writing route is **never** publicly callable.

## Assumptions

- Builds on and **reuses** the spec-003 generator engine (`StubRenderer`/`GeneratorEngine`/`Naming`/the `make:*`
  command layer), the spec-005 middleware (`Pipeline`/`MiddlewareResolver`/the declared aliases), the spec-002/030
  data layer (Models/Repositories/Resources), and the spec-043 `ResponseEnvelope` — this feature adds a generator
  template + a route reader + a docs emitter + a documented headless surface; it does not re-spec them.
- The generator engine is **pure** (render + plan), the WP-CLI command a thin `class_exists('WP_CLI')`-gated
  boundary (the spec-003 convention) — so the generator is unit-tested headlessly.
- Auth for v1 is the WordPress **nonce** (same-origin) + **application passwords** (server-to-server). **JWT/OAuth
  are out of scope** (a documented later increment).
- `api:docs` emits **OpenAPI 3** (JSON) as the primary format; a human-readable endpoint reference is acceptable
  where OpenAPI is overkill.
- Headless mode **exposes existing data** through the contract — it does not introduce a new data store; the
  exposed surface is read-first (writes go through the existing secured routes/forms).
- Out of scope (explicitly): JWT/OAuth, a GraphQL layer, a separate decoupled frontend app, and write-API
  generation beyond the secured resource the generator already produces.
- Browser/live confirmation of headless auth flows requires a running server; per the project-wide environment
  gate, the pure generator/route-reader/docs-emitter are unit-tested headlessly and the live flow runs when the
  environment is available.
