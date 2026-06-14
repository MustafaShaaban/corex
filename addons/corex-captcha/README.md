# Corex Captcha

Verify an anti-bot challenge behind a single `Captcha` interface — the configured driver decides.
Optional add-on; requires `corex-core`.

## Drivers

| `captcha.driver` | Behaviour |
|---|---|
| `none` (default) | Always passes (the form honeypot + throttle still guard). |
| `honeypot` | Passes when the hidden field is empty; fails when filled. |
| `recaptcha` / `turnstile` / `hcaptcha` | Posts the token to the provider; passes only on a confirmed `success`. **Fail-closed.** |

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
