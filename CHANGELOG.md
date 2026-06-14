# Changelog

All notable changes to Corex are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project adheres to
[Semantic Versioning](https://semver.org/) (pre-1.0: the API may still move).

## [0.26.0] — 2026-06-14

Closeout + design language — reconciling the platform claims with the code (spec 053) and turning the thin DLS
catalog into a full, native-first Design Language System (spec 054).

### Added

- **make:site `--starter` slice (053):** `wp corex make:site --starter` scaffolds a runnable example slice
  (model → repository → service → controller-on-envelope → block → option → test + `REMOVE-EXAMPLE.md`) plus a
  starter block theme (`@wordpress/scripts` build, `Assets` helper); `--minimal` and the default omit it.
- **Data admin UI (053):** the `corex-config` Data screen now ships search, source/form filter, sortable headers,
  pagination, a CSV-export button, a detail drawer, and loading/error/empty states over the existing query/detail
  routes (built on pure `dataClient.js` helpers).
- **Captcha Test button (053):** `corex-captcha` ships `captcha-admin.js` wiring the Test button to
  `POST /captcha/test` with a classified, secret-safe result message; Insights "Run check" surfaces failures.
- **Foundations tokens (054):** `theme.json` gains the genuinely-missing `custom.motion` (duration + easing),
  `custom.focus` (width/color/offset), and `custom.z` (z-index scale) groups as runtime CSS custom properties.
- **`corex/modal` block (054):** the one justified new block — a native `<dialog>` with `aria-labelledby`,
  focus-trap, ESC/backdrop close and focus return; degrades without JS; token-only (consumes the focus + z tokens).
- **DLS block styles + skeleton (054):** `register_block_style` entries for card / section / striped-table /
  button-secondary / button-ghost / empty-state, plus a token-only `.corex-skeleton` loading utility.
- **Patterns + page templates (054):** section-header, content-split, stats, FAQ, and latest-news patterns
  (composing only real blocks, drift-tested) + `page-landing` / `page-contact` / `page-form` FSE templates.
- **Design-system documentation (054):** the `DesignSystemCatalog` expanded to the full six-category taxonomy with
  a `mechanism` field (drift-checked both directions), a published gap analysis, and a docs-app design-system
  section (foundations + a page per component with when-to-use / when-not-to-use + patterns + templates).

### Changed

- **Honest README + reconciled status (053):** the README was rewritten as a truthful entry point and stale
  PROGRESS / 045 / 049 completion checkboxes were corrected; a documentation-in-every-PR rule (§D.5) was added.

### Notes

- 563 Pest + 55 Jest green; docs build 268 pages. Guard Gate clean across both specs. DECISIONS #87–#88. The
  native-first finding (most candidate "components" are WordPress core blocks or Corex block styles, not new
  blocks) is the deliberate scope outcome. The modal's visual a11y sweep runs in the spec-052 E2E workflow.

## [0.25.0] — 2026-06-14

The "platform" release — the leap from a framework to a platform you build client sites on with a team + AI agents
(roadmap specs 043–052, all merged via PR/CI).

### Added

- **Response contract + frontend runtime (043):** `Corex\Http\ResponseEnvelope` + `EnvelopeResponder` and a
  buildless `window.Corex` runtime (api/forms/loading/notices); forms + Insights + Data migrated onto it.
- **Admin control panel (044):** per-domain status cards + an onboarding checklist; captcha config + a Test
  verification action; specific PageSpeed diagnostics (local-URL detection); rich add-on manifests; authorship.
- **Data management pro (045):** search / filter / sort / paginate, CSV export, a readable detail view, and a
  `SubmissionStore` storage seam.
- **REST resources & headless (046):** `wp corex make:api-resource`, `routes:list`, `api:docs` (OpenAPI), headless
  docs (nonce / application-password auth).
- **Asset manager & environments (047):** `AssetManager` url/path/version helpers with per-environment cache-busting
  (filemtime / manifest hash / version) + `assets:doctor` / `cache:clear`.
- **Media optimization (048):** an optional `corex-media` add-on — WebP on upload (original preserved) + an
  optimized `<picture>` helper (lazy / async / LCP / srcset) + an advisory image-support probe.
- **make:site client-site platform (049):** `wp corex make:site` scaffolds a correctly-namespaced client plugin +
  theme + governance (AGENTS/CLAUDE, the client-only edit boundary, one-feature-one-PR, AI/cache `.gitignore`).
- **Team ops & distribution (050):** `compliance:check` (enforces the client/framework boundary), `package:update`
  (the spec-034 release manifest), `docs:sync` / `docs:serve`, and Azure DevOps deployment docs.
- **Design Language System (051):** a drift-checked catalog (Components/Blocks/Patterns/Templates/Guidelines) in
  `corex-ui` + new `corex/alert` + `corex/badge` components.
- **Visual & E2E in CI (052):** a Playwright E2E workflow + a console-error sweep (the item-20 gate) + the
  browser-verification Definition of Done.

### Notes

- 544 Pest + 40 Jest green; Guard Gate clean across all ten specs. DECISIONS #77–#86. Browser/live behaviour runs in
  the new E2E workflow (nightly + on-demand) and locally via wp-env.

## [0.24.0] — 2026-06-13

Connectivity — making kit activation visible and transparent, from a user-driven deep review that found the
framework "felt disconnected" (enabling kits seemed to do nothing; submissions were hard to find).

### Added
- **Prompt-to-apply kit activation** (spec 042): enabling a kit add-on (Corex → Add-ons) now surfaces a
  dismissible prompt previewing exactly what applying would do (pages created / filled in / left unchanged, the
  front page, the modules) — **read-only** until you choose **Apply**, which runs the one shared apply path and
  shows a "what changed" summary. A corex-core `KitProvisioner` seam (with a `NullKitProvisioner` default) lets the
  Add-ons screen and dashboard drive activation without depending on a kit add-on (Principle IX).
- **Corex dashboard "Site status" card** (spec 042): shows which kits are applied, the live contact-submission
  count linked to **Corex → Data**, and the current front-page status — with an actionable empty state, degrading
  gracefully when the forms add-on or kit framework is inactive.
- **Block-assets health check** (spec 040): `BlockAssetsProbe` flags any registered `corex/*` block whose
  script/style URL embeds a filesystem path, in **Site Health** and `wp corex doctor`, so a misconfigured mount is
  diagnosed instead of showing as a blank editor panel.

### Fixed
- **Kit apply never leaves a blank front page** (spec 041): page seeding now classifies each declared page
  **create / adopt (populate an empty or placeholder page) / skip (existing user content)** instead of skipping
  any slug that already exists, and the front page is set whenever the declared home was created or adopted. A
  soft reset deletes pages the kit **created** but only **empties** a page it **adopted** (a pre-existing page the
  user owned is never deleted). Applying the Company kit creates the previously-missing About/Contact pages.

### Changed
- **Junction/symlink-safe block asset URLs** (spec 040): `DynamicBlockRegistrar` normalizes each discovered block
  directory back under `WP_PLUGIN_DIR` before `register_block_type` (pure `BlockPathResolver` + `PluginMountMap`),
  so editor/view/style URLs resolve correctly on any mount (Windows junction, POSIX symlink, realpath-resolved/CI).
  A no-op for the already-correct junction case (no regression). Preventive hardening.

## [0.23.1] — 2026-06-12

### Fixed
- **Boot regression** introduced in 0.23.0: `ConfigServiceProvider` failed to boot
  (`Cannot resolve [FieldSections]: it is not instantiable`) because the spec-039 `FieldSections` interface had no
  concrete binding, so the container could not autowire `SettingsForm` for the settings screen. Bind
  `FieldSections` → `SettingsRegistry`, and add a container-wiring regression test that exercises the autowire
  path the unit tests had bypassed.

## [0.23.0] — 2026-06-12

Admin extensibility — making custom data and custom settings pages first-class, from a deep-review follow-up.

### Added
- **Custom tables in the admin** (spec 038): mark any Corex-managed table **managed** (one `ManagedTable`
  declaration) and it appears in **Corex → Data** automatically — browsable, paginated, deletable — like a
  post-type list, with no admin code. The `$wpdb` reader is **prepared** (`%i`/`%d`) and **bounded** (`LIMIT`);
  opt-in, so Corex never enumerates arbitrary tables.
- **Easy option pages** (spec 039): declare an `OptionPage` (title, menu, capability, fields) + register it to get
  a real, secured admin settings screen — rendered by the existing settings controls and saved cap + per-page
  nonce gated, with no form/nonce/save code. Reuse is enabled by a `FieldSections` seam shared with the built-in
  settings. Scaffold one with **`wp corex make:option-page <Name>`**.

### Fixed
- The `wp-dataviews` "dependencies that are not registered" notice on the Data screen — the handle is now declared
  only when WordPress actually registers it (the React already falls back to a plain table).

### Versioning
- All framework plugin/theme headers + `COREX_*_VERSION` constants aligned to 0.23.0 (`wp corex version`).

## [0.22.0] — 2026-06-12

The second round of deep-review work (specs 033–037) plus release hygiene — finishing the initiative a user
review surfaced, and adding a user-requested insights dashboard.

### Added / Changed
- **Design system overhaul** (spec 033): a real `theme.json` design system — expanded palette + state colors, a
  full type + spacing scale, **shadow presets** + **radius tokens**, `styles.elements`, and an **Editorial**
  style variation. The card blocks gained depth (token-only).
- **Self-update + distribution** (spec 034): Corex updates through WordPress's own plugin-update flow (an
  `Update URI` + a configured manifest endpoint), **fail-safe** and never phoning home unless configured. A
  documented **safe-edit boundary**: updates replace framework files only — never `corex-app/`, `brand.json`,
  content, or data.
