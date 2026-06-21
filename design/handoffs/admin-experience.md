# M6 CoreX Admin Product Experience Handoff

**Status:** Approved

**Approved:** 2026-06-21

**Engineering target:** Spec 060 - CoreX Admin Product Experience

## Approval evidence

The product owner supplied the approved CoreX admin design package as the authoritative source
(`F:\Work\Design project questions answered (3)`): **Corex Admin Dashboard**, **Corex Admin - Add-ons & Data**,
**Corex Admin Login & Settings**, **Corex Options Round 2**, and **Corex Addon Logos**. This handoff records that
approved direction inside the repository. It is an input to the engineering spec; it does not authorize
implementation without the reviewed Spec 060. Visual identity is inherited from M2 (Spec 057); this milestone applies
it to the **shared CoreX wp-admin screens only** — never to the public company-site frontends.

## Approved direction

- A calm, dark-first (with a complete light mode) CoreX surface **inside wp-admin**, not a wp-admin reskin: a
  "COREX FRAMEWORK" sidebar group (Overview/Add-ons/Data/Settings) and CoreX-owned screens styled through the scoped
  `--corex-admin-*` adapter; generic wp-admin chrome stays untouched.
- Reuses the M2 tokens and fonts (Space Grotesk display, JetBrains Mono for technical labels) via the admin adapter —
  brass accent, semantic success/warning/danger, raised surfaces, soft borders, focus ring.
- Core components: topbar (breadcrumb + title + search + primary action), stat cards (mono label / value / delta),
  data tables with filter/sort, status badges, add-on cards with a status + enable/disable toggle, settings sections,
  a setup wizard, and a readiness/status screen.
- Add-on philosophy: **"Add-ons self-disable — never a hard dependency — toggle freely."** The admin manages only
  installed add-ons (enable/disable/status/dependency explanation/settings access); it is **not** a marketplace.
- LTR and RTL, light and dark, and responsive admin behavior are all in scope.

## Scope

### Screens

Dashboard (framework overview + stat cards + recent records), Add-ons (cards with truthful state + toggle), Data
(table + filter/sort/export), Settings (state-aware sections incl. captcha/reCAPTCHA), Setup Wizard (guided
first-run), and Readiness/Status (release/CI/environment checks with honest gating).

### Truthful add-on state model

Every add-on resolves to exactly one display state:

- **not installed** — package/plugin absent; cannot be enabled from admin (install is developer/CLI/deployment work).
- **inactive** — installed but the WordPress plugin is not active.
- **feature off** — WP plugin active but the CoreX feature flag is off.
- **active** — active and feature enabled.
- **dependency missing** — active but a required CoreX add-on dependency is unmet.
- **WooCommerce missing** — gated on WooCommerce, which is absent.
- **Pro required** — a future/commercial add-on shown as a disabled, non-actionable state only (no licensing,
  entitlement, or purchase flow).

### Settings consistency

Settings sections reflect the runtime add-on state: not installed → hide advanced settings or show an "add-on not
installed" state; inactive/feature-off → a disabled state, no fields presented as active/usable; active but
unconfigured → "configuration needed"; active and configured → normal settings.

### Captcha / reCAPTCHA (worked example of the rule)

- captcha not installed → reCAPTCHA settings must not appear active.
- installed but inactive/feature-off → reCAPTCHA fields disabled or replaced by a clear disabled state.
- active but keys missing → "configuration needed".
- active and configured → provider settings + a test action.
- Secret values are **write-only** — never rendered back to the screen.

## States (every screen)

Loading, empty, error, success, and permission-denied states are defined for the data-bearing screens; a future-aware
license-expired state is acknowledged for Pro without implementing licensing.

## Accessibility, RTL, responsive

- WCAG 2.2 AA: landmarks, heading order, visible focus (admin focus token), accessible names, status meaning not by
  color alone (text/icon + badge).
- Full keyboard operation; Escape/return-focus for any disclosure/overlay; `prefers-reduced-motion` respected.
- RTL via logical properties (mirrored sidebar/tables/cards); responsive admin layout (sidebar + content) collapses
  gracefully on narrow widths.

## Tokens and scoping

- Consume M2 semantic roles through the existing scoped `--corex-admin-*` adapter (`corex-admin-tokens.css`),
  registered by `corex-core` and enqueued only by CoreX admin screens (Principle VI). No global wp-admin restyle; no
  `--wp--preset--` in the adapter; no CoreX admin branding applied to public frontends.
- Admin assets load only on CoreX screens; no global admin CSS/JS library.

## Explicit exclusions

- Public company-site/frontend design (M3/M4 own that).
- Plugin marketplace / download / install-from-admin behavior (install is developer/CLI/deployment).
- Pro licensing, entitlement, commercial package distribution, white-label, client portal, editor workspace.
- CPT/form/addon/model/table/resource generators.
- Heavy animation in wp-admin.

## Open questions

- Whether the Setup Wizard ships as a full multi-step flow now or a guided single screen in M6 (planning to decide;
  default: a guided single-screen first-run that reuses the existing setup wizard foundation).
- Pro-required surfacing source: a per-add-on descriptor flag vs. the Free/Pro boundary matrix (planning to decide;
  default: an explicit, non-actionable descriptor flag, no licensing).
