# Phase 1 — Data Model: 043 Response contract + Runtime kit

This feature shapes **responses** and **client behaviour**; it persists nothing. The "entities" are value objects
and the conceptual client facilities.

## ResponseEnvelope (PHP value object — `Corex\Http\ResponseEnvelope`)

Immutable. Built only via static factories. Never holds a secret.

| Field | Type | Present when | Meaning |
|---|---|---|---|
| `ok` | bool | always | `true` success, `false` failure |
| `message` | string | always | human-readable, translatable summary (may be empty) |
| `data` | array | success | the success payload (may be empty `{}`) |
| `code` | string | failure | stable machine code (e.g. `validation_failed`, `captcha_failed`, `forbidden`) |
| `errors` | array<string,string> | failure (validation) | field name → translatable message |
| `details` | array | failure (optional) | explicitly-safe extra context (never secrets) |

**Factories**
- `success(array $data = [], string $message = ''): self` → `ok=true`.
- `error(string $code, string $message, array $details = []): self` → `ok=false`, empty `errors`.
- `validation(array $errors, string $message = ''): self` → `ok=false`, `code='validation_failed'`, `errors` set.

**Invariants**
- Immutable (readonly props; no setters).
- A success carries no `code`/`errors`; an error carries no `data`.
- Serialisation (`toArray()`) emits only the keys above — no internal/secret fields (SC-006).
- Pure: constructable and assertable with no WordPress runtime.

**Wire serialisation** — see `contracts/response-envelope.md`.

## EnvelopeResponder (PHP — `Corex\Http\EnvelopeResponder`)

Thin boundary. `toRest(ResponseEnvelope $e): WP_REST_Response`.

**Status mapping**

| Condition | HTTP status |
|---|---|
| `ok === true` | 200 |
| `code === 'validation_failed'` | 422 |
| `code === 'forbidden'` / nonce/cap | 403 |
| any other error | 400 |

Body = `$e->toArray()`. No business logic; no DB; no echo.

## Field-error map (reused concept)

`array<string, string>` — produced by the server validator (spec-020 schema) and consumed by both the server
(`validation()` factory) and the client (`Corex.forms` renders each into the matching `[data-corex-field]`'s
`.corex-form__error`). Keys are field names; values are translatable messages.

## Validation schema (existing — reused, not modified)

The per-field rule set already exported by `SchemaExporter` and embedded as `data-corex-schema` (spec 020). The
runtime's forms module mirrors these rules client-side. **This feature consumes it unchanged.**

## Client runtime facilities (conceptual — `window.Corex`)

Not data records — the named, independently-testable capabilities of the global. Full surface in
`contracts/runtime-api.md`.

| Facility | Responsibility | Key state |
|---|---|---|
| `Corex.api` | nonce-attaching request → normalised envelope; timeout/network → error result; start/end events | per-request abort token |
| `Corex.forms` | `bind(form)`: schema validate → render field/global errors → submit via `api` → render server errors | per-form bound flag, in-flight token (dedupe) |
| `Corex.loading` | disable submit, `aria-busy`, spinner, dedupe, restore on settle | per-region loading flag |
| `Corex.notices` | render global success/error into an accessible status region | — |

## Events (custom DOM events)

| Event | Dispatched on | `detail` |
|---|---|---|
| `corex:request:start` | document | `{ url, method }` |
| `corex:request:end` | document | `{ url, method, ok }` |
| `corex:form:success` | the form element | `{ envelope }` |
| `corex:form:error` | the form element | `{ envelope }` |

## CSS surface (token-styled, documented)

| Class | Applied to | Purpose |
|---|---|---|
| `.corex-is-loading` | form/region | loading state hook (dims content, shows overlay) |
| `.corex-spinner` | injected element | the spinner glyph (token-driven size/color) |
| `.corex-form__status` | status region | global success/error text (`role="status"`) |
| `.corex-form__overlay` | injected | optional busy overlay over a submitting region |
