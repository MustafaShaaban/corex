# Contract: Captcha & Insights test buttons (US3)

Wires UI to **existing** diagnostic routes. Secret-safe by construction — the secret never leaves the server.

## Routes consumed (already implemented)

| Purpose | Method + path | Auth | Returns (envelope) |
|---|---|---|---|
| Captcha test | `POST /wp-json/corex/v1/captcha/test` | cap + nonce | `{ ok, data:{ status, message, missingKeys? } }` |
| Insights run | `POST /wp-json/corex/v1/insights/run` | `manage_options` + nonce | `{ ok, data:{ status, message, ... } }` (already wired in `insights.js`) |

## Captcha button (the real gap — new JS)

- **Location**: `addons/corex-captcha/assets/captcha-admin.js`, enqueued by `CaptchaServiceProvider` on the
  settings screen with a localized `{ restUrl, nonce }`. No build step (vanilla over `window.Corex.api`).
- **Behavior**:
  1. On **Test** click → set the button busy/disabled, POST to `/captcha/test` with the nonce.
  2. Render the returned `status`+`message` in a labelled live region (`role="status"`), classified:
     `ok` (success) · `missing_keys` (names which keys via `missingKeys`) · `invalid_keys` · `network_error` ·
     `not_applicable`. Re-enable the button on completion.
  3. **Never** read or render any key/secret field.
- **Missing-key guidance**: when `status==='missing_keys'`, the message tells the admin exactly which key to add
  and where to get it (provider-appropriate).

## Insights (verify + polish only)

- The "Run check" button already exists and POSTs `/insights/run`. This feature **verifies** its message renders
  the `PsiDiagnostic` classification (`local_url` / `http_error` / `quota` / `invalid_key` / `invalid_response`
  / `ok`) with actionable wording and a "recommended, not required" note for a missing optional API key — and
  polishes copy if any case is vague. No new button unless verification finds it absent.

## Invariants

- No secret is ever sent to the client or written to a log (FR-014/SC-005).
- Busy state on in-flight; accessible result announcement; i18n-ready strings (FR-017).

## Test contract

- **Jest** (captcha module): Test click POSTs with nonce + sets busy; renders each `status` message; asserts no
  secret field is read from config; `missing_keys` lists `missingKeys`. (`window.Corex.api` mocked.)
- **Manual/Playwright (env-gated)**: button visible on the settings/insights screens, console-clean.
