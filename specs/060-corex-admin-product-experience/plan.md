# Implementation Plan: CoreX Admin Product Experience

**Branch**: `spec/060-corex-admin-product-experience` (foundation),
`fix/060-admin-design-implementation` (corrective visual implementation) | **Date**: 2026-06-21 |
**Spec**: [spec.md](./spec.md)

**Input**: spec.md; design input [M6 admin experience handoff](../../design/handoffs/admin-experience.md); built on M2 tokens (Spec 057), the scoped `--corex-admin-*` adapter, and the existing `Corex\Foundation\Addon*` runtime model.

## Summary

Give the shared CoreX wp-admin a coherent, accessible, dark-first (with light) product experience and make every
screen **truthful** about add-on/settings/captcha state. Reuse the existing runtime model: `AddonProvider`
(slug/pluginFile/dependencies/featureFlag/externalGate) and `AddonRuntimeState` (installed/active/flag/gate) already
encode the facts, and `AddonProviderResolver::blockedReason()` already orders them. M6 adds a **pure display-state
resolver** (`AddonStatus` enum + `AddonStatusResolver`) on top, surfaces it in the Add-ons + Settings + captcha
screens, and applies the approved design through the scoped adapter — without restyling wp-admin or touching the
public frontend.

## Technical Context

**Language/Version**: PHP 8.3+; admin screen markup + CSS (logical properties); minimal admin JS for toggles where
core mechanisms are insufficient. WordPress 7.0+.
**Primary Dependencies**: `corex-core` `Foundation\Addon*` model; the scoped `corex-admin-tokens.css` adapter
(Spec 057 US4); existing CoreX admin screens in `corex-config` (Settings/Data/Insights/Dashboard) + `corex-captcha`;
the shared `AdminGuard` (cap+nonce) for state changes.
**Storage**: existing options for add-on enable/disable + settings; no new schema. The resolver is pure (no DB).
**Testing**: Pest (resolver state matrix; settings-section state; captcha write-only + states; asset-scoping;
readiness gating), Jest for any admin toggle JS, Playwright for rendered a11y/RTL where available else
ENVIRONMENT-GATED.
**Target Platform**: wp-admin (CoreX screens) on WordPress 7.0+.
**Performance/Constraints**: admin assets load only on CoreX screens (Principle VI); token-only via the adapter
(Principle V); RTL-first (Principle VIII); declarative security (Principle VII via `AdminGuard`); no optional-plugin
hard dependency (Principle IX); no global wp-admin restyle; no public-frontend impact.
**Scale/Scope**: 6 screens (Dashboard/Add-ons/Data/Settings/Setup/Readiness), 7 add-on states, captcha worked
example, universal states.

## Corrective implementation architecture

PR #58 is the state/security foundation. The corrective branch completes the visual contract using one
registered-but-never-global CoreX admin shell stylesheet in `corex-core`, plus existing screen-specific styles for
Data, Insights, Add-ons, control-panel/settings, and captcha. Every selector is rooted in `.corex-admin`; login uses
the separate `body.login.corex-login` root. All declarations consume `--corex-admin-*` roles from the adapter.

`CorexAdminAssets` owns the allow-list of current CoreX screen hooks and conditionally enqueues the shared shell;
individual screens continue to own their scripts and screen-specific styles. `AdminPage` centralizes the accessible
shell, page header, notices, and permission-denied state so six screens do not drift. Existing state services,
actions, REST routes, and `AdminGuard` remain unchanged. Login remains native WordPress markup/behavior: CoreX adds
only a body class, conditionally enqueued adapter/login stylesheet, safe logo variable, header URL, and footer text.

Current owned surfaces found in the repository: WordPress login; Dashboard/Overview + settings; Add-ons; Data;
Insights (including readiness); Setup Wizard; captcha controls within Settings; declarative CoreX option pages.

## Constitution Check

- [x] **I. Theme is a skin** — N/A (admin is plugin-owned); no admin logic placed in the theme.
- [x] **II. Plugins boot themselves** — admin screens/resolver live in plugins; work in admin context independent of
  the theme.
- [x] **III. Thin controllers, fat services** — the state resolver is a pure service; screens/controllers stay thin;
  no DB in the resolver.
