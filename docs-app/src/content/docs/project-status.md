---
title: Project status
description: What works, what is partial, and what is not built — with the file that records each one.
---

**Version 0.40.0.** This page exists so you do not have to read the git history to find out what you
are adopting. Everything below is traceable to something in the repository — a spec, a policy file, a
test exclusion, a design inventory row.

The canonical copy is [`PROJECT-STATUS.md`](https://github.com/MustafaShaaban/corex/blob/main/PROJECT-STATUS.md)
in the repository root, kept beside the code it describes. This page mirrors it.

Three statuses, and they mean specific things:

| | Meaning |
|---|---|
| **Stable** | Built, tested, and used. Its contract is not expected to change. |
| **Partial** | Real and working, with a named gap. The gap is stated, not implied. |
| **Planned** | Not built. Listed so you can tell "we chose not to yet" from "we forgot". |

## Foundation

| Module | Status | Notes |
|---|---|---|
| `corex-core` | **Stable** | Container, layered config, routing, middleware, security, cache, jobs, notifications, mail seams, admin shell. |
| `corex-config` | **Stable** | The admin product: settings, data models, submissions inbox, access, operations, notifications, insights, blog tools. |
| `corex-forms` | **Stable** | Schema, validation, flow builder, submission pipeline, routing, delivery, file uploads. |
| `corex-blocks` | **Stable** | Server-rendered blocks with the shared provider/renderer split. |
| `theme/` | **Stable** | FSE block theme, presentation only. |
| `packages/cli` | **Stable** | `wp corex make:*`, `version`, `docs:generate`, release packaging. |

## Add-ons

| Add-on | Status | What is missing |
|---|---|---|
| `corex-email` | **Stable** | Live delivery is fail-closed by default — deliberate, not a gap. |
| `corex-guides` | **Stable** | Extendable guide registry plus support contact. |
| `corex-media` | **Stable** | WebP behind an activation gate that only serves the converted file when it passes. |
| `corex-captcha` | **Stable** | Honeypot, reCAPTCHA, Turnstile, hCaptcha. |
| `corex-ui` | **Stable** | Modal, drawer, tabs, accordion, layout blocks. |
| `corex-careers` | **Stable** | Jobs, applications, CV upload through the protected store. |
| `corex-profile` | **Partial** | Registration, auth gateway, session listing. The full profile system is deferred. |
| `corex-newsletter` | **Partial** | Signed-token subscribe/confirm and a publish notifier. No campaign composer, scheduling or segmentation. |
| `corex-bookings` | **Partial** | Call-request capture and routing. No calendar, availability or rescheduling. |
| `corex-kit-company` | **Partial** | The most complete kit. Remaining section blocks and `make:site` token inheritance are open. |
| `corex-kit-portfolio` | **Partial** | Project type and two blocks — not full page coverage. |
| `corex-kit-woo` | **Planned** | A blueprint, a gate and a provider. It reserves the seam; it is not a store kit. |

## Known open items

No dependency advisories are open: `.github/dependency-security-policy.json` holds zero exceptions and
`npm run verify:dependencies` passes across all three ecosystems. The 24 that stood at v0.39.0 were
closed in v0.40.0.

- **Three browser specs excluded from a fresh-install run.** Two block-editor specs trade a
  first-open failure between them; a third, the flow builder, has not been shown to be environmental
  and may be a real slow path. Recorded with their ruled-out causes in
  `tests/e2e/playwright.config.js`.
- **A one-pixel RTL overflow** on the access screen at 375px.
- **Arabic typography has layout proof, not type proof.** The RTL matrix forces `dir="rtl"` onto
  English strings, so bidi artifacts in those cells belong to the fixture.
- **GitHub Pages is not enabled.** The workflow builds and uploads these docs; publishing is a
  repository setting.

## Deliberately not built

Front-office editor workspace · header builder · mega-menu builder · full client portal · full
auth/profile system · Pro licensing UI · animation in wp-admin · advanced WooCommerce internals.

These are decisions, recorded in `ROADMAP.md` §15. Roadmap presence does not authorize
implementation — that is §16, and it is the rule this project has kept to.

## What is next

Navigation and template parts (M3), the complete company-page contract (M4), component batches built
when a kit proves it needs them (M5), portfolio and WooCommerce kit completion (M8/M9), and a full
Capability Inspector / System Map — the natural successor to the bounded capability summary spec 074
added to the Models screen.
