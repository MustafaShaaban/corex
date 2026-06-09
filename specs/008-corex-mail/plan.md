# Implementation Plan: Corex Mail (MVP)

**Branch**: `008-corex-mail` | **Date**: 2026-06-09 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/008-corex-mail/spec.md`

## Summary

Deliver transactional email as an *application* of the framework. Pure, headless cores — a
**TemplateRenderer** (whitelisted `{{ path }}` merge → sanitize → escape → brand layout), a
**HeaderGuard** (reject CR/LF/control chars), a **RecipientResolver** (fixed/role/dynamic → validated
addresses), and a **MessageBuilder** producing an immutable **EmailMessage** — plus the thin WordPress
boundary: a **MailDriver** abstraction with a default **WpMailDriver** (`wp_mail`), an **EmailLog**
persisted through the spec-002 data layer (a non-public `corex_email_log` CPT), and a **Mail** facade for
the one-line developer API. A neutral **`Corex\Mail\Mailer`** seam in corex-core lets the spec-007 Forms
listener delegate to Corex Mail when active and fall back to `wp_mail` when not — no hard dependency either
way. The add-on ships as **`addons/corex-email`** (`Corex\Email`).

## Technical Context

**Language/Version**: PHP 8.3 (strict_types).

**Primary Dependencies**: corex-core (Container, Config, BootLogger, ServiceProvider, the spec-007 event
seam, the spec-005 Validator/sanitizers), spec-002 data layer (Model/Repository for `corex_email_log`),
spec-006 `brand.json` (the layout's brand tokens). No optional plugin is a hard dependency.

**Storage**: email audit records as a `corex_email_log` CPT (`public=false`) via the data layer; no custom
table (deferred with the custom-table roadmap item).

**Testing**: Pest unit (headless — renderer, header guard, recipient resolver, message builder, mail
service with a fake driver/log) + Pest integration (real `./wp` — a templated send records a log entry; the
Forms listener delegates to Corex Mail when active).

**Target Platform**: WordPress 7.0+ (works in REST/admin/CLI/cron contexts; sending is request-context-safe).

**Project Type**: WordPress framework add-on (single monorepo; new first-party add-on under `addons/`).

**Performance Goals**: a send does bounded work (render + validate + one driver call + one log insert); role
recipients resolve via a single capped query; no unbounded user scan.

**Constraints**: WP calls confined to boundary classes (the driver, the log repository, the WP user
directory, the facade accessor); renderer/guard/resolver/builder are pure. Token-only layout styling,
logical/RTL CSS, i18n, WCAG for any rendered HTML. Sending is best-effort and non-fatal.

**Scale/Scope**: one example template (the contact notification), one driver (`WpMailDriver`), three
recipient kinds (fixed/role/dynamic). Queue, attachments, multi-provider drivers, admin UI, CLI,
suppression, per-language variants, and the Woo override are out of scope (spec Assumptions).

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- [x] **I. Theme is a skin** — mail logic lives in `addons/corex-email` + the corex-core seam; the layout
  consumes `brand.json`/theme tokens, registers no presentation. PASS.
- [x] **II. Plugins boot themselves** — `MailServiceProvider` self-inits on the boot pass; the log CPT
  registers on `init`. Works in REST/admin/CLI/cron. PASS.
- [x] **III. Thin controllers, fat services** — no controller in the MVP (no admin UI); `MailService`
  orchestrates, the repository is the only data-source layer for the log. PASS.
- [x] **IV. Everything injected** — renderer, guard, resolver, driver, log repo, service all
  container-resolved; the `Mail` facade is a bounded accessor (framework boundary only). PASS.
- [x] **V. Runtime tokens** — the email layout derives brand colors/logo from `brand.json` at runtime; no
  build-time tokens, no hardcoded values. PASS.
- [x] **VI. Assets load conditionally** — N/A (email is server-rendered HTML; the engine enqueues no
  front-end asset). PASS.
- [x] **VII. Security is declarative/automatic** — the header-injection guard + recipient validation apply
  on every send inside the engine; callers cannot bypass them. Output is escaped, input sanitized. PASS.
- [x] **VIII. RTL-first** — the layout uses logical properties; Arabic renders correctly. PASS.
- [x] **IX. No optional dep is hard** — no SMTP plugin/ACF/Woo required; the default driver uses `wp_mail`;
  Forms uses Corex Mail via a container-checked seam and falls back otherwise. PASS.
- [x] **X. Spec is source of truth** — this plan traces to spec 008 (clarified). PASS.
- [x] **Guard Gate + Definition of Done** acknowledged: clean-code-guard + wp-guard (production), test-guard
  (tests), docs-guard (docs); Pest tests; i18n; RTL; WCAG; PROGRESS/DECISIONS updated.

**Result**: PASS — no violations; Complexity Tracking not required.

## Project Structure

### Documentation (this feature)

```text
specs/008-corex-mail/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output (mail-contracts.md)
└── tasks.md             # Phase 2 output (/speckit-tasks)
```

### Source Code (repository root)

```text
plugins/corex-core/src/
└── Mail/
    └── Mailer.php                  # neutral seam: send(MailRequest) — bound by Corex Mail, consumed by Forms
                                    # (+ MailRequest value object: to, templateName|subject, context|body, replyTo)

