# Implementation Plan: Admin control panel & integrations

**Branch**: `feature/044-admin-control-panel` | **Date**: 2026-06-13 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/044-admin-control-panel/spec.md`

## Summary

Layer a **control-panel experience and integration diagnostics** over the shipped settings (032), add-on manager
(026), insights (037), branding (016), and captcha drivers (012) — adding no new settings store and no new driver.
Pure services compute, from existing Config values, a **per-domain status** (configured / needs setup / error) and
an **onboarding checklist**; the settings + dashboard screens render them as cards with warnings + setup links.
Two **test actions** — captcha "Test verification" and Insights "Test key/URL" — run server-side, classify the
result (pure classifiers; local-URL detection, HTTP/quota/invalid-key/invalid-response for PSI), and return it
through the **spec-043 `ResponseEnvelope` + `window.Corex` runtime** (no secret ever in a response). The `Addon`
manifest gains rich, accurate fields (summary/description/provides/requires/enable-disable/needs-config/docs) that
the Add-ons screen surfaces. Framework authorship metadata is corrected to the owner/brand.

## Technical Context

**Language/Version**: PHP 8.3 (corex-config) + browser JS via the existing `window.Corex` runtime (spec 043).

**Primary Dependencies**: existing only — `SettingsRegistry`/`SettingsForm`/`SettingsStore`/`AdminDashboard`
(032), `Config`/`FeatureFlags`, `AddonRegistry`/`Addon`/`AddonsScreen` (026), `InsightProvider`/`PerformanceProvider`/
`PsiNormalizer`/`InsightStore` (037), the captcha `CaptchaResolver`/`RemoteCaptcha` drivers (012), the shared
`AdminGuard` (Principle VII scope), and `ResponseEnvelope`/`EnvelopeResponder` + `corex-runtime` (043). No new
runtime/build dependency; no new option store.

**Storage**: none new — domain status + checklist are **derived** at render time from existing settings; captcha
gains a site-key + v3 threshold/action persisted via the existing `SettingsStore` (same option mechanism).

**Testing**: Pest (pure status/checklist/diagnostic classifiers + the controllers' envelope shape, Brain Monkey)
and Jest where admin JS is added (the test-button wiring on `window.Corex`). Live browser smoke env-gated.

**Target Platform**: wp-admin (Corex settings, dashboard, Add-ons, Insights screens).

**Project Type**: WordPress framework monorepo — enhancement to `corex-config` + authorship across plugins/theme.

**Performance Goals**: status/checklist computed from already-loaded settings (no extra queries); test actions are
on-demand admin clicks; no per-request cost added to the front end.

**Constraints**: token-only with wp-admin palette fallbacks (DECISIONS #71); logical/RTL; WCAG 2.2 AA (status not
by color alone); i18n; AdminGuard cap+nonce on every screen/action; **no secret in any response** (Principle VII).

**Scale/Scope**: ~6 new pure classes (ControlPanel status, OnboardingChecklist, CaptchaDiagnostic, PsiDiagnostic,
SiteUrlReachability, the extended Addon manifest), 2 thin test controllers, view/CSS additions to 3 existing
screens, authorship header edits. No new screen menu.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.* (Corex Constitution v1.2.1.)

- [x] **I. Theme is a skin** — N/A/PASS. No business logic added to the theme; only its **author header** changes.
- [x] **II. Plugins boot themselves** — PASS. All work lives in corex-config providers/screens on admin hooks; no
  theme dependency.
- [x] **III. Thin controllers, fat services** — PASS. Status/checklist/diagnostic logic is in **pure services**;
  the test controllers only route → service → envelope; screens only render.
- [x] **IV. Everything injected** — PASS. New services are container-wired; the pure classifiers are value-style
  (no `new` of a dependency inside a method).
- [x] **V. Runtime tokens** — PASS. Card/checklist/diagnostic CSS uses `var(--corex-…, <admin fallback>)`; no
  build-time tokens.
- [x] **VI. Conditional assets** — PASS. Admin CSS/JS enqueue only on the Corex screens that use them; the test
  buttons reuse the already-conditional `corex-runtime`.
- [x] **VII. Declarative security** — PASS. Every screen + test action goes through the shared `AdminGuard`
  (cap + nonce); responses use the envelope and **carry no secret** (FR-008/FR-013/SC-006); output escaped.
- [x] **VIII. RTL-first** — PASS. Logical properties throughout the new admin CSS.
- [x] **IX. No optional dep is hard** — PASS. Captcha drivers + PSI/Cloudflare stay optional, detected/adapted; a
  fresh install with nothing configured renders fully (all "needs setup").
- [x] **X. Spec is source of truth** — PASS. Traces to spec 044; reuses 012/016/026/032/037/043 without re-speccing.
- [x] **Guard Gate + DoD** — acknowledged: wp-guard (cap/nonce/escape/remote-get/no-secret), clean-code, test-guard
  (Pest/Jest), docs-guard (settings/insights/add-on guides + READMEs); i18n, RTL, WCAG; PROGRESS/DECISIONS; NEXT STEP.

**Result: PASS — no violations. Complexity Tracking not required.**

## Project Structure

### Documentation (this feature)

```text
specs/044-admin-control-panel/
├── plan.md · research.md · data-model.md · quickstart.md
├── contracts/
│   ├── test-actions.md       # captcha + insights test REST actions (envelope-shaped)
│   └── domain-status.md      # the status/checklist + add-on manifest view contracts
└── tasks.md                  # created by /speckit-tasks
```

### Source Code (repository root)

```text
plugins/corex-config/src/
├── ControlPanel/
│   ├── DomainStatus.php          # NEW — pure: settings values → per-domain {status, missing[], setupLink}
│   ├── ControlPanelStatus.php    # NEW — pure: aggregates the domains for the cards
│   └── OnboardingChecklist.php   # NEW — pure: domains → checklist steps (label, domain, done, link)
├── Settings/
│   ├── SettingsRegistry.php      # CHANGE — captcha: add site_key, v3 score_threshold + action; group metadata
│   ├── AdminDashboard.php        # CHANGE — render domain cards + warnings/setup-links + onboarding checklist
│   └── assets/control-panel.css  # NEW — token-only, admin-fallback card/checklist/status styling
├── Captcha/
│   ├── CaptchaDiagnostic.php     # NEW — pure: classify a verify outcome (ok/invalid-key/network/missing-keys)
│   └── CaptchaTestController.php # NEW — REST: AdminGuard-gated, runs the driver verify, returns the envelope (no secret)
├── Insights/
│   ├── SiteUrlReachability.php   # NEW — pure: detect local/private URL (.local/localhost/127.*/private ranges)
│   ├── PsiDiagnostic.php         # NEW — pure: classify a PSI failure (local-url/http/quota/invalid-key/invalid-response)
│   ├── Providers/PerformanceProvider.php  # CHANGE — use the classifiers; carry an admin-only diagnostic detail
│   └── InsightsController.php    # CHANGE — add a cap+nonce "test" action returning the classified envelope
└── Addons/
    ├── Addon.php                 # CHANGE — manifest gains summary/description/provides/requires/enable-disable/needs-config/docs
    ├── AddonView.php             # CHANGE — render the rich manifest
    └── AddonsScreen.php          # CHANGE — surface needs-config + missing keys

plugins/*/(*.php headers) · theme/style.css   # CHANGE — Author header → owner/brand (US5)
docs/ + CONTRIBUTING                            # CHANGE — document the authorship convention

tests/Unit/Config/ (Pest) · tests/<jest>        # NEW — classifiers + controllers + checklist
docs-app/src/content/docs/guides/configuration.md · insights.md   # CHANGE — document the panel + diagnostics
```

**Structure Decision**: All behavior lives in **corex-config** as **pure services** (status, checklist, the two
diagnostic classifiers) plus **thin test controllers** that reuse the existing captcha drivers and insight
providers and answer with the spec-043 envelope. No new admin menu, no new option store — the control panel
re-renders the *existing* settings grouped, and the diagnostics enrich the *existing* insight/captcha paths.
Authorship (US5) is a cross-cutting header edit + a documented convention.

## Complexity Tracking

> No Constitution Check violations — section intentionally empty.
