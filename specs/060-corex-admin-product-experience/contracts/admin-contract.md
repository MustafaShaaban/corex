# Contract: CoreX Admin Product Experience

Testable contracts. Verified by Pest (resolver, settings-state, captcha, asset-scoping, readiness) and ENV-gated
browser checks.

## C1. Add-on state resolver (US1)

- `AddonStatusResolver` MUST return exactly one `AddonStatus` for any input, by the order: `pro_required` (if the
  descriptor is pro), else `not_installed` → `inactive` → `dependency_missing` → `feature_off` →
  `woocommerce_missing` → `active`.
- The resolver MUST be pure: no WordPress functions, no DB, no globals — constructed inputs only.
- `canToggle()` MUST be true only for installed add-ons; `not_installed` and `pro_required` MUST NOT be togglable.

## C2. Add-ons screen (US1)

- The screen MUST render each add-on's state and offer enable/disable + settings access **only** for installed
  add-ons; a `not_installed` add-on MUST show "not installed" with **no** enable control.
- The screen MUST NOT present any marketplace/download/install action.
- `dependency_missing` MUST name the missing dependency; `woocommerce_missing` MUST state WooCommerce is required.

## C3. Settings-section state (US2)

- A section MUST derive its display from its add-on's `AddonStatus`: `not_installed` → hidden or "add-on not
  installed"; `inactive`/`feature_off`/`pro_required` → disabled (no usable fields); `active`+unconfigured →
  "configuration needed"; `active`+configured → normal fields.
- No section MUST present fields as active/usable for a non-`active` add-on.

## C4. Captcha / reCAPTCHA (US2)

- reCAPTCHA settings MUST follow C3 against the captcha add-on state; MUST NOT appear active when captcha is
  `not_installed`.
- `active`+keys-missing → "configuration needed"; `active`+configured → provider settings + test action.
- The secret MUST be write-only: never present in rendered HTML or any API/REST response; an empty submit MUST
  preserve the stored secret.

## C5. Visual design + scoping (US3)

- CoreX admin screens MUST apply the scoped `--corex-admin-*` adapter + the design components and meet WCAG 2.2 AA
  (landmarks, visible focus, status not by color alone) in dark and light, mirrored in RTL (logical properties),
  responsive without horizontal scroll at narrow widths/200% zoom.
- The adapter + CoreX admin CSS/JS MUST load only on CoreX admin screens — never globally in wp-admin and never on
  the public frontend. CoreX admin CSS MUST contain no raw hex/size/font outside the documented admin-token
  allowance.

## C6. Security (cross-cutting)

- Enable/disable and settings save MUST go through the shared `AdminGuard` (capability + nonce); output escaped;
  secrets write-only. No hand-rolled cap/nonce checks.

## C7. Readiness honesty (US4)

- The Readiness/Status screen MUST render environment-gated checks as environment-gated, never as passing.

## C8. Scope

- MUST NOT change public-frontend output; MUST NOT implement marketplace/install-from-admin, Pro licensing/
  entitlement/distribution, client portal, editor workspace, or any generator.

## Acceptance (test hooks)

- Pest: resolver state matrix (every combination → one expected state); `canToggle` rules; settings-section state per
  add-on state; captcha write-only + states; admin asset registered-not-global; readiness env-gating.
- ENV-gated Playwright: a11y/contrast/focus, RTL mirroring, reduced motion, narrow/zoom, dark+light.
