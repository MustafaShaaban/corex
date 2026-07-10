---
title: Design gap surfaces (Spec 063)
description: The truthful admin screens, blocks, and patterns added by the Spec 063 design-gap program — each showing real state, never fabricated data.
---

Spec 063 closed the implementation-ready gaps from the "Corex Final Design Gap-Closure" package under one
invariant: **every surface communicates its real state — no fabricated data, integrations, Pro/marketplace/
licensing behavior, and no dead entry points.** Where an optional or not-yet-backed design feature appears, the
surface labels the exact current limitation and provides the safest available path instead of pretending it works.

## New admin screens

All screens live under the **COREX FRAMEWORK** menu, are gated by the shared `AdminGuard` (capability + nonce for
any write), and use the scoped `--corex-admin-*` adapter (no global wp-admin restyle).

| Screen | Page slug | What it shows (real data only) |
|---|---|---|
| Overview summary | `corex-settings` | An "At a glance" strip: environment/mode badge (`wp_get_environment_type()`), add-on active/total, form submissions (honest "not available" when the source is off), media delivery, a readiness pointer, and a docs link. |
| Forms & Flows | `corex-forms` | A persisted visual flow builder with draft/published versions, field/rule/action registries, routing, Email Studio bindings, success states, preview/test mode, and supported front-end flow blocks. |
| Submissions | `corex-submissions` | The permission-scoped Inbox for real flow submissions: filters, detail drawer, status/read/assignment, notes, timeline, related email actions, bulk previews, retention, and audited exports. |
| Data Models | `corex-data-models` | The actor-scoped `DataRegistry` catalog and records plus adapter-backed CSV dry-run/commit, rejected-row reports, CSV/XLSX export history, and provider-declared migration apply/rollback. Unsupported actions are omitted. |
| Operations & Security | `corex-operations-security` | The real environment plus real WordPress hardening checks (HTTPS, `DISALLOW_FILE_EDIT`, debug-display hidden, no default "admin"). Operations-mode switching, login protection, and a capability editor are labelled future — CoreX never renames WordPress core files. |
| Email Studio | `corex-email-studio` | A functional studio for persisted templates, layouts, partials, variables, routing, desktop/mobile/RTL previews, plain text, environment-aware test sends, delivery logs, health checks, and resend lineage. |

Each screen loads its own stylesheet only on its own hook (Principle VI), conveys status by text + tone (never
colour alone), and uses logical CSS for RTL, dark, and light.

## New blocks

Both are dynamic (server-rendered) blocks; assets load only where the block renders.

- **`corex/social-share`** — a privacy-friendly Blog share bar built from the real current-post permalink. The
  share links work without JavaScript; a copy-link (Clipboard API) and native-share (Web Share API) control are
  progressive enhancement, revealed only when supported. No share counts, no third-party scripts. Accessible
  labels, RTL, and reduced-motion aware.
- **`corex/newsletter-signup`** — a double opt-in signup form wired to the real
  `corex/v1/newsletter/subscribe` REST route (CoreX Newsletter add-on). It is gated on that add-on (an honest
  "not available" state when inactive), includes a required consent field and an accessible honeypot, and shows
  the endpoint's truthful outcome — a "check your email to confirm" message on success. No fabricated success.

## New company section patterns

Presentational company-site sections ship as native FSE **block patterns** (composed of core blocks + `theme.json`
tokens) rather than bespoke blocks — the design's "prefer core blocks/patterns" rule. They auto-register under the
CoreX pattern category, carry neutral placeholder content only, and are RTL-correct via core blocks.

- `corex/section-services-grid` — header + responsive three-column service cards.
- `corex/section-process-steps` — a numbered, ordered process/steps section.
- `corex/section-logo-cloud` — a restrained "trusted by" logo row (neutral placeholders).
- `corex/section-contact-info` — a heading + responsive contact info cards.

## Truthfulness contract

- No fabricated data, charts, records, integrations, Pro, marketplace, or licensing behavior.
- No dead entry points — an unbuilt or out-of-scope capability is hidden or honestly gated, never a broken link.
- Optional add-ons (Media, Captcha, Email, Newsletter) are detected behind a seam; a screen degrades to an honest
  "unavailable" state when its add-on is inactive (Principle IX). WooCommerce stays dual-gated.
- Every write path is capability + nonce protected; secrets stay write-only.