addons/corex-email/                 # NEW first-party add-on (Corex\Email)
├── corex-email.php                 # WP plugin header + guarded autoloader + (no logic)
├── config/mail.php                 # from.name / from.address / reply_to defaults
├── src/
│   ├── MailServiceProvider.php     # binds cores + driver + log; registers the log CPT + default templates;
│   │                               # binds the corex-core Mailer seam to the Corex Mail implementation
│   ├── Mail.php                    # facade: Mail::to()->template()->with()->send() / ->subject()->body()
│   ├── MailService.php             # orchestrates build → guard → validate recipients → render → driver → log
│   ├── EmailResult.php             # value object: status (sent|failed|rejected), message, logId
│   ├── Message/
│   │   ├── EmailMessage.php        # immutable: to/cc/bcc, replyTo, subject, body, headers
│   │   └── MessageBuilder.php      # fluent builder backing the facade (pure)
│   ├── Template/
│   │   ├── EmailTemplate.php       # abstract: name(), subject(context), body(context)
│   │   ├── TemplateRegistry.php    # name → template (unknown → null, non-fatal)
│   │   ├── TemplateRenderer.php    # {{ path }} merge from MailContext, sanitize+escape, wrap in Layout (pure)
│   │   ├── MailContext.php         # typed whitelisted bag: get('user.name') over event/model/site (pure)
│   │   └── Layout.php              # shared brand layout (brand.json tokens, RTL, i18n) — returns HTML
│   ├── Security/
│   │   └── HeaderGuard.php         # reject CR/LF/control in subject/from/reply-to/display names (pure)
│   ├── Recipients/
│   │   ├── RecipientResolver.php   # fixed/role/dynamic → validated addresses (pure; role via UserDirectory)
│   │   ├── UserDirectory.php       # interface: usersInRole(role): list<address>
│   │   └── WpUserDirectory.php     # get_users by role (boundary)
│   ├── Driver/
│   │   ├── MailDriver.php          # interface: send(EmailMessage): bool
│   │   └── WpMailDriver.php        # wp_mail (boundary)
│   ├── Log/
│   │   ├── EmailLog.php            # Model (postType corex_email_log)
│   │   └── EmailLogRepository.php  # PostRepository: record(status, message) + queries (boundary)
│   └── Templates/
│       └── ContactNotificationTemplate.php  # the example template (Forms admin notification)

plugins/corex-forms/src/Listeners/SendEmailListener.php   # CHANGED: delegate to the Mailer seam when bound; else wp_mail

addons/corex-email/tests live under repo-root tests/ (Corex\Tests):
tests/
├── Unit/Mail/TemplateRendererTest.php   # merge + escape + whitelist + layout
├── Unit/Mail/HeaderGuardTest.php         # CR/LF/control rejection
├── Unit/Mail/RecipientResolverTest.php   # fixed/role(fake directory)/dynamic + invalid dropped
├── Unit/Mail/MailServiceTest.php         # build→guard→render→fake driver→fake log; sent/failed/rejected
└── Integration/Mail/MailLifecycleTest.php # real ./wp: templated send logs corex_email_log; Forms delegates
```

**Structure Decision**: A neutral **`Corex\Mail\Mailer`** seam lives in corex-core (with a primitive
`MailRequest` value object using only scalars/arrays) so the Forms add-on can depend on the seam, never on
Corex Mail's concrete types — `container->has(Mailer::class)` is the detect-and-defer switch (Principle IX).
The engine ships as `addons/corex-email` (`Corex\Email`), mirroring the corex-forms bootstrap. The four
cores (TemplateRenderer, HeaderGuard, RecipientResolver, MessageBuilder) are pure and unit-tested; only the
driver, the log repository, the WP user directory, and the facade accessor touch WordPress.

## Complexity Tracking

> No constitution violations — section intentionally empty.
