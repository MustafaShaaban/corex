# Implementation Plan: Emails that send what was designed

**Branch**: `spec/120-designed-emails` | **Date**: 2026-10-23 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/120-designed-emails/spec.md` (seven stories; decisions EML-D4, EML-D5,
EML-D10, EML-D11 under the owner's 2026-10-21 mandate; SAFE-D2)

## Summary

One class, `EmailFrame`, draws every email CoreX draws, in the standard design and the site's brand, with a reason line
and links chosen by the email's kind; a site's own design keeps replies and is given other kinds only if it says so.
Studio templates are drawn on the server for the preview, the test send and every real send by the same code, with
sample values, the template's From and a plain-text part. Variables are known from one catalog grouped by the emails
that supply them; any submission field and an "All answers" block can be used; a missing value is left out and named
on the log row; a binding that would leave one out needs a confirmation. A new site ships four editable defaults and new
flows bind two of them; the account add-on's reset and welcome emails are Studio templates sent through the shared
mailer. A finished message on the shared mailer can carry its own plain text. The design is in
[research.md](research.md) (R1 to R12), the shapes in [data-model.md](data-model.md), the routes in
[contracts/rest.md](contracts/rest.md), the checks in [quickstart.md](quickstart.md).

## Technical Context

**Language/Version**: PHP 8.3; JavaScript built with `@wordpress/scripts`

**Primary Dependencies**: WordPress 7.0+ (`send_retrieve_password_email`, `get_password_reset_key`,
`get_privacy_policy_url`, REST, options); CoreX contracts; no new library

**Storage**: no schema change. Options `corex_email_default_templates_state` and `corex_email_default_templates`
(new, loaded with the site's options). Studio records: attempt payload gains `missing_variables`; flow email bindings
gain `accept_missing`.

**Testing**: Pest unit and integration (512M), Jest, Playwright; tests capture mail or use `MailDelivery::DELIVER` with
a spy, never deliver

**Target Platform**: WordPress 7.0+ on shared and managed hosts, single site and multisite

**Project Type**: WordPress framework (plugins, add-ons, admin React screens)

**Performance Goals**: a preview answers in under 500ms on a shared host (one render, no extra query per variable)

**Constraints**: finished messages unchanged (SAFE-D2); client-implemented interfaces unchanged in a breaking way:
`EmailDesign` keeps its one method, `EmailContent` and `MailRequest` gain trailing optional fields, `MailTemplateCatalog`
entries gain a key; `Mailer` and `SubmissionEmailGateway` untouched

**Scale/Scope**: seven stories across corex-core, corex-email, corex-config, corex-forms, corex-newsletter,
corex-profile, corex-bookings, corex-careers, corex-guides

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.* Both passes give the same answers.

- [x] **I. Theme is a skin**: PASS. Nothing goes in a theme; the frame reads the theme's primary colour at runtime.
- [x] **II. Plugins boot themselves**: PASS. Defaults install on `init` from corex-email; add-ons register theirs from
  their own providers.
- [x] **III. Thin controllers, fat services**: PASS. The preview, test and reset routes validate input and call one
  service (`EmailTemplatePreview`, `EmailStudioService`, `DefaultEmailTemplates`).
- [x] **IV. Everything injected**: PASS. New services are container bindings; the two new contracts live in corex-core
  and are bound by the plugins that implement them.
- [x] **V. Runtime tokens**: PASS. Screen styles use `--corex-` variables. The frame writes colours as values because
  mail clients read no custom property; they come from `EmailPalette`, the brand settings and theme.json at runtime.
- [x] **VI. Conditional assets**: PASS. Changes are in Email Studio's and the flow editor's existing bundles.
- [x] **VII. Declarative security**: PASS. New routes declare Email Studio's permission and nonce callbacks; output
  escaped; values merged escaped; the header guard runs on Studio sends.
- [x] **VIII. RTL-first**: PASS. The frame follows the site's direction; the preview has a right-to-left view; screen
  CSS uses logical properties.
- [x] **IX. No optional dep is hard**: PASS. corex-profile and corex-newsletter use `DesignedEmails` only when it is
  bound; corex-email uses `MailTemplateUsage` only when corex-forms binds it.
- [x] **X. Spec is source of truth**: PASS. Four gaps decided in research (R1, R7, R8, R9) under the mandate.
- [x] **Guard Gate + Definition of Done**: `clean-code-guard`, `wp-guard`, `test-guard`, `docs-guard`; i18n, RTL, WCAG
  2.2 AA; DECISIONS #450 to #458, PROGRESS and the release notes.

## Project Structure

### Documentation (this feature)

```text
specs/120-designed-emails/
├── spec.md  plan.md  research.md  data-model.md  quickstart.md  tasks.md
├── contracts/rest.md
└── checklists/requirements.md
```

### Source Code (repository root)

```text
plugins/corex-core/src/Mail/        # MailRequest (plainText), DesignedEmails, MailTemplateUsage (new contracts)
addons/corex-email/src/
├── Design/                         # EmailFrame, EmailKind, KindAwareEmailDesign (new); EmailContent, Standard/Site
│                                   # design, SiteEmailBrand (accent fallback)
├── Template/                       # TemplateRenderer through the frame; EmailTemplate kind()/links(); Layout removed
├── Templates/                      # ContactNotificationTemplate, FormNotificationTemplate (new)
├── Studio/                         # EmailTemplateService (render, variables, missing), EmailVariableCatalog,
│                                   # EmailSampleValues, EmailTemplatePreview, DefaultEmailTemplates,
│                                   # StudioDesignedEmails (new); controller preview/test/reset; gateway resend
├── Routing/EmailRouteMessageFactory.php     # From, From name, plain text, kind
├── Message/MessageBuilder.php, RequestMailer.php, Queue/MailRequestPayload.php
└── MailServiceProvider.php
plugins/corex-config/src/Email/     # Preview, Plain text, Test send, Variables, Templates (From, reset, warnings), Logs
plugins/corex-config/src/Forms/tabs/EmailsTab.js   # event names, missing-variable warning and confirmation
plugins/corex-forms/src/            # default bindings, EmailStage context and fallback, validator, FlowTemplateUsage,
                                    # SendEmailListener template and plain floor body
addons/corex-newsletter/src/        # Studio double opt-in and welcome, unsubscribe link in the frame
addons/corex-profile/src/           # reset and welcome through DesignedEmails, defaults, page mark on register
addons/corex-bookings, corex-careers, corex-guides  # template kinds
docs-app/src/content/docs/guides/mail.md, email-design.md
```

**Structure Decision**: the existing layout. Contracts in corex-core; drawing in corex-email; each sender where it is.

## Client impact

- **Muva**: finished messages (leads, briefs) unchanged; its reply design keeps replies only (R2); four draft templates.
- **Perego**: finished messages unchanged, same From and single HTML part; may hand over its text version (R10); four
  draft templates.
- **Mizzey**: pinned to v0.42.0.

## Order of work

Story 7 (the seam, small and independent), story 3 (the frame; the rest is drawn in it), story 2 (what a Studio send
carries), story 5 (variables; the preview samples them), story 1 (preview and test send), story 4 (defaults), story 6
(account emails).