- **Block library v2** (spec 035): five new inline-edited, server-rendered blocks — **hero, cta, team, gallery,
  tabs** — with media-library images and a **no-JavaScript** CSS-only tabs widget. Enough to build a full
  landing page from Corex blocks.
- **Health, versioning, i18n & OSS hygiene** (spec 036): a **Site Health** integration + `wp corex doctor`
  (non-zero exit on critical); `wp corex version <semver>` stamps every framework header/constant in one step; a
  shared `corex` text domain + `composer i18n:pot`; and `LICENSE`, `CODE_OF_CONDUCT`, `SECURITY`, `.editorconfig`,
  and GitHub templates.
- **Insights dashboard** (spec 037): a **Corex → Insights** screen with two Run-on-demand cards — **Performance**
  (PageSpeed Insights / Lighthouse) and **Readiness** (agent-readiness signals + an optional Cloudflare scan) —
  scored, graded, cached, cap+nonce-gated, and gracefully degrading with no keys.

### Fixed
- Resolved a fatal (`FormsListController` resolved to the wrong namespace, breaking `rest_api_init`/the site
  editor) and made dynamic block registration **idempotent** (no more "already registered" notices on a
  double-firing discovery hook).

### Versioning
- All framework plugin/theme headers + `COREX_*_VERSION` constants are now aligned to the release (0.22.0),
  ending the `0.1.0` drift — stamped with the new `wp corex version` (spec 036).

