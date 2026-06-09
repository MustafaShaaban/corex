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

## Tests

```bash
composer test   # headless: the branding service (logo URL / login CSS / config) + the bundled SVG
```

> The rendered admin appearance (the login page showing the Corex logo) is a browser check. The full
> **settings/dashboard UI** (React/DataViews) is spec 017 and needs a Node build + a browser to author.
