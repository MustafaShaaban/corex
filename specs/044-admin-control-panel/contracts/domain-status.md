# Contract — Control-panel domain status, checklist & add-on manifest (view contracts)

These are **server-rendered** view contracts (no REST surface) — the shape the screens render from the pure
services. They are computed from existing Config values; nothing new is persisted.

## Domain card (per domain)

Each of the named domains (Branding, Forms, Captcha, Mail, Insights, Updates, Integrations, Add-ons) renders a card:

- a **title** and a **status badge**: `configured` / `needs setup` / `error` — status conveyed by **text + icon**,
  not color alone (WCAG 2.2 AA).
- when not `configured`: a plain-language **warning** naming the missing/invalid items, and a **"how to set this
  up"** link (deep-link to the field(s) and, for integrations, an external "where to get keys/docs" link).
- a configured **secret** shows as "set" only — never the value.

Status rules: `configured` = required fields present + valid · `needs_setup` = a required field empty for the
chosen feature · `error` = a recorded failed test. Honeypot/none captcha & absent-optional keys → `configured`.

## Onboarding checklist (dashboard)

- a list of the **not-done** steps, each: label, the domain, a deep-link; ticked steps drop off.
- an explicit **"you're all set"** state when every domain is `configured`.
- never shows a step for a domain that is already configured.

## Add-on manifest card (Add-ons screen)

Each add-on renders:

- **summary** (one line) + **description**.
- **provides**: the blocks / content types / REST routes / settings / features it registers.
- **enable behavior** and **disable behavior** (what happens on each).
- **requires**: plugins / feature flags / API keys; with a **needs-configuration** flag and the **missing keys**
  named when configuration is incomplete.
- a **docs link**.
- an unmet dependency is **explained** on an enable attempt (the existing dependency-aware refusal, now surfaced),
  not a silent failure.

All text translatable; all values escaped; manifest fields default to empty/false for add-ons that don't set them
(additive — no existing registration breaks).
