# Corex Config

Corex's product identity + (forthcoming) settings/dashboard. Part of the free core.

## Brand identity

Corex ships a scalable **SVG logo** (`assets/corex-logo.svg`) — navy `#0B1F3B` + cyan `#00C2FF`, a
layered-core mark with a "Corex" wordmark.

## Admin branding

`AdminBranding` applies the Corex **product** brand in wp-admin (kept separate from any client site's
look — client sites stay neutral):

- the **login page** logo (the Corex SVG),
- the login link → the site home (or `brand.login_url`),
- the **admin footer** → "Powered by Corex" (or `brand.footer_text`).

All overridable via the Config engine (`brand.logo_url`, `brand.login_url`, `brand.footer_text`).

## Settings dashboard

A top-level **Corex** admin menu → a settings screen for **brand / mail / forms / captcha**. Each field
is a Config dot-key, so saving persists to the prefixed option the **Config engine reads** — the framework
consumes settings with no extra wiring (e.g. saving `mail.from.address` is what `WpMailDriver` reads).
Saving is nonce + `manage_options` gated and sanitized.

```php
$store = $container->make(Corex\Config\Settings\SettingsStore::class);
$store->save('brand.footer_text', 'Powered by Acme');   // → option corex_brand_footer_text
Config::get('brand.footer_text');                        // 'Powered by Acme'
```

## Tests

```bash
composer test              # headless: branding service + the settings registry/form + the bundled SVG
composer test:integration  # real ./wp: a saved setting is read back through the Config engine
```

> The **React/DataViews UI** (DataViews tables for submissions/subscribers/applications, the setup wizard,
> a health-check runner) is the deferred upgrade — it needs a Node build + a browser to author and verify.
> The current settings screen is server-rendered (Settings-API style) and fully testable.

> The rendered admin appearance (the login page showing the Corex logo) is a browser check. The full
> **settings/dashboard UI** (React/DataViews) is spec 017 and needs a Node build + a browser to author.
