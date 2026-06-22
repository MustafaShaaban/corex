# Changelog

All notable changes to Corex are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project adheres to
[Semantic Versioning](https://semver.org/) (pre-1.0: the API may still move).

## [Unreleased]

## [0.30.0] — 2026-06-23

### Added

- **Pre-site asset & media hardening (spec 062):** the minimum-safe asset + media foundation for building a real
  company website.
  - **First-class asset helpers** — `Corex\Assets\Style` / `Script` / `Image` / `Picture` (facades over the
    `AssetManager`/`BuildManifest`, with an `AssetRegistry` for named theme/plugin/client bases). They resolve
    URL + cache-busting version (manifest-hashed when present), support deps/in_footer/defer|async/module/version
    and a sibling wp-scripts `*.asset.php`, render source-controlled `<img>`/`<picture>`, and **refuse a `.scss`**
    passed to an enqueue helper (SCSS is source only — the compiled CSS is enqueued).
  - **Generated client SCSS/JS/image pipeline** — `make:site --starter` now scaffolds
    `assets/src/{scss,js,images}/` → `assets/{css,js,images}/` with `styles`/`scripts`/`images`/`build` npm scripts
    (project-local `sass` + wp-scripts) and a `functions.php` that enqueues the compiled output through the CoreX
    asset helpers — never hardcoded paths.
  - **WebP activation gate** — a WebP is no longer served just because it exists: after conversion the derivative
    is measured and served only if it is present + valid, dimensions match, and it is smaller than the original by
    at least a configurable threshold (default 5%). The result is tracked per attachment (`_corex_webp` meta), and
    a WebP that came out larger is generated but quietly not served.
  - **WebP reset/cleanup** — `wp corex media reset-webp [--dry-run] [--all] [--attachment=<id>] [--limit=<n>]`
    deletes only tracked CoreX-generated derivatives (never originals, manually-uploaded WebP, or untracked files),
    clears the meta, and reports counts; deleting an attachment removes only its tracked derivative.
  - **Dist client-asset verification** — `verify:dist` flags a half-built client theme (SCSS/JS source present but
    no compiled `assets/css`/`assets/js`), so a deploy can't package an unbuilt theme.
  - Docs draw the line: `Corex\Assets\*` is for source-controlled theme/plugin/client assets, `Corex\Media\*` is
    for Media Library uploads.

## [0.29.0] — 2026-06-23

### Added

- **Team-safe company-site readiness (spec 061):** CoreX is now safe for a team (and AI agents) to build real
  client sites on.
  - **Role Gate** — every session classifies into one of four modes (CoreX Framework / Client Site / Deployment /
    Docs-Planning) that decide *where* it may edit, documented in root `AGENTS.md` / `CLAUDE.md` /
    `COREX-WORKING-GUIDE.md` §G, the team-workflow docs, copy/paste start prompts, and the generated client stubs.
    Rule hierarchy: Role Gate (where) → Spec Kit (what) → Guard Gate (safe to ship) → UI/UX ProMax (UI good
    enough). A standard SUMMARY/…/NEXT STEP handoff format is required of every response.
  - **Team source layout** — framework source stays in `plugins/`/`addons/`/`packages/`/`theme/`/root `specs/`/
    `docs/`/`docs-app/`; client source lives in `sites/<client>/` (`<client>-site` + `<client>-theme` +
    governance + specs/docs). `wp/wp-content/` and `dist/` are runtime/build output, never edited as source.
  - **Shared-host `dist` builder** — `npm run build:dist [-- --client=acme] [--dry-run]` assembles a flat,
    deployable WordPress tree in `dist/` from repo source (de-symlinked), excluding dev/runtime/secret paths, with
    a `corex-release.json` manifest; `npm run verify:dist` checks it. `dist/` is git-ignored, never committed.
  - **Azure Pipelines** (`azure-pipelines.yml`) — builds `dist/` + publishes the artifact, then an approval-gated,
    parameterised SFTP deploy stage (secrets only, production runtime files protected). GitHub Actions stays the
    PR/quality gate. Deployment docs + checklists under `docs/en/05-deployment/`.
  - **CoreX Media settings + regeneration** — a Media settings panel (enable WebP, quality, JPEG/PNG toggles, a
    live GD/Imagick/WebP/uploads-writable read-out; originals always preserved) with filter seams + sanitization;
    `wp corex media regenerate-webp [--dry-run] [--limit] [--attachment]` backfills WebP siblings (never deletes
    originals); a `corex_media_optimize_image` delivery seam blocks can opt into for `<picture>` output.
  - **`make:site` flat client layout** — generates `<slug>-site/` + `<slug>-theme/` directly under the output dir
    (so `--path=sites/acme` → `sites/acme/acme-site` + `acme-theme`), with header/footer/front-page override
    scaffolding (brand-via-tokens vs structure-in-client-theme) and, in `--starter`, a project-local image
    pipeline (`assets/src/images/` → built `.webp` via `npm run images`). Older nested sites keep working.

## [0.28.0] — 2026-06-22

### Added

- **CoreX admin design implementation landed (spec 060, milestone M6 — merged via PR #59):** the approved CoreX admin
  design is now applied end to end and render-verified (Playwright, dark + light) against the design captures:
  - **Real `wp-login.php`** carries the CoreX login design for logged-out users — branded mark/wordmark, ambient
    grid + glow, a separate SSO slot (truthfully disabled, "SSO is not configured yet.") above the form card with an
    "or" divider, leading user/lock field icons with the password reveal kept on the right, a brass sign-in button,
    and entrance/focus motion that respects `prefers-reduced-motion`. The login is dark-first; the saved CoreX
    appearance (System/Light/Dark) controls it for logged-out users. WordPress still owns all authentication.
  - **Every CoreX admin screen** (Overview, Settings, Add-ons, Data, Insights, Setup Wizard, and declarative option
    pages) uses the shared, full-bleed shell — the dark product surface fills the wp-admin content area with the
    six-item `COREX FRAMEWORK` rail; scoped to CoreX screens only, no global wp-admin restyle.
  - **Data explorer:** the Sources/Models rail drives the active source, a real per-day 14-day records chart (from
    submission timestamps, zero-filled), a derived field schema, search/source-filter/reset/export, row selection
    with a bulk delete (nonce-confirmed), an accessible record drawer (focus trap, Escape, focus return), and a
    designed table. Provider-/value-aware controls use an accessible custom listbox readable in dark mode.
  - **Settings tabs** (Brand → Mail → Forms → Captcha → Insights) with a live admin appearance setting, admin-logo
    preview, footer-text value, and an SSO-slot toggle. Asset CSS/JS is filemtime-versioned so edits bust the cache.
  - **Captcha settings are provider-specific** and reactive to the selected driver — **None** (disabled notice),
    **Honeypot** (no-key spam-trap, no keys shown), **reCAPTCHA** (site/secret keys + v3 score/action + Google
    references), **hCaptcha** (keys + hCaptcha references), and **Cloudflare Turnstile** (keys + Cloudflare
    references); each driver shows only its own fields, descriptions, and official links. Secrets stay write-only.

### Fixed

- **Render-verified CoreX admin visual fidelity (spec 060, milestone M6):** every CoreX admin surface was rendered
  (headless Chrome, authenticated, dark + light) and compared against the approved design captures, exposing defects
  the earlier source-only pass missed. Form inputs no longer render with WordPress's white field background and
  buttons no longer render in WordPress blue — controls (inputs, selects with a single RTL-aware chevron, textareas,
  primary/secondary and Gutenberg buttons) are now styled with specificity that wins over core wp-admin CSS, with a
  brass focus ring and dark input wells. Card and section headings take an explicit CoreX text colour (a contrast/WCAG
  fix on dark surfaces). The `--corex-admin-*` adapter (dark + light) was realigned to the approved tokens, the Data
  table gained monospace uppercase headers and themed pagination, and the login screen gained an ambient grid backdrop
  with a brass checkbox and a muted reveal control. Truthful-state, security, and markup contracts are unchanged.

### Added

- **Company-site readiness & onboarding:** a central docs URL resolver (`Corex\Config\Docs\DocsUrl`) so the
  Add-ons screen's "Documentation" links resolve to an absolute URL — the `docs.base_url` config key (filterable
  via `corex_docs_base_url`), else the framework's canonical GitHub docs source — and never resolve against the
  active client WordPress domain. Add-on **tier badges** (Recommended / Optional / Site kit / Requires
  WooCommerce) plus a "Where to start" note (the always-on foundation: corex-core/blocks/config/forms) make
  add-on choice clear without weakening the truthful state model. New onboarding docs: an end-to-end *Start your
  first company site* guide and a *Using AI agents safely* guide, named-local-site (WAMP) + safe-reset, add-on
  tiers, what-each-layer-owns (CoreX UI vs Company Kit vs parent theme vs generated client theme) + header/footer
  ownership, the WordPress 7 Font Library strategy, a flat-`dist/` deployment warning (never ship the symlinked
  `wp/`), a "Start here" README map, and docs-app-is-optional guidance. Neutral *Acme* placeholders throughout.

- **CoreX Admin Product Experience (spec 060, milestone M6):** the shared CoreX wp-admin now presents a truthful
  add-on/settings state model. `AddonStatus` + `AddonStatusResolver` resolve every add-on to one of seven honest
  states (not installed / inactive / feature off / dependency missing / WooCommerce missing / Pro required / active);
  the Add-ons screen renders each as an accessible labelled badge and offers enable/disable for installed add-ons
  only (no marketplace/install-from-admin). Settings sections reflect the same model — not-installed sections are
  hidden behind a notice, inactive sections are disabled, active-but-unconfigured sections prompt for configuration —
  with reCAPTCHA as the worked example, and **secret fields (captcha secret, API keys) are now write-only** (never
  rendered back; an empty submit preserves the stored secret). The admin styling consumes only the scoped
  `--corex-admin-*` adapter and loads only on CoreX admin screens — no global wp-admin restyle, no public-frontend
  branding. Docs: design-system → Admin experience.

- **Corrective full CoreX admin visual implementation (spec 060, milestone M6):** PR #58 is recorded as the
  truthful-state foundation, not the completed visual rollout. The corrective implementation applies the approved
  design across native WordPress login, Overview, Add-ons, Data, Settings, Setup Wizard, and Readiness/Insights. It
  adds the `COREX FRAMEWORK` grouping, shared page shell/headers, stat and add-on cards, settings sections, data
  tooling/states, readiness cards, setup progress, and visible permission-denied/empty/error/success/warning/
  disabled states. The layer is dark-first with a complete light mapping, responsive, RTL-first, keyboard/focus
  visible, reduced-motion aware, and allow-listed to CoreX admin/login assets only.

- **Company Site Kit v1 page coverage (spec 059, milestone M4):** the `corex-kit-company` `CompanyBlueprint` now
  provides the full v1 content-page set (Home, About, Services, Single Service, Work, Case Study, Industries, FAQ,
  Blog, Team, Testimonials, Locations, Contact, Privacy/Terms/Cookie, Maintenance), composed only from registered
  `corex/*` patterns + the M3 header/footer + core blocks (token-only, RTL, accessible). System surfaces stay on the
  universal templates. Demo content levels (`minimal`/`standard`/`full`) keep the same structure with varying depth;
  each page carries editable, plugin-compatible SEO starter metadata. Applied through the existing preview/apply
  provisioning with safe `reset`/`adopt`/`skip`/`conflict` handling. Section blocks with no dedicated pattern yet
  (services/team/case-study/locations grids) are the recorded M5 batch. Docs: guides → Company Site Kit v1.

- **Header, mobile navigation, mega menu, and footer system (spec 058, milestone M3):** reusable FSE template parts
  and block patterns — six header variants (`corex/header-*`), four mega menus (`corex/megamenu-*`, native
  `<details>` disclosures), and six footer variants (`corex/footer-*`) — built only from WordPress core blocks and
  the M2 brand tokens, registered under a new **CoreX** pattern category. The core navigation block supplies the
  accessible mobile overlay; a small buildless behavior script adds single-open mega menus, Escape/outside-click
  close with focus return, and a transparent→solid sticky-header state. Keyboard/focus/Escape/outside-click,
  WCAG 2.2 AA focus, RTL (logical properties), `prefers-reduced-motion` gating, and a no-JS fallback throughout.
  Assets load only where a CoreX header/footer renders (Principle VI); three layout-only `theme.json` custom tokens
  added, no new brand values. No builder, commerce logic, kit pages, or Pro scope. Docs: design-system → Navigation
  & footer.

- **CoreX brand tokens and logo system (spec 057, milestone M2):** canonical `theme.json` color/typography token
  vocabulary with accessible dark/editorial style variations and RTL/mixed-script typography; a four-file self-hosted
  font package (Space Grotesk, JetBrains Mono, IBM Plex Sans Arabic) with provenance, `font-display: swap`, and no
  unmeasured preload; the approved Core X logo package (five SVG variants + provenance manifest); a `brand.json`
  brand-override validator that enforces complete wholesale list replacement with one-release compatibility aliases;
  a scoped `--corex-admin-*` admin token adapter loaded only on CoreX admin screens; and updated design-system and
  branding documentation. Client brandability is preserved — CoreX product identity does not become a hardcoded
  client-site identity. Merged via PR #54.

## [0.27.0] — 2026-06-19

### Added

- **Exposure-aware dependency security (spec 056):** `npm run verify:dependencies` audits Composer, root npm, and
  docs-app npm together, validates exact expiring development-tool exceptions, fails closed on unavailable audit
  evidence, and is enforced by a weekly/on-demand/dependency-change GitHub Actions workflow. High or critical
  shipped-runtime/CI findings cannot be excepted; forced audit downgrades remain prohibited.
- **Stable client readiness (spec 055):** `wp corex readiness` now reports add-on runtime gating, release metadata,
  CI/security repo-file controls and environment-gated GitHub settings, `make:site` validation, deployment profiles,
  native-first component coverage, Free/Core vs Pro boundaries, and multi-agent workflow readiness with exact
  evidence and next actions.
- **Runtime add-on gating:** optional first-party providers are resolved before boot from shared provider metadata,
  activation state, dependency state, installed-file checks, feature flags, and external gates. Disabled or missing
  optional add-ons are excluded before they can register unsafe behavior; Woo remains gated by both Corex state and
  WooCommerce availability.
- **Client-site readiness checks:** generated minimal and starter `make:site` scaffolds are validated for isolated
  client plugin/theme folders, namespace and prefix separation, governance files, specs/docs placeholders, token
  strategy, and starter example files.
- **Deployment and component matrices:** deployment profiles distinguish local passable checks from environment-gated
  shared-host, Azure/container, Docker, and wp-env checks; company-site UI needs are classified by native Corex or
  WordPress mechanisms without starting the final Corex visual redesign.
- **Product boundary matrix:** adoption and trust basics stay Free/Core, while advanced newsletter, bookings,
  careers/ATS, Woo, email/media/data automation, white-label, starter-kit, Azure/DevOps, governance dashboard,
  multi-company identity, and client portal scope are Pro candidates.
- **Repo-owned security controls:** CODEOWNERS, Dependabot, and CodeQL workflow files are present. GitHub branch
  protection, the required CI context, Dependabot security updates, and secret scanning were verified before this
  release.

### Notes

- Release verification passed Composer validation, PHP lint across 392 files, 620 Pest tests with 2239 assertions,
  88 Jest tests across 16 suites, all workspace builds, the 39-page docs build, dependency-policy verification, and
  local WordPress readiness against the v0.26.1 baseline.
- Docker/wp-env, browser automation, and external deployment evidence remain explicitly environment-gated. They are
  not represented as passing release checks.

## [0.26.1] — 2026-06-15

### Fixed

- **Junctioned add-on block assets 403 in the editor (spec 040 gap):** add-ons loaded via Corex's Boot provider list
  rather than WordPress's `active_plugins` (e.g. `corex-careers`, `corex-kit-portfolio`) emitted malformed block
  asset URLs (`…/wp-content/plugins/C:/…/addons/…/style-index.css`) on a symlinked/junctioned dev or CI layout,
  because WordPress only learns a symlinked plugin's real location for plugins it activates itself. A new
  `Corex\Blocks\PluginRealpathRegistrar` replays `wp_register_plugin_realpath()` for every junctioned mount at boot,
  so `plugins_url()` resolves correctly for every add-on. Caught by the spec-052 console-error sweep on its first
  live run; the env-gated Playwright suite was also hardened (WP 7.0 inserter selector, native-validation-aware
  contact-form assertions, `storageState` auth, deterministic editor-ready waits). DECISIONS #90.

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
