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

## Tests

```bash
composer test   # headless: each driver + the resolver (the provider HTTP call is stubbed)
```