- [x] **IV. Everything injected** — resolver + screens resolved via the container; no `new` of a dependency in a
  method.
- [x] **V. Runtime tokens** — admin visuals via the scoped `--corex-admin-*` adapter mapping M2 roles; no raw
  hex/size/font in CoreX admin CSS.
- [x] **VI. Conditional assets** — the adapter + admin CSS/JS load only on CoreX admin screens; no global library, no
  frontend load.
- [x] **VII. Declarative security** — enable/disable + settings save go through the shared `AdminGuard` (cap+nonce);
  no hand-rolled checks; secrets write-only; output escaped.
- [x] **VIII. RTL-first** — logical properties; mirrored admin layout; Arabic correct.
- [x] **IX. No optional dep is hard** — captcha/Woo/etc. surfaced via state, never required; WooCommerce-missing is a
  state, not a crash.
- [x] **X. Spec is source of truth** — traces to spec.md + the approved handoff.
- [x] **Guard Gate + DoD** — wp/clean-code/test/docs guards per task; tests, i18n, RTL, WCAG 2.2 AA, docs + PROGRESS.

**Result: PASS.** No violations.

## Project Structure

```text
plugins/corex-core/src/Foundation/      # add: AddonStatus (enum) + AddonStatusResolver (pure)
plugins/corex-config/src/
├── Addons/                             # Add-ons screen consuming AddonStatusResolver (states + toggle)
├── Settings/                          # state-aware settings sections
└── assets/{control-panel,data,insights,addons,settings}.css  # scoped design via --corex-admin-*
addons/corex-captcha/src/              # reCAPTCHA settings follow the state rule; secrets write-only
tests/Unit/{Foundation,Config,Captcha}/  # resolver matrix, settings-state, captcha, asset-scoping
docs-app/.../design-system|guides/      # admin experience docs
```

**Structure Decision**: Add the pure `AddonStatus`/`AddonStatusResolver` in `corex-core/Foundation` (next to the
existing model it reads). Surface it in `corex-config` admin screens and `corex-captcha` settings. Visual design is
scoped CSS consuming the existing `--corex-admin-*` adapter; no new global assets. Reuse the existing screens, guard,
and options — extend, don't rebuild.

## Phase 0 — Research

See [research.md](./research.md): resolves (1) state ordering + where `pro_required` sits; (2) reuse of
`blockedReason` logic vs. a new resolver (new pure resolver returning an enum, sharing the ordering); (3) how
settings-section state derives from add-on state; (4) captcha write-only secret handling; (5) admin asset scoping
mechanism; (6) Setup Wizard scope; (7) the Pro-required source.

## Phase 1 — Design & Contracts

- [data-model.md](./data-model.md): `AddonStatus` enum, add-on descriptor, runtime snapshot (existing), settings
  section state, admin screen.
- [contracts/](./contracts/): add-on-state contract (the 7 states + ordering + admin-manages-installed-only),
  settings-state contract (incl. captcha + write-only secrets), admin-visual/scoping contract (adapter-only, no
  global/frontend, a11y/RTL/reduced-motion), readiness-honesty contract.
- [quickstart.md](./quickstart.md): resolver matrix run, settings/captcha state checks, asset-scoping check, with
  ENV-gated browser steps.

### Implementation phasing (mapped to user stories)

1. **US1 (P1)** — `AddonStatus` + `AddonStatusResolver` (pure, RED→GREEN matrix); Add-ons screen surfaces states +
   enable/disable for installed only (no install-from-admin).
2. **US2 (P2)** — state-aware Settings sections + captcha/reCAPTCHA states; write-only secrets.
3. **US3 (P2)** — scoped admin visual design (cards/tables/topbar/badges) via the adapter, dark/light/RTL/responsive,
   a11y; asset-scoping test (no global/frontend load).
4. **US4 (P3)** — setup + readiness screens + universal states (loading/empty/error/success/permission-denied),
   honest env-gating.
5. **Polish** — docs, full gate, PROGRESS/ROADMAP/CHANGELOG/DECISIONS.

## Complexity Tracking

No constitution violations. Not applicable.
