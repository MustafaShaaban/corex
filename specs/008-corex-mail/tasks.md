---
description: "Task list for Corex Mail MVP (spec 008)"
---

# Tasks: Corex Mail (MVP)

**Input**: Design documents from `specs/008-corex-mail/`

**Tests**: REQUIRED (constitution). Headless cores are unit-tested first (TDD); the templated send +
log + Forms delegation have an integration test against real `./wp`.

**Guard Gate (per story)**: `clean-code-guard` + `wp-guard` (production), `test-guard` (tests),
`docs-guard` (docs). ABSPATH guard on every src class file. WP API only in boundary classes
(`WpMailDriver`, `EmailLogRepository`, `WpUserDirectory`, the CPT registration, the `Mail` facade accessor).

## Format: `[ID] [P?] [Story?] Description` — seam under `plugins/corex-core/src/Mail`; engine under
`addons/corex-email/src`; tests under repo-root `tests/` (`Corex\Tests`).

---

## Phase 1: Setup

- [ ] T001 Scaffold `addons/corex-email/corex-email.php` (WP plugin header — Requires Plugins: corex-core;
  guarded shared-autoloader fallback; ABSPATH guard; no logic) mirroring `plugins/corex-forms/corex-forms.php`.
- [ ] T002 Add `"Corex\\Email\\": "addons/corex-email/src/"` to root `composer.json` autoload + `composer
  dump-autoload`; create `tests/Unit/Mail/` and `tests/Integration/Mail/`.
- [ ] T003 Create `addons/corex-email/src/MailServiceProvider.php` (skeleton `register()`/`boot()`, ABSPATH
  guard) and register it in `plugins/corex-core/src/Boot.php`'s provider list (after FormsServiceProvider).

## Phase 2: Foundational (blocking prerequisites)

- [ ] T004 [P] corex-core seam: `plugins/corex-core/src/Mail/Mailer.php` (interface `send(MailRequest): void`)
  + `Mail/MailRequest.php` (primitive value object: to/templateName/context/subject/body/replyTo; ABSPATH guard).
- [ ] T005 [P] `addons/corex-email/config/mail.php` (`['from' => ['name' => '', 'address' => ''], 'reply_to' => '']`,
  ABSPATH guard).

---

## Phase 3: User Story 1 — Send a templated email (Priority: P1) 🎯 MVP

**Goal**: build → render (whitelisted merge + escape + layout) → driver → result. **Independent test**:
render a template with a context and assert the merged/escaped body + a result, with a fake driver/log.

- [ ] T006 [P] [US1] Write failing `tests/Unit/Mail/TemplateRendererTest.php`: `{{ path }}` merged from a
  `MailContext`; unknown path → empty; a value containing markup is escaped (not live); body wrapped in the
  layout (FR-003/4/5, SC-003/8).
- [ ] T007 [P] [US1] Write failing `tests/Unit/Mail/MailServiceTest.php`: a valid build → fake driver
  receives the message → result `sent` + one fake-log record; driver returns false → `failed`; service never
  throws (FR-001, FR-011, SC-005). (Security/recipient paths added in US2/US3.)
- [ ] T008 [US1] Implement `src/Message/EmailMessage.php` (immutable) + `Message/MessageBuilder.php` (fluent,
  pure `build()`; `send()` resolves the service from the container).
- [ ] T009 [US1] Implement `src/Template/MailContext.php` (whitelisted dotted `get`) + `Template/EmailTemplate.php`
  (abstract) + `Template/TemplateRegistry.php` (name → template, unknown → null).
- [ ] T010 [US1] Implement `src/Template/Layout.php` (brand.json tokens, logical/RTL CSS, i18n) +
  `Template/TemplateRenderer.php` (merge → sanitize → escape → wrap) to green T006.
- [ ] T011 [US1] Implement `src/EmailResult.php` + `src/MailService.php::deliver()` (orchestrate build →
  driver → log; fake-driver/log injectable) to green T007.
- [ ] T012 [US1] Implement `src/Mail.php` facade (bounded container accessor → MessageBuilder); bind cores +
  TemplateRegistry + MailService in `MailServiceProvider::register()`; guard gate (clean-code + test-guard).

**Checkpoint**: a templated message renders + "sends" through a fake driver, fully unit-tested.

---

## Phase 4: User Story 2 — Safe by default (Priority: P1)

**Goal**: header-injection guard + recipient validation, applied inside the service. **Independent test**:
CR/LF in a field rejects; an invalid address is dropped while a valid co-recipient is kept.

- [ ] T013 [P] [US2] Write failing `tests/Unit/Mail/HeaderGuardTest.php`: CR / LF / control char in subject,
  from, reply-to, or a display name → a rejection reason; a clean set → null (FR-006, SC-002).
- [ ] T014 [US2] Implement `src/Security/HeaderGuard.php` (pure `inspect(fields): ?string`) to green T013.
- [ ] T015 [US2] Wire `HeaderGuard` + per-address validation into `MailService::deliver()`: a header
  rejection → result `rejected` + log, no send; invalid addresses dropped + logged; no valid recipient →
  `failed`, no send (FR-006/7, SC-002/4). Extend MailServiceTest for these paths; guard gate.

**Checkpoint**: nothing dangerous or invalid can be delivered; every blocked attempt is logged.

---

## Phase 5: User Story 3 — Resolve recipients flexibly (Priority: P2)

**Goal**: fixed/role/dynamic recipient resolution + reply-to. **Independent test**: each kind resolves from
a context against a fake user directory; invalid dropped.