## [0.21.0] — 2026-06-12

The first round of deep-review fixes (specs 029–032) — addressing real gaps a user review surfaced.

### Added / Changed
- **Inline-editable blocks** (spec 029): the component blocks (`stat`/`testimonial`/`pricing`/`accordion`) are
  now **edited inline on the canvas** (RichText) while staying dynamic; the `corex/form` block **picks a form
  from a dropdown** (the cap-gated `corex/v1/forms` route) instead of a typed slug.
- **Corex → Data admin** (spec 030): a `@wordpress/dataviews` screen to **view and delete form submissions**
  (and any registered custom-table source), via the cap-gated `corex/v1/data` REST. A `DataSource` abstraction
  lets custom tables plug in.
- **Kits build a real site** (spec 031): applying a kit now **creates its pages** (composed front page + About/
  Contact, etc.) idempotently and reversibly (tracked so `wp corex reset` removes exactly them).
- **Modern settings UX** (spec 032): the logo is set with a **media picker** (no URL typing), the captcha driver
  is a **dropdown**, and the configured **logo shows in the settings header**.

## [0.20.1] — 2026-06-12

### Fixed (documentation)
- Refreshed the **docs-app** product site for specs 025–028, which it had not yet reflected:
  - Regenerated the per-class internals reference (`wp corex docs:generate` → 231 pages) so it includes the
    reset CLI, add-on manager, the four new component blocks, `AdminGuard`, and the corrected
    `orderBy`/`orderByNumeric` signature.
  - **CLI guide**: documented `wp corex docs:generate` and `wp corex reset` (soft + gated full).
  - **Blocks guide**: listed the built-in `corex/*` library, including `stat`/`testimonial`/`pricing`/`accordion`.
  - **Configuration guide**: documented the Add-ons and Setup Wizard admin screens.
  - **Getting-started overview**: cross-links the in-repo Developer & Operations Handbook (`docs/`).

## [0.20.0] — 2026-06-12

### Added
- **Developer & Operations Handbook** (`docs/`, spec 028): an in-repo, GitHub-native Markdown handbook for
  setting up, dockerizing, deploying, and contributing to Corex — built spec-first across 12 phases and split
  by audience from the published `docs-app/` site (which keeps the product docs + the generated class
  reference; the handbook links to it, never duplicating).
  - **Getting started**: five OS guides (Windows WAMP/XAMPP, Linux, macOS, wp-env), grounded in the real setup
    script + `wp-env.json`.
  - **Docker**: a one-command dev stack (`docker-compose.yml` with nginx/php-fpm/MariaDB/redis/mailpit and a
    monorepo-mapping entrypoint) and a multi-stage production `Dockerfile`.
  - **Deployment recipes**: Azure App Service, Azure VM, AWS Elastic Beanstalk, AWS EC2+RDS, and cPanel — each
    covering provisioning, deploy-from-tag, HTTPS, secrets, backups, rollback, zero-downtime, and CI/CD, with a
    Mermaid topology diagram.
  - **Team workflow** (onboarding, git-flow-lite, Conventional Commits, the Spec Kit loop, quality gates),
    **cookbooks** (Woo detect-and-defer, multisite, headless, AI-agent flows, paid add-ons), **troubleshooting**,
    and **contributing**.
  - A **glossary**, a **translation-memory** of locked English terms, and an `en/` → `ar/` placeholder mirror
    for the future Arabic translation phase.
- Docker/CI config at the repo root (`docker-compose.yml`, `Dockerfile`, `docker/`, `.dockerignore`) — dev/deploy
  tooling only; no framework runtime dependency added.

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
