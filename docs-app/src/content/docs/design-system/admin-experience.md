---
title: Admin experience
description: The complete scoped CoreX login and wp-admin product experience, built on truthful runtime state.
---

The CoreX admin is a dark-first product surface inside WordPress, with a complete light mapping. It preserves
WordPress admin chrome and authentication behavior while giving every current CoreX-owned screen one visual and
state contract. It never styles the public frontend.

PR #58 delivered the truthful-state foundation: add-on state resolution, state-aware settings, captcha gating,
write-only secrets, and initial Add-ons badge CSS. The corrective Spec 060 implementation applies the approved visual
design across the complete current admin journey rather than treating M6 as badge-level styling.

## Current surfaces

- Native WordPress login, lost-password, reset-password, message, and error markup with additive CoreX branding.
- `COREX FRAMEWORK` Overview with live site stat cards and onboarding/domain status cards.
- Add-ons as truthful cards with labelled states and enable/disable controls for installed add-ons only.
- Data with search, source/form filters, sorting, CSV export, pagination, detail drawer, and explicit loading/empty/
  filtered-empty/error states.
- Settings as state-aware sections, including captcha controls and write-only secret indicators.
- Setup Wizard with guided progress, kit cards, empty state, and apply success notice.
- Readiness & Insights with performance/readiness cards, loading/error/result states, and honest environment gating.
- Declarative CoreX option pages registered by applications.

## Truthful add-on states

`Corex\Foundation\AddonStatus` resolves every add-on to exactly one state through the pure `AddonStatusResolver`:

| State | Meaning |
|---|---|
| `not_installed` | The package/plugin is absent; no admin enable or install action. |
| `inactive` | Installed, but the WordPress plugin is not active. |
| `feature_off` | Active, but the CoreX feature flag is off. |
| `dependency_missing` | A required CoreX add-on is unmet. |
| `woocommerce_missing` | WooCommerce is required and unavailable. |
| `pro_required` | Static disabled future/commercial indicator; no licensing or purchase flow. |
| `active` | Active and fully satisfied; the only usable state. |

Status meaning is always written as text and reinforced with icon/tone. Color never carries the meaning alone.
The admin manages installed add-ons only. Package installation remains developer/CLI/deployment work.

## Settings and secrets

Settings sections derive from `Corex\Config\Settings\SettingsSectionState`: not installed, disabled, configuration
needed, or normal. Captcha is the worked example. Its provider fields never appear usable while the add-on is absent
or inactive.

Password-typed settings, including captcha and Insights credentials, are write-only. A saved value is represented by
a “Saved” indicator; the input value remains empty. Submitting an empty secret preserves the stored value. Saves use
the shared `AdminGuard` capability and nonce path.

## Visual and accessibility contract

The registered `corex-admin-tokens` adapter supplies only `--corex-admin-*` roles. A shared shell and each
screen-specific stylesheet are conditionally enqueued through an explicit CoreX screen allow-list. Selectors are
rooted in `.corex-admin`; login uses the separate `body.login.corex-login` root. Unrelated wp-admin pages and public
frontend pages do not receive these assets.

The component layer includes page headers, stat cards, add-on cards, settings sections, data tables/toolbars, notices,
badges, helper text, setup progress, readiness checks, and loading/empty/error/success/warning/disabled/
permission-denied states. It uses logical CSS properties for RTL, collapses at narrow admin widths, has visible focus,
keeps text contrast at the WCAG 2.2 AA target, and disables non-essential motion under `prefers-reduced-motion`.

## Verification boundary

Headless PHP/JS contracts verify asset scoping, native-login preservation, screen shell coverage, text-labelled
states, add-on truth, write-only secrets, and empty-secret preservation. See the Spec 060 visual-evidence record for
the rendered browser matrix; unavailable browser checks are marked `ENVIRONMENT-GATED`, never passed by inference.

## Out of scope

Public company-site design, marketplace/download/install UI, Pro licensing or entitlement, client portal, editor
workspace, and generators remain outside M6.
