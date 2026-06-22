---
title: Start your first company site
description: The end-to-end path from a fresh CoreX checkout to a named, deployable company website — without getting lost.
---

This page is the **map** for building a real company website on CoreX. It links the detailed pages in
order; read it top to bottom the first time. Throughout, a neutral placeholder stands in for your real
client:

| Placeholder | Value |
|---|---|
| Company name | **Acme Company** |
| Site title | **Acme Website** |
| Local URL | `http://acme.local` |
| Database | `acme` (prefix `acme_wp_`) |
| Generated site slug | `acme-site` |

> Use your own client's name only **inside the generated client site** you create with `make:site` — never
> in the CoreX framework repo, its docs, or examples.

## Three things that are easy to confuse

CoreX has three distinct layers. Keeping them straight prevents most early mistakes:

1. **The CoreX framework repo** — this checkout (`theme/`, `plugins/*`, `addons/*`). You rarely edit it for a
   single client.
2. **The local WordPress install** — a throwaway WordPress in `./wp` (git-ignored) that *runs* the framework
   so you can see it. Its URL/DB/title are just local dev values.
3. **The generated client site** — a **site plugin + site theme** created by `wp corex make:site`, with its
   own namespace and prefixes. **This is where your client's brand, structure, pages, and content live.**

## The path

### 1. Install CoreX locally

Pick one local stack — you do **not** need both:

- **WAMP / XAMPP (Windows)** — a normal local Apache/MySQL/PHP stack. → [WAMP / Apache + WP-CLI](/getting-started/wamp-apache/)
- **Docker / wp-env** — containerised, useful for CI-like isolation and team consistency, but **optional**.
  → [wp-env / Docker](/getting-started/wp-env-docker/)

> **Docker is optional.** If WAMP/XAMPP is working, you can build, run, and deploy a company site without
> Docker. Reach for wp-env when you want an isolated, reproducible environment (e.g. CI parity).

### 2. Use a named local URL and database for the company site

The setup script's defaults create a generic CoreX dev install (`http://corex.local`, DB `corex`, title
`Corex`). `corex` is only a **default example name**, not a required one. For a real client, create a
**named** local site so it reads like the project:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\setup-wordpress.ps1 `
  -SiteUrl http://acme.local `
  -Title "Acme Website" `
  -DbName acme `
  -DbPrefix acme_wp_
```

Add the host and point your WAMP vhost `DocumentRoot` at the WordPress directory:

```txt
# hosts file
127.0.0.1 acme.local
```

```txt
# Apache vhost DocumentRoot
C:\wamp64\www\corex\wp
```

The full named-site walkthrough (including a **safe reset** if you already ran the default `corex` setup) is
in [WAMP / Apache + WP-CLI → Creating a named local company site](/getting-started/wamp-apache/#creating-a-named-local-company-site).

### 3. Verify CoreX boots

```bash
wp --path=wp corex doctor
```

A green readiness report means the framework booted. If it does not, fix that **before** generating a site.

### 4. Understand the required foundation

Four plugins are the always-on framework foundation. They are **not** optional add-ons and do not appear on
the Add-ons screen:

- `corex-core` · `corex-blocks` · `corex-config` · `corex-forms`

### 5. Understand the optional add-ons

Everything else is an **add-on** you enable by need — see the full tiering in
[Required, recommended & optional add-ons](/guides/free-core-vs-pro/#add-on-tiers). In short:

- **Recommended for a normal company site:** `corex-ui`, `corex-kit-company`, `corex-media`
- **Optional by need:** `corex-email`, `corex-newsletter`, `corex-captcha`, `corex-careers`, `corex-bookings`
- **Only with WooCommerce:** `corex-kit-woo`

> You do **not** need to enable every add-on to start a site. The Add-ons screen (Corex → Add-ons) badges each
> one with its tier and links to its documentation.

### 6. Generate the company site

```bash
wp corex make:site Acme
```

This scaffolds `plugins/acme-site/` (app code, `AcmeSite\` namespace) and `themes/acme/` (presentation), plus
team/agent governance files. Details: [Build a client site](/guides/client-site/).

### 7. Apply the Company Site Kit (where appropriate)

The Company Kit composes CoreX UI patterns into starter company pages and a front page. Apply it, then adopt
and edit the result in the client theme. Details: [Company Site Kit v1](/guides/company-kit/).

### 8. Customize the client theme — not CoreX internals

This is the rule that keeps your work upgrade-safe:

- **Brand restyling** (colours, fonts, spacing) → client `theme.json` tokens / a style variation. **No
  framework edits needed.**
- **Structural header/footer or layout changes** → override the template parts in the **client theme**
  (`themes/acme/parts/`, `themes/acme/templates/`).
- **Do not edit CoreX framework internals for one client.** See
  [CoreX UI, Company Kit & client ownership](/guides/company-kit/#what-each-layer-owns) for exactly which
  layer owns what, and where client header/footer overrides belong.

### 9. Fonts

Final approved client brand fonts should be **source-controlled in the client theme** and registered through
its `theme.json` / a style variation — reproducible across environments. WordPress 7's **Appearance → Fonts**
(Font Library) is fine for temporary testing and editor-managed additions, but don't rely on manually uploaded
fonts for production brand identity unless the migration is documented. Only commit/upload fonts you are
licensed to use. See [Typography & fonts](/guides/branding/#fonts).

### 10. Build, package, and deploy

The local `./wp` is a **dev runtime** — it contains symlinks/junctions and dev artifacts and is **not** a
deployment artifact. Build a clean WordPress tree and deploy that. See
[Deploy & distribute](/guides/deployment/).

## Optional: the documentation site

The CoreX **docs-app** (this site) is optional — it is a searchable team docs site, **not** required to run
CoreX or to start a company site. You can read the docs three ways:

- **No docs app** — read `README.md`, `docs/en/**`, and the docs-app Markdown sources directly in the repo or
  on GitHub.
- **Dev server** — `cd docs-app && npm install && npm run dev` → `http://localhost:4321`.
- **Static WAMP vhost** — `cd docs-app && npm install && npm run build`, then point an Apache vhost
  `docs.corex.local` at `docs-app/dist` (add `127.0.0.1 docs.corex.local` to your hosts file) →
  `http://docs.corex.local`.

If you host the docs, tell the CoreX admin where they live so the **Add-ons → Documentation** links point at
your docs site instead of the GitHub source: set the `docs.base_url` config key (e.g. `http://docs.corex.local`)
or filter `corex_docs_base_url`. With no base configured, those links safely open the docs source on GitHub —
they never resolve against the client site's own domain.

## Next

- [WAMP / Apache + WP-CLI](/getting-started/wamp-apache/)
- [Build a client site](/guides/client-site/)
- [Company Site Kit v1](/guides/company-kit/)
- [Deploy & distribute](/guides/deployment/)
- [Using AI agents safely](/guides/ai-agents/)
