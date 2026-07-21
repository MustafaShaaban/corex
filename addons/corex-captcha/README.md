# Corex Captcha

Verify an anti-bot challenge behind a single `Captcha` interface — the configured driver decides.
Optional add-on; requires `corex-core`.

## Drivers

| `captcha.driver` | Behaviour |
|---|---|
| `none` (default) | Always passes (the form honeypot + throttle still guard). |
| `honeypot` | Passes when the hidden field is empty; fails when filled. |
| `recaptcha` | **reCAPTCHA v3** — scored and typed. Verifies `success`, an exact hostname allowlist, the form's server-derived action, token age, one-time use, and a score threshold. **Fail-closed.** |
| `turnstile` / `hcaptcha` | Posts the token to the provider; passes only on a confirmed `success`. **Fail-closed.** |

### reCAPTCHA v3

`recaptcha` resolves to `RecaptchaV3Captcha`, which returns a typed `Corex\Security\ChallengeVerification`
(via the `Corex\Security\VerifyingChallenge` seam) rather than a bare boolean, so a below-threshold score,
an action mismatch, a hostname mismatch, an expired token, or a replay are each reported distinctly. The
verification runs fail-closed and checks, in order: token present → provider reachable → response parseable
→ `success` → hostname (exact allowlist, never substring) → action (server-derived, `corex_form_<slug>` by
default) → age → score → one-time use (replay is checked last, so only a fully valid token is ever recorded).

Settings (**CoreX → Settings → Captcha**): `captcha.site_key`, `captcha.secret`, `captcha.score_threshold`
(default **0.3**), `captcha.allowed_hostnames`, and an optional global `captcha.action`. The client script
loads only on pages with a protected form and requests a fresh token per submission. Only protected CoreX
forms are covered; the honeypot always guards. The **site key** may reach the browser; the **secret never
does**.

## Use it

```php
$captcha = $container->make(Corex\Captcha\Captcha::class);  // the configured driver

if (! $captcha->verify($token)) {
    // reject the submission
}
```

Configure via the Config engine: `captcha.driver` and `captcha.secret` (option `corex_captcha_driver`
/ `corex_captcha_secret`, or env). The secret is **never logged**; remote verification is fail-closed —
a missing secret, a transport error, or a non-success response all return false.

## Test verification button

On **Corex → Settings**, next to the captcha secret field, this add-on adds a **Test verification** button
(`assets/captcha-admin.js`, enqueued only on that screen). It POSTs to `corex/v1/captcha/test`
(`manage_options` + nonce) and shows a classified, actionable result inline — `ok`, `missing_keys` (naming the
key to add), `invalid_keys`, `network_error`, or `not_applicable`. The probe runs server-side and the button
renders only the result's status + message, so **no secret ever reaches the browser**.

**Enabling** this add-on gives you the captcha drivers, the verification endpoint, and its Test button;
**disabling** it removes the button and the `corex/v1/captcha/test` route. Required config for the key-based
drivers (reCAPTCHA / hCaptcha / Turnstile): a provider site key + secret; the honeypot driver needs no keys.

## Tests

```bash
composer test   # headless: each driver + the resolver (the provider HTTP call is stubbed)
```
