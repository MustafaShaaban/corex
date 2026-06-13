# Contract — Admin test actions (captcha + insights)

Both actions are `AdminGuard`-gated (`manage_options` + a valid REST nonce), answer with the spec-043
`ResponseEnvelope`, and **never** include a secret. They reuse the existing captcha drivers / insight providers.

## Captcha — Test verification

`POST corex/v1/captcha/test` (cap+nonce). Body: `{ "token": "<a test captcha token, optional>" }`.

Runs the **configured** driver's verify (synthetic probe for honeypot/none). Responses:

```json
{ "ok": true,  "message": "Captcha keys verified.", "data": { "kind": "ok" } }
{ "ok": false, "code": "missing_keys",  "message": "Add the site and secret keys for this driver.", "details": {} }
{ "ok": false, "code": "invalid_keys",  "message": "The keys were rejected by the provider.", "details": {} }
{ "ok": false, "code": "network_error", "message": "Could not reach the captcha provider — try again.", "details": {} }
```

- The **secret key MUST NOT appear** anywhere in the response (FR-008 / SC-006).
- `not_applicable` (driver none/honeypot) → `{ ok:true, data:{ kind:"not_applicable" } }` with a clear message.

## Insights — Test key / URL

`POST corex/v1/insights/test` (cap+nonce). Body: `{ "url": "<site url, optional>" }` (defaults to `home_url`).

Classifies reachability + a probe attempt:

```json
{ "ok": false, "code": "local_url",        "message": "PageSpeed can't crawl a local/private URL (corex.local). Test a public URL.", "details": {} }
{ "ok": false, "code": "http_error",       "message": "PageSpeed returned HTTP 500. Try again shortly.", "details": { "status": 500 } }
{ "ok": false, "code": "quota",            "message": "PageSpeed quota exceeded — add an API key or wait.", "details": {} }
{ "ok": false, "code": "invalid_key",      "message": "The PageSpeed API key was rejected.", "details": { "status": 400 } }
{ "ok": false, "code": "invalid_response", "message": "PageSpeed returned an unreadable response.", "details": {} }
{ "ok": true,  "message": "PageSpeed reachable.", "data": { "kind": "ok", "keyAdvice": "recommended" } }
```

- `details` is populated **only** for a `manage_options` admin and is **scrubbed** of any `key=`/token (FR-013).
- The response states whether the key is `optional`/`recommended` (`data.keyAdvice` / message), with a docs link
  surfaced by the screen (not a secret).

## Security (both)

- `permission_callback` enforces the cap; the controller routes through the shared `AdminGuard` (Principle VII
  scope) — no hand-rolled checks.
- Output escaped; the envelope carries only `kind` / `message` / admin-only scrubbed `details`.
- An unauthenticated or non-`manage_options` request is refused (403) — never runs the probe.
