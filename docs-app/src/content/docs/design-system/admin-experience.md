---
title: Admin experience
description: The shared CoreX wp-admin product experience — truthful add-on states, state-aware settings, write-only secrets, and a calm scoped visual layer.
---

The CoreX admin (milestone M6) is the shared product surface both company sites see inside wp-admin. It is styled
through the scoped `--corex-admin-*` adapter — never a global wp-admin reskin and never applied to the public
frontend — and, above all, it is **truthful**: every screen reflects the real runtime state of each add-on and
setting.

## Truthful add-on states

`Corex\Foundation\AddonStatus` resolves every add-on to exactly one state, via the pure `AddonStatusResolver`
(ordered to agree with boot-time gating):

| State | Meaning |
|---|---|
| `not_installed` | the package/plugin is absent |
| `inactive` | installed but the WordPress plugin is not active |
| `feature_off` | active but the CoreX feature flag is off |
| `dependency_missing` | active but a required CoreX add-on is unmet |
| `woocommerce_missing` | gated on WooCommerce, which is absent |
| `pro_required` | a future/commercial add-on, shown disabled (no licensing) |
| `active` | active and fully satisfied — the only usable state |

The **Add-ons** screen renders each add-on's state as a labelled badge (`AddonStatus::tone()` → success/warning/
danger/neutral; meaning is carried by the label text, never colour alone). Enable/disable is offered only for
installed add-ons (`AddonStatus::canToggle()`); a not-installed add-on shows "Not installed" with no enable control.
**Installing packages is developer/CLI/deployment work** — the admin manages only installed add-ons (enable, disable,
status, dependency explanation, settings access). There is no marketplace or install-from-admin.

## State-aware settings

Settings sections reflect the same model through `Corex\Config\Settings\SettingsSectionState`:

- **not installed** → the section is hidden behind an "add-on not installed" notice (no fields).
- **inactive / feature off** → a disabled notice and disabled inputs (no usable fields).
- **active but unconfigured** → a "configuration needed" prompt with the enterable fields.
- **active + configured** → normal settings.

### Captcha / reCAPTCHA

The captcha section is the worked example: when `corex-captcha` is not installed the reCAPTCHA settings never appear
active; when inactive the fields are disabled; when active but the site key and secret are not both set it shows
"configuration needed"; when active and configured it shows the provider settings and the test action.

## Write-only secrets

Secret (password-typed) settings — the captcha secret and the Insights API keys — are **write-only**. The stored
value is never rendered back into the form (the input is empty with a "Saved / Not set" hint), and submitting the
field empty preserves the stored secret. Saves go through the shared `AdminGuard` (capability + nonce).

## Scoped, accessible visual layer

CoreX admin styling consumes only the scoped `--corex-admin-*` adapter and is enqueued **only on CoreX admin
screens** (Principle VI) — it never restyles generic wp-admin and never loads on the public frontend. The layer is
RTL-first (logical properties), dark and light, and meets WCAG 2.2 AA (landmarks, visible focus, status meaning not
by colour alone).

## Out of scope

No public-frontend design, no plugin marketplace/download/install UI, no Pro licensing/entitlement/distribution, no
client portal, no editor workspace, and no CPT/form/add-on/model/table/resource generators. See
`specs/060-corex-admin-product-experience/` for the full spec and contracts.
