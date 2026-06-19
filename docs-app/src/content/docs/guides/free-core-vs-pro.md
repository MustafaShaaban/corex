---
title: Free/Core vs Pro
description: The product-boundary policy that keeps adoption, safety, accessibility, RTL, and deployment basics in Core.
---

Corex can support commercial add-ons without weakening the free framework. The rule is simple: anything needed to
adopt Corex safely or ship a basic company site stays in Free/Core. Advanced vertical workflows, automation, and
agency operations can become Pro candidates.

`wp corex readiness` enforces the policy through the `free-pro` row. It passes only when all required Free/Core
capabilities are present and no security-critical capability is classified as `pro-candidate`.

## Free/Core baseline

These capabilities are required in Free/Core:

| Capability | Reason |
|---|---|
| Core framework | The framework runtime is the adoption surface. |
| Basic blocks and DLS | Company sites need a native design vocabulary without a paid dependency. |
| Basic forms and contact form | Contact and lead capture are baseline website behavior. |
| Basic config and options | Safe setup cannot require a commercial layer. |
| Basic media fields | Company pages need native media and identity content. |
| Basic captcha and honeypot | Spam prevention is a security baseline. |
| Accessibility | WCAG-oriented output is part of trust and delivery quality. |
| RTL | Arabic and logical layouts are first-class requirements. |
| i18n | Translation-ready strings are required for shippable work. |
| Basic make:site | Client-site separation is required for safe adoption. |
| Basic docs and deployment docs | Operators need baseline documentation to run Corex safely. |

Security-critical basics cannot become Pro-only. The release boundary model rejects a security-critical item when
its classification is `pro-candidate`.

## Pro candidates

These capabilities can be commercial because they are advanced, vertical, or operations-heavy:

| Candidate | Scope |
|---|---|
| Advanced newsletter | Segmentation, campaigns, automation, and advanced delivery. |
| Bookings | Scheduling workflows and appointment operations. |
| Careers and ATS | Applicant tracking, pipeline automation, and hiring workflows. |
| WooCommerce kit | Storefront integration behind the WooCommerce dependency gate. |
| Advanced email providers | Queues, logs, templates, provider routing, and operational delivery. |
| Advanced media CDN and optimization | CDN/offload and infrastructure-specific optimization. |
| Data Manager Pro | Advanced admin data-management tooling. |
| White-label admin | Agency and reseller branding. |
| Starter kits | Packaged vertical starters beyond the baseline scaffold. |
| Azure and DevOps automation | Cloud and release automation for specific infrastructure. |
| AI-agent governance dashboards | Advanced governance reporting and audit dashboards. |
| Multi-company identity kit | Managing multiple company identities and brands. |
| Client portal dashboard | Application-level client portal workflows. |

## Deferred and out of scope

Deferred items are not blockers for the first client sites. Out-of-scope items are not part of this readiness spec.
Either category needs a new or updated Spec Kit feature before implementation.

## Verify the boundary

```powershell
vendor\bin\pest tests\Unit\Release\FreeProBoundaryTest.php
wp --path=wp corex readiness 0.26.1
```

Expected readiness evidence includes Free/Core rows such as `free-core:accessibility` and Pro candidate rows such as
`pro-candidate:bookings`.

## See also

- [Build a client site](/guides/client-site/)
- [Deploy & distribute](/guides/deployment/)
- [Design Language System](/guides/design-system/)
