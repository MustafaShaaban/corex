# Contract — `window.Corex` client runtime surface

Buildless global. Available wherever the `corex-runtime` script is enqueued (form pages + Corex admin screens).
No jQuery. Uses `wp.apiFetch`/`wp.i18n` when present, degrades gracefully otherwise.

## `Corex.api`

```text
Corex.api.get(url, opts?)    → Promise<Result>
Corex.api.post(url, data?, opts?) → Promise<Result>
Corex.api.delete(url, opts?) → Promise<Result>
```

- Attaches the WP REST nonce automatically (`X-WP-Nonce` for `fetch`; apiFetch threads it natively).
- Always **resolves** to a `Result` (never rejects to the caller):
  ```text
  Result = { ok: boolean, status: number, envelope: ResponseEnvelope }
  ```
- Normalises any body to an envelope: a legacy/bare body is wrapped so `ok` is always defined.
- A non-2xx, non-JSON/HTML, timeout, or network failure → `{ ok:false, envelope:{ ok:false, code, message } }`
  with a translatable generic `message` (never a thrown exception, never raw HTML shown).
- `opts.timeoutMs` (default a sane value) aborts via `AbortController`.
- Fires `corex:request:start` then `corex:request:end` (with `{ ok }`) on `document`.

## `Corex.forms`

```text
Corex.forms.bind(formEl)   → void   // idempotent; safe to call twice
```

- Reads the embedded `data-corex-schema` (spec-020) and validates on submit using the **same** rules as the server
  (mirrors `required/email/numeric/max/min`, bail-per-field).
- On client error: renders per-field messages into `[data-corex-field] .corex-form__error`, sets `aria-invalid`,
  focuses the first invalid control, writes the global status — and sends **no** request.
- On valid: prevents duplicate submit, shows loading, POSTs via `Corex.api`, then renders the server envelope's
  `errors`/`message` (server authoritative). Resets the form + shows the success message on `ok`.
- Fires `corex:form:success` / `corex:form:error` on the form element with `{ envelope }`.
- The runtime auto-binds every `.corex-form` on load; `bind()` is also callable for dynamically added forms.

## `Corex.loading`

```text
Corex.loading.start(regionEl, submitEl?) → token
Corex.loading.stop(token)                → void
```

- `start`: adds `.corex-is-loading`, sets `aria-busy="true"`, disables `submitEl`, shows `.corex-spinner`; returns
  a token. A second `start` on an already-loading region returns `null` (dedupe).
- `stop`: restores all of the above. Always called on both success and error.

## `Corex.notices`

```text
Corex.notices.status(regionEl, message, kind)  → void   // kind: 'success' | 'error'
```

- Writes `message` into the region's `.corex-form__status` (a `role="status"` polite live region) and toggles a
  state class. No raw HTML injection (text only / escaped).

## Degradation

- No `wp.apiFetch` → `Corex.api` uses `fetch` + explicit nonce header.
- No `wp.i18n` → identity translator (English source strings).
- Runtime fails to load / JS disabled → forms fall back to the server-authoritative submit route; spec-020 error
  regions remain in the markup. Instant client validation is the only lost affordance (documented).
