# Changelog

All notable changes to Corex are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project adheres to
[Semantic Versioning](https://semver.org/) (pre-1.0: the API may still move).

## [0.19.0] — 2026-06-11

The forward specs 025–027, each built spec-first via the full Spec Kit flow, tested, guarded, verified on
real WordPress, and merged via its own PR (CI green).

### Added
- **`wp corex reset`** (spec 025): return a Corex site to a clean state. **Soft** mode deactivates Corex
  add-ons, clears `corex_*` options + feature flags, and removes the wizard-seeded demo — touching only
  Corex's footprint. **Full** mode (`--hard`) wipes the DB to a fresh Corex starter, gated behind a typed
  `--yes-i-mean-it` safeguard (+ WP-CLI confirm) so it never auto-runs. `--dry-run` previews either mode.
  Pure `ResetPlanner` + fail-closed `ResetGate`; the wipe lives only in `ResetExecutor`.
- **Corex Add-ons screen** (spec 026): a "Corex → Add-ons" admin screen (in `corex-config`) to enable/disable
  each add-on — toggling its plugin and feature flag together — with dependency awareness (refuse + explain,
  no silent cascade). Pure `AddonRegistry` + `AddonManager`; gated by the shared `AdminGuard`.
- **New `corex/*` component blocks** (spec 027): `corex/stat`, `corex/testimonial`, `corex/pricing`, and
  `corex/accordion` — server-rendered, token-only, RTL, accessible (the accordion uses native `<details>`,
  no JS). Auto-discovered with no engine change.

## [0.18.0] — 2026-06-11

The "Finish Corex" initiative — brought into full spec-first compliance (retrospective specs 018–024)
and remediated (P1–P6), merged via PR #1 with CI green.

### Added
- **Front-end build pipeline** (spec 018): `@wordpress/scripts` across npm workspaces; every `corex/*`
  dynamic block now has editor-side registration (`registerBlockType` + `ServerSideRender` +
  InspectorControls), compiled token-only styles + auto RTL, and a "Corex" inserter category.
- **CLI** `wp corex make:block` + `wp corex docs:generate` (spec 019): a one-name dynamic-block
  scaffolder and an AST-based (no class loading) per-class internals reference generator.
- **Shared form validation + flexible builder** (spec 020): one PHP schema is the source of truth —
  exported to the block, mirrored by `validation.js`, re-validated server-side (authoritative); a
  per-type `FieldRenderer` (SRP) renders every input type accessibly + token-only with whitelisted attrs.
- **QueryBuilder complex queries + feature flags** (spec 021): `orWhere`/`whereMeta`/`whereBetween`/
  `whereTax`/`whereDate`/`search`/`orderByNumeric`/`paginate` (pure, capped, value-bound) and a
  feature-flag layer (`config/features.php` + `FeatureFlags` + `Config::enabled()`).
- **Documentation web app** (spec 022): Astro + Starlight under `docs-app/` — getting-started, guides,
  architecture, FAQ, troubleshooting + the generated reference, client-side Pagefind search, RTL-ready.
- **Portfolio + WooCommerce site kits** (spec 023): `corex-kit-portfolio` (`corex_project` CPT +
  `corex/projects` block + FSE templates) and a gated, HPOS-safe `corex-kit-woo`; company-kit manifest
  drift-protection.
- **Deferred tail** (spec 024): gated mail queue (Action Scheduler, lazy worker), read-only WP 7.0
  Abilities, and a setup wizard (pure planner + admin-gated screen + `BlueprintActivator`).
- **Tests**: a block-`index.js` Jest test + a root `jest.config.js` scoping JS tests to Corex; an
  environment-gated Playwright E2E smoke (`tests/e2e/`).

### Changed
- `QueryBuilder::orderBy` no longer takes a boolean flag — use the new `orderByNumeric()` (clean-code P3).
- Admin-menu screens (`AdminDashboard`, `SetupWizardScreen`) route their cap + nonce check through the
  shared `Corex\Security\Admin\AdminGuard` instead of hand-rolling it (Principle VII scope, P5).
- `SetupWizardScreen` slimmed to render + gate, delegating side effects to `BlueprintActivator` (SRP).

### Fixed
- Mail-queue worker registers lazily, fixing an early-boot regression that loaded the `corex` textdomain
  too soon (zero-notice boot).

### Governance
- Constitution **v1.2.1** — Principle VII scope clarification (admin screens → `AdminGuard`).
- Retrospective specs **018–024** restore spec-first compliance (Principle X); DECISIONS #56–#58.

## [0.17.0] — 2026-06-10

### Added
- **Admin Dashboard / Settings** (`corex-config`, spec 017): a top-level Corex admin menu + a
  server-rendered settings screen (brand/mail/forms/captcha) that persists to the options the Config
  engine reads. (The React/DataViews UI is the deferred upgrade — needs a Node build + browser.)
- **Corex Brand Identity + Admin Branding** (`corex-config`, spec 016): Corex's SVG logo (navy + cyan)
  + login/footer admin branding, configurable, separate from client branding.
- **Call Request** (`corex-bookings`, spec 015): request-a-call flow → custom table + leader/visitor mail.
- **Careers** (`corex-careers`, spec 014): job CPT + taxonomies, `corex/jobs` block, secure application
  flow (CV validated, stored, notified) + a status pipeline.
- **Newsletter / Subscriptions** (`corex-newsletter`, spec 013): topic subscriptions, double opt-in
  (signed tokens), unsubscribe/suppression, GDPR consent, on-publish trigger.
- **Captcha + Secure uploads** (`corex-captcha` + core, spec 012): a fail-closed captcha driver system
  (honeypot/reCAPTCHA/Turnstile/hCaptcha) + a path-safe upload validator.
- **Custom Tables + TableRepository** (core, spec 011): a schema builder + `dbDelta` migrator + typed
  `TableRepository` (casts), the foundation for subscribers/applications/bookings.
- **Company Website Kit** (`corex-kit-company` + theme, spec 010): universal FSE templates + a Blueprint
  manifest composing the Corex blocks/patterns.
- **Corex UI block library** (`corex-ui`, spec 009): server-rendered `corex/*` blocks + token-only section
  patterns under a "Corex" category + a UI manifest.

> Spans v0.9.0–v0.17.0 (one tagged release per spec). The full per-spec history is in `PROGRESS.md` /
> `DECISIONS.md`. Tags: v0.9.0 (UI) · v0.10.0 (Kit) · v0.11.0 (Tables) · v0.12.0 (Captcha/Uploads) ·
> v0.13.0 (Newsletter) · v0.14.0 (Careers) · v0.15.0 (Call) · v0.16.0 (Branding) · v0.17.0 (Admin).
> Plus hotfixes v0.8.1 / v0.9.1 (cross-platform autoload casing, caught by CI).

## [0.8.0] — 2026-06-09

### Added
- **Corex Mail (MVP)** (new `corex-email` add-on, spec 008): a one-line `Mail` API; code-registered
  templates with whitelisted, escaped `{{ path }}` merge variables wrapped in a brand layout (from
  `theme.json`/`brand.json`, RTL-aware); a security gate (header-injection guard + recipient
  validation); fixed/role/dynamic recipient resolution; a `MailDriver` abstraction with a default
  `WpMailDriver` (`wp_mail`, from-identity from Config); and a queryable `corex_email_log` audit (CPT).
- **Mail seam** in corex-core (`Corex\Mail\Mailer` + `MailRequest`): a transport-neutral interface so
  modules send email without depending on a concrete engine (detect-and-defer via the container).

### Changed
- Forms (`SendEmailListener`) now delivers the contact notification through Corex Mail when active
  (templated + logged), falling back to `wp_mail` otherwise — no hard dependency either way.

## [0.7.0] — 2026-06-09

### Added
- **Forms engine** (new `corex-forms` plugin, spec 007): code-defined form schemas, a pure
  headless validator (bail-per-field; `required`/`email`/`max`/`min`/`numeric` returning i18n
  message keys) and schema resolver, a secured REST submit lifecycle
  (`POST corex/v1/forms/{slug}` → nonce → form-shaped sanitize → throttle → honeypot →
  validate → dispatch), store + email listeners (a non-public `corex_submission` CPT via the
  data layer; `wp_mail` to a configurable recipient), an example contact form, and the
  accessible, token-only `corex/form` FSE block with conditional assets.
- **Event seam** in corex-core (`Corex\Events`): `ListenerProvider` + `EventDispatcher` —
  ordered, once-each, best-effort dispatch — reusable by future modules (Corex Mail).

### Changed
- `Http\Middleware\Response::reject()` gained an optional payload argument so a rejection can
  carry a structured body (e.g. per-field validation errors). Backward compatible.

## [0.6.0] — 2026-06-08

### Added
- **Foundation** (specs 001–006): the self-booting engine — PSR-11 container, service-provider
  lifecycle, layered Config, declarative hooks, controller auto-discovery (spec 001); the data
  layer — read-only Models, repositories, ACF-optional field driver, capped QueryBuilder, eager
  loading (spec 002); `wp corex make:*` CLI generators (spec 003); the block engine — convention
  discovery, conditional assets, container-resolved render, Block-Bindings connectors (spec 004);
  the declarative middleware pipeline — `nonce`/`auth`/`throttle`/`sanitize` + `SecurityModule`
  (spec 005); the theme token source + `brand.json` runtime override resolver + style variations
  (spec 006).

[0.8.0]: https://github.com/MustafaShaaban/corex/releases/tag/v0.8.0
[0.7.0]: https://github.com/MustafaShaaban/corex/releases/tag/v0.7.0
[0.6.0]: https://github.com/MustafaShaaban/corex/releases/tag/v0.6.0
