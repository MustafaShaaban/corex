---
title: Settings & feature flags
description: The layered Config engine, the admin settings UI, and feature flags.
---

## Layered config

`Config::get()` resolves a dot-notation key across three layers, first match wins:
**`.env`** (project root) → **WordPress options** → **shipped defaults** (`config/*.php`).

```php
use Corex\Support\Facades\Config;

Config::get('app.name');             // 'Corex' from defaults
Config::get('forms.email.recipient', '');
```

A dot key maps to an option `corex_<key_with_underscores>` and an env var
`KEY_WITH_UNDERSCORES` (`app.name` → option `corex_app_name`, env `APP_NAME`). Shipped
namespaces: `app`, `query`, `security`, `theme`, `features`.

## The settings UI

`corex-config` registers a top-level **Corex** admin menu with a server-rendered settings
screen (brand / mail / forms / captcha). It persists each field to the `corex_*` option
the Config option layer reads — so a saved setting flows into the framework with no extra
wiring.

Two companion screens sit under the same **Corex** menu (all share the `AdminGuard`
capability + nonce check):

- **Add-ons** — enable/disable each Corex add-on (its plugin **and** its feature flag,
  together), dependency-aware (it refuses a toggle that would break a dependency, e.g.
  disabling `corex-ui` while a kit needs it).
- **Setup Wizard** — pick a starter kit and apply it: it enables the kit’s flags, activates its modules,
  and **creates the kit’s pages** (a composed front page + About/Contact, etc.) idempotently. The seeded
  pages are tracked so `wp corex reset` removes exactly them.
- **Data** — a DataViews table of your form **submissions** (and any registered Corex custom-table source),
  with sorting, pagination, and delete. Served by the cap-gated `corex/v1/data` REST routes. **Custom tables
  appear automatically**: mark a Corex-managed table *managed* (register a `ManagedTable` with the
  `ManagedTables` registry) and it shows up here like a post-type list — no admin code, opt-in, with prepared +
  bounded queries (spec 038).
- **Insights** — performance (PageSpeed/Lighthouse) + agent-readiness cards with a Run button (see
  [Insights](./insights.md)).

## Feature flags

`features.*` flags gate optional or edition behaviour through the same layered engine:

```php
Config::enabled('mail_queue');   // false until enabled
// or inject the service:
$flags = $container->make(\Corex\Support\Config\FeatureFlags::class);
$flags->enabled('pro');
$flags->all();                    // ['pro' => false, 'mail_queue' => false, …]
```

Only a truthy value (`1` `true` `on` `yes`) enables a flag. Flip one per-site by an option
(`corex_features_<flag>`) or env (`FEATURES_<FLAG>`) — no code change. Registered flags
live in `config/features.php`: `pro`, `mail_queue`, `dataviews_admin`, `woocommerce_kit`.

:::tip[Free vs Pro]
The Free/Pro split rides on `features.pro` — Free builds leave it off; Pro distributions
enable it. One codebase, gated by a flag.
:::

## The control panel

The Corex settings screen is a **control panel** (spec 044): the settings are grouped into
domains (Branding, Mail, Forms, Captcha, Insights, …), each shown as a card with a status —
**configured**, **needs setup**, or **error** — conveyed by an icon and text (not color alone).
A domain that needs attention shows what is missing and a "how to set this up" link, and the
dashboard carries an **onboarding checklist** of the steps still to do (with an "all set" state
when nothing remains). Status is derived from your existing settings — nothing new is stored.

## Captcha

Pick a driver (none / honeypot / reCAPTCHA v3 / Turnstile / hCaptcha). Key-based drivers show a
**site key** and a write-only **secret**, plus a score threshold and action for reCAPTCHA v3. The
**Test verification** action (provided by the Corex Captcha add-on) probes the configured provider
and reports a specific result — `ok`, `missing_keys`, `invalid_keys`, or `network_error` — through
the standard response envelope. The secret is used only in the outbound probe and never appears in
a response.
