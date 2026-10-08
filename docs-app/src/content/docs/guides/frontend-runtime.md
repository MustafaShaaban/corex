---
title: The response contract & frontend runtime
description: One canonical JSON envelope on the server and one buildless window.Corex on the client — so forms, admin actions, and REST all behave identically.
---

Corex speaks **one response shape** everywhere and ships **one small client runtime** that
understands it. A form submit, an admin action, and a REST call all return the same envelope,
and the same `window.Corex` drives the request, the loading state, and the error rendering —
with no jQuery and no build step.

## The response envelope

Every Corex endpoint returns one of two shapes.

**Success**

```json
{ "ok": true, "message": "Thanks — your message was sent.", "data": {} }
```

**Error** (validation)

```json
{
  "ok": false,
  "code": "validation_failed",
  "message": "Please check the highlighted fields.",
  "errors": { "email": "email" },
  "details": {}
}
```

**Error** (general)

```json
{ "ok": false, "code": "captcha_failed", "message": "Verification failed.", "details": {} }
```

A response never contains a secret — only `ok`, `message`, `data` (success) or `code`,
`message`, `errors`, `details` (error). On the server you build it with a pure value object:

```php
use Corex\Http\ResponseEnvelope;

return ResponseEnvelope::success(['id' => $id], __('Saved.', 'corex'));
return ResponseEnvelope::validation(['email' => 'email'], __('Check the fields.', 'corex'));
return ResponseEnvelope::error('captcha_failed', __('Verification failed.', 'corex'));
```

`Corex\Http\EnvelopeResponder::toRest($envelope)` maps it to a `WP_REST_Response`
(success → 200, validation → 422, forbidden → 403, other error → 400).

## The client runtime — `window.Corex`

The runtime is registered as the `corex-runtime` script and loads **only** where a Corex form
or admin screen needs it (it is declared as a dependency, never enqueued globally). It reads
`wp.apiFetch`/`wp.i18n` when present and falls back to `fetch` + an identity translator.

### `Corex.api`

```js
const result = await Corex.api.post( '/wp-json/corex/v1/things', { name: 'X' }, { nonce } );
// result = { ok, status, envelope }   — always resolves, never throws.
if ( result.envelope.ok ) { /* … */ } else { /* result.envelope.errors / .message */ }
```

`get`, `post`, and `delete` attach the REST nonce, parse the body, normalise it to an
envelope, and turn a timeout / network failure / non-JSON response into an ordinary error
result. Each request fires `corex:request:start` and `corex:request:end` on `document`.

### `Corex.forms.bind`

```js
Corex.forms.bind( formEl ); // idempotent; the runtime also auto-binds every .corex-form
```

`bind` reads the form's embedded `data-corex-schema` (the same schema the server validates
against — see [Create a form](/guides/forms/)), validates on submit with the **same rules**,
renders per-field errors into `[data-corex-field] .corex-form__error`, prevents duplicate
submits, posts via `Corex.api`, and renders the server's authoritative errors. It fires
`corex:form:success` / `corex:form:error` on the form; the table under Events says what each carries.

> The server is always authoritative. Client validation is instant feedback only; a submission
> that passes the client is still validated server-side.

### Adding a validated custom form

For a form defined in code, do not write these attributes by hand: the form's `markup()` is handed
them as parts. See [Draw a form your own way](/guides/forms/#draw-a-form-your-own-way).

What the runtime reads, for anything else that renders a form:

- `<form class="corex-form">` with `data-corex-endpoint`, `data-corex-nonce` and
  `data-corex-schema` (a JSON array of `{ name, type, label, required, rules }`);
- `data-corex-messages`, a JSON object of rule name to message. Optional: without it the
  runtime uses its own English table;
- `data-corex-success` and `data-corex-error`, what the status region says. Without them it
  shows the server's message when there is one, and otherwise nothing;
- each field in a wrapper `[data-corex-field="<name>"]` that holds a `.corex-form__error` element;
- one `.corex-form__status` region.

Every named control in the form is sent, the trap field and a challenge token among them. The
runtime binds the form automatically — no per-form JavaScript.

### Loading, status, and styling

`Corex.loading.start(region, submitEl)` adds `.corex-is-loading`, sets `aria-busy`, disables
the submit control, and shows a `.corex-spinner`; `Corex.loading.stop(token)` restores them.
`Corex.notices.status(region, message, kind)` writes the accessible `.corex-form__status`.
All four classes — `.corex-is-loading`, `.corex-spinner`, `.corex-form__status`,
`.corex-form__overlay` — are token-styled (theme.json CSS variables, with wp-admin fallbacks)
and use logical properties, so they are RTL-correct by default.

### Events

| Event | Target | Detail |
|---|---|---|
| `corex:request:start` / `corex:request:end` | `document` | `{ url, method, ok? }` |
| `corex:form:success` | the form | `{ envelope }` |
| `corex:form:error` | the form | `{ envelope }` when the server refused; `{ errors, fields }` when the browser's own check did |
| `corex:form:redirect` | the form | `{ url, success }` |
| `corex:form:custom-success` | the form | `{ success }` |

## Degradation

With JavaScript disabled a form does not submit: it has no `action`, so the browser posts it to the
page it is on, which nothing handles. The submit route takes JSON from the runtime.
Without `wp.apiFetch`, `Corex.api` uses `fetch`; without `wp.i18n`, strings fall back to their
English source.
