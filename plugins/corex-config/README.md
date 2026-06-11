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

## Add-ons screen

A **Corex → Add-ons** submenu lists every Corex add-on with its state (Active / Inactive / Not installed) and,
where it has one, its feature flag. Enabling or disabling an add-on toggles **its plugin and its feature flag
together**. The screen is **dependency-aware**: it refuses to disable an add-on an active add-on requires
(naming the dependent), and refuses to enable one whose dependency is inactive (naming the missing
dependency) — so a toggle can never leave the site broken.

The decisions are the pure, unit-tested `Corex\Config\Addons\AddonRegistry` + `AddonManager` (the kit add-ons
require `corex-ui`, mirroring the blueprints); the `AddonsScreen` only renders + gates (via the shared
`Corex\Security\Admin\AdminGuard`, cap + nonce) and delegates the plugin/flag writes to `AddonActivator`.
Companion to the setup wizard (which composes a whole kit at once).

## Tests

```bash
composer test              # headless: branding + settings + the add-on registry/manager dependency rules
composer test:integration  # real ./wp: a saved setting read back; the add-on activator flag sync
```

> The **React/DataViews UI** (DataViews tables for submissions/subscribers/applications, the setup wizard,
> a health-check runner) is the deferred upgrade — it needs a Node build + a browser to author and verify.
> The current settings screen is server-rendered (Settings-API style) and fully testable.

> The rendered admin appearance (the login page showing the Corex logo) is a browser check. The full
> **settings/dashboard UI** (React/DataViews) is spec 017 and needs a Node build + a browser to author.