- [ ] T016 [P] [US3] Write failing `tests/Unit/Mail/RecipientResolverTest.php`: `fixed`, `role` (fake
  `UserDirectory`), and `dynamic` (context path) specs → validated address set; invalid dropped (FR-007/8, SC-004).
- [ ] T017 [US3] Implement `src/Recipients/UserDirectory.php` (interface) + `Recipients/RecipientResolver.php`
  (pure; role via the directory; dynamic via `MailContext`) to green T016; wire into `MessageBuilder`/service.
- [ ] T018 [US3] Implement `src/Recipients/WpUserDirectory.php` (`get_users` by role, capped — boundary);
  bind it in the provider; guard gate (clean-code + wp-guard on the directory; test-guard).

**Checkpoint**: recipients target a person, a role, or context data — all validated.

---

## Phase 6: User Story 4 — Forms uses Corex Mail (detect-and-defer) (Priority: P2)

**Goal**: real delivery + log on real WP, and the Forms listener delegates to the seam. **Independent
test**: integration — a templated send writes a `corex_email_log`; with the Mailer bound, the contact form
notification routes through Corex Mail.

- [ ] T019 [P] [US4] Write failing `tests/Integration/Mail/MailLifecycleTest.php`: a templated `Mail::to()
  ->template()->with()->send()` returns `sent` and creates one `corex_email_log`; a header-injection attempt
  logs `rejected` with no send; with `Mailer` bound, a `MailRequest` from the Forms path is delivered + logged
  (FR-010/12, SC-005/6).
- [ ] T020 [US4] Implement `src/Log/EmailLog.php` (Model, postType `corex_email_log`) + `Log/EmailLogRepository.php`
  (`record(status, message, template)` + `byStatus()` via the data layer — boundary).
- [ ] T021 [US4] Implement `src/Driver/MailDriver.php` (interface) + `Driver/WpMailDriver.php` (`wp_mail`,
  from-identity from `config('mail.*')` — boundary); bind `MailDriver` → `WpMailDriver`.
- [ ] T022 [US4] Register the `corex_email_log` CPT (`public=false`) on `init`; make `MailService` implement
  `Corex\Mail\Mailer` (`send(MailRequest)` → build → `deliver()`); bind `Corex\Mail\Mailer` → `MailService`
  in `MailServiceProvider`.
- [ ] T023 [US4] Implement `src/Templates/ContactNotificationTemplate.php` (name `contact-notification`;
  subject/body with `{{ form.* }}`/`{{ site.* }}`) and register it with the `TemplateRegistry` in `boot()`.
- [ ] T024 [US4] Modify `plugins/corex-forms/src/Listeners/SendEmailListener.php`: when the container has
  `Corex\Mail\Mailer`, build a `MailRequest` (template `contact-notification`, the submission as context) and
  `send()`; otherwise the existing `wp_mail` fallback (FR-012). Guard gate (clean-code + wp-guard).
- [ ] T025 [US4] Green the integration test (T019); guard gate (wp-guard on driver/log/CPT/listener; test-guard).

**Checkpoint**: a real, audited templated email sends, and Forms routes through Corex Mail when present.

---

## Phase 7: Polish & Cross-Cutting

- [ ] T026 [P] Create `addons/corex-email/README.md` (Mail API, templates + merge/escaping, the security
  gate, recipients, the driver/config, the log, the Forms integration); docs-guard.
- [ ] T027 [P] Update `plugins/corex-core/README.md` with a short "Mail seam" section (`Corex\Mail\Mailer`);
  docs-guard.
- [ ] T028 Run the full headless suite + integration; confirm a live templated send logs + Forms delegates;
  confirm site still boots (SC-005/006/007).
- [ ] T029 Update `PROGRESS.md` (spec 008 complete) + `DECISIONS.md` (the Mailer seam, the email-log CPT, the
  wp_mail default driver, the whitelisted `{{ path }}` merge); verify Definition of Done; merge to develop +
  release tag at the next checkpoint.

---

## Dependencies & Execution Order

Setup (T001–T003) → Foundational (T004–T005) → **US1** (T006–T012, MVP) → **US2** (T013–T015, needs US1) →
**US3** (T016–T018, needs US1) → **US4** (T019–T025, needs US1+US2+US3) → Polish (T026–T029). US1 is the
independent MVP; US2/US3 harden and extend it; US4 adds the real boundary + the Forms delegation.

## Inline analyze — FR/SC coverage

- FR-001 T007/T008/T011 · FR-002 T009/T023 · FR-003 T006/T009/T010 · FR-004 T006/T010 · FR-005 T010 ·
  FR-006 T013/T014/T015 · FR-007 T015/T016/T017 · FR-008 T016/T017/T018 · FR-009 T021 · FR-010 T019/T020 ·
  FR-011 T007/T011/T015 · FR-012 T022/T024 · FR-013 (no optional dep) all.
- SC-001 T012 · SC-002 T013/T015 · SC-003 T006 · SC-004 T015/T016 · SC-005 T007/T019 · SC-006 T019/T024 ·
  SC-007 T006/T013/T016 · SC-008 T006/T010. **All FRs and SCs covered; 0 critical.**

## Notes
- WP API (`wp_mail`/`get_users`/`register_post_type`/`WP_Query`) only in `WpMailDriver`, `WpUserDirectory`,
  `EmailLogRepository`, the CPT registration, and the `Mail` facade accessor. Renderer/HeaderGuard/
  RecipientResolver/MessageBuilder/MailContext stay pure. One task at a time; guard before each commit;
  commit per story (Conventional Commits) on `feature/008-corex-mail`.
