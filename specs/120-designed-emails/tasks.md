# Tasks: Emails that send what was designed

**Input**: [spec.md](spec.md), [plan.md](plan.md), [research.md](research.md), [data-model.md](data-model.md),
[contracts/rest.md](contracts/rest.md), [quickstart.md](quickstart.md)

Order: story 7, 3, 2, 5, 1, 4, 6 (plan "Order of work"). [P] = can run alongside the task before it. Story numbers are
the spec's; the revamp story is in brackets.

## Phase 1: Story 7, a plain-text version on the shared mailer [US-EML-19] (FR-035 to FR-037)

- [ ] T001 [US7] `plugins/corex-core/src/Mail/MailRequest.php`: `?string $plainText = null`, last; blank → null.
- [ ] T002 [US7] `RequestMailer`, `MessageBuilder::plainText()`, `Queue/MailRequestPayload` carry it (finished only).
- [ ] T003 [P] [US7] Unit: payload round trip, an old-style request unchanged, blank is none; integration: captured
  with both parts, captured unchanged without.
- [ ] T004 [US7] Email Studio log: each capture shows its plain text and HTML in a disclosure (`LogsPanel.js`).

## Phase 2: Story 3, one frame [US-EML-05] (FR-012 to FR-018)

- [ ] T005 [US3] `Design/EmailKind.php`, `KindAwareEmailDesign.php`; `EmailContent` gains `kind`, `links`.
- [ ] T006 [US3] `StandardEmailDesign` footer: reason line and links; `SiteEmailDesign` gives a site design only the
  kinds it accepts.
- [ ] T007 [US3] `SiteEmailBrand`: accent falls back to theme.json `primary` (`#rrggbb`).
- [ ] T008 [US3] `Design/EmailFrame.php`: brand, accent override, direction override, privacy link, plain footer.
- [ ] T009 [US3] `TemplateRenderer` and `EmailTemplate::kind()/links()` through the frame, with a plain-text part;
  `Layout` removed; `MessageBuilder` sends the plain text of a template.
- [ ] T010 [US3] Code templates' kinds and links: contact notification (with the submission link), newsletter
  (unsubscribe in the frame), bookings, careers, guides.
- [ ] T011 [US3] `Templates/FormNotificationTemplate.php`; `SendEmailListener` uses it and hands the floor a plain body.
- [ ] T012 [P] [US3] Unit: frame per kind, reason and links, accent order, region dedupe, kind-aware site design, reply
  unchanged for a site design; integration: four kinds in one frame on a capturing site.

## Phase 3: Story 2, what a Studio send carries [US-EML-03] (FR-007 to FR-011)

- [ ] T013 [US2] `EmailTemplateService`: From optional, named errors, plain text by `PlainText`, merged values whole.
- [ ] T014 [US2] `EmailRouteMessageFactory`, gateway `resendTo()`, controller resend: From, From name, plain text.
- [ ] T015 [US2] `EmailStudioService::dispatch()` runs the `HeaderGuard`.
- [ ] T016 [P] [US2] Unit and integration: From and plain part on a routed send, a resend, defaults to the general
  sender, `<500` whole, refusals named, finished messages unchanged.

## Phase 4: Story 5, variables [US-EML-07] (FR-025 to FR-030)

- [ ] T017 [US5] `Studio/EmailVariableCatalog.php` (groups, known keys, supplies) and `EmailSampleValues.php`.
- [ ] T018 [US5] `EmailTemplateService`: known-key check naming the key, `form.answers` block, missing values left
  out with their blocks, `missing` returned; attempt `missing_variables` stored and shown in the log.
- [ ] T019 [US5] corex-core `MailTemplateUsage`; corex-forms `FlowTemplateUsage`; save warnings in the draft route.
- [ ] T020 [US5] `MailTemplateCatalog` entries' `variables`; `FlowConfigurationValidator` refuses an unsupplied
  variable unless `accept_missing`; `EmailsTab.js` warns, names the events in words and asks for the confirmation.
- [ ] T021 [US5] `EmailStage` context: `form.title`, `form.answers`, `links.submission`, `email.kind`.
- [ ] T022 [US5] Variables tab grouped with samples (`AssetPanels.js`); the comma-separated variables field goes.
- [ ] T023 [P] [US5] Unit, integration and Jest for the above.

## Phase 5: Story 1, preview and test send [US-EML-02] (FR-001 to FR-006)

- [ ] T024 [US1] `Studio/EmailTemplatePreview.php`: draw a draft with samples and direction; test send of it.
- [ ] T025 [US1] Controller `preview` and template `test` routes; result in words with reason and log address.
- [ ] T026 [US1] Client: server preview with samples beside it, Desktop 600px / Mobile 375px / Right to left; Plain
  text tab from the server; Test send with one address, refusal, result and link; error in place of the preview.
- [ ] T027 [P] [US1] Unit, integration, Jest, Playwright (`tests/e2e/email-studio-preview.spec.js`).

## Phase 6: Story 4, default templates [US-EML-01] (FR-019 to FR-024)

- [ ] T028 [US4] `Studio/DefaultEmailTemplates.php`: definitions, install active or as drafts, reset; installed from
  `MailServiceProvider::registerEmailStudio()`; reset route and button with confirmation.
- [ ] T029 [US4] corex-forms: default bindings on flow create; submitter without an address is `no_recipient`; team
  notification with nothing to send falls back to the PHP contact notification.
- [ ] T030 [US4] corex-core `DesignedEmails`; corex-email `StudioDesignedEmails`; corex-newsletter double opt-in and
  welcome through it.
- [ ] T031 [P] [US4] Integration: fresh install active, upgrade drafts, reset keeps history, new flow sends two,
  newsletter confirm and welcome, fallback.

## Phase 7: Story 6, account emails [US-EML-15] (FR-031 to FR-034)

- [ ] T032 [US6] corex-profile defaults "Password reset" and "Welcome to your account"; reset through
  `send_retrieve_password_email` during an account-block request; welcome instead of `wp_new_user_notification()`;
  page mark on the register form; fall back and record.
- [ ] T033 [P] [US6] Integration: both captured framed with plain text, no login address, Arabic right to left, a
  broken template falls back and is recorded.

## Phase 8: Polish

- [ ] T034 Docs: `docs-app/.../guides/mail.md` (the `plainText` field with an example, designed emails),
  `email-design.md` (kinds, `KindAwareEmailDesign`), corex-email README.
- [ ] T035 CHANGELOG `[Unreleased]` with before and after per kind and Client impact (Muva, Perego, Mizzey); PROGRESS
  "In flight"; DECISIONS #450 to #458.
- [ ] T036 Guards (`clean-code-guard`, `wp-guard`, `test-guard`, `docs-guard`); measure the screens at 375px, dark and
  right to left; token inventory; Quality findings fixed; CI green.
