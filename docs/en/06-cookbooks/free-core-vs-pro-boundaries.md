---
title: Free/Core vs Pro boundaries
description: Classify adoption basics, trust basics, Pro candidates, deferred work, and out-of-scope items.
audience: contributor
stability: draft
last_verified: 2026-06-19
---

# Free/Core vs Pro boundaries

Spec 055 keeps the first client-site readiness work honest: adoption and trust basics stay in Free/Core, while
advanced commercial capabilities can be marked as Pro candidates without blocking the first company sites.

Use `wp corex readiness` before client-site kickoff. The `free-pro` row passes only when required Free/Core
capabilities exist and no security-critical capability is classified as `pro-candidate`.

## Free/Core baseline

These capabilities are required in Free/Core:

| Capability | Why it stays Free/Core |
|---|---|
| Core framework | The runtime framework is the adoption surface. |
| Basic blocks and DLS | The first company sites need the native design vocabulary. |
| Basic forms and contact form | Contact and lead capture are baseline site behavior. |
| Basic config and options | Safe setup must not require a paid layer. |
| Basic media fields | Company identity pages need native media support. |
| Basic captcha and honeypot | Spam protection is a security baseline. |
| Accessibility | WCAG-oriented output is a trust baseline. |
| RTL | Arabic and logical layouts are core requirements. |
| i18n | Translation-ready strings are required for shippable work. |
| Basic make:site | Client-site separation is required for safe adoption. |
| Basic docs and deployment docs | Operators need baseline docs to run Corex safely. |

Security-critical basics cannot be Pro-only. In code, `FreeProBoundaryItem` rejects a `securityCritical` capability
with `classification: pro-candidate`.

## Pro candidates

These are valid commercial candidates because they are advanced, vertical, or automation-heavy:

| Capability | Why it can be Pro |
|---|---|
| Advanced newsletter | Segmentation, campaign automation, and advanced delivery are commercial scope. |
| Bookings | Scheduling workflows are vertical and optional. |
| Careers and ATS | Applicant tracking and pipeline automation are vertical. |
| WooCommerce kit | Storefront integration is optional and dependency-gated. |
| Advanced email providers | Provider routing, queues, logs, and templates are operations scope. |
| Advanced media CDN and optimization | CDN/offload automation is infrastructure-specific. |
| Data Manager Pro | Advanced admin data management is commercial tooling. |
| White-label admin | Agency/reseller branding is commercial positioning. |
| Starter kits | Packaged vertical starters can be commercial without weakening Core. |
| Azure and DevOps automation | Cloud automation is deployment-specific advanced scope. |
| AI-agent governance dashboards | Governance dashboards are advanced operational reporting. |
| Multi-company identity kit | Managing many identities is agency scope. |
| Client portal dashboard | Client portals are application scope beyond first company sites. |

## Deferred and out of scope

Deferred items are valid ideas that are not required before the first company sites. Out-of-scope items are not part
of the current readiness spec. Do not convert either into product code without a new or updated Spec Kit feature.

## Verification

Run:

```powershell
vendor\bin\pest tests\Unit\Release\FreeProBoundaryTest.php
wp --path=wp corex readiness 0.26.1
```

Expected:

- the Pest suite proves required Free/Core basics exist;
- security-critical Pro candidates are rejected;
- advanced commercial items remain allowed as Pro candidates;
- `wp corex readiness` reports `free-pro` as `PASS`.

## See also

- `packages/cli/src/Release/FreeProBoundaryDefaults.php`
- `packages/cli/src/Release/FreeProBoundaryReadinessCheck.php`
- `tests/Unit/Release/FreeProBoundaryTest.php`
- `specs/055-stable-client-readiness/spec.md`

