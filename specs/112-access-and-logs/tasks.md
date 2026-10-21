# Tasks: Access and logs

**Input**: [spec.md](spec.md), [plan.md](plan.md), [research.md](research.md), [data-model.md](data-model.md),
[contracts/rest.md](contracts/rest.md), [quickstart.md](quickstart.md)

**Tests**: required. The Definition of Done asks for tests on every change and FR-028 asks for a role matrix. In each
story the tests are written first and must fail before the code that makes them pass.

**Format**: `- [ ] T### [P] [US#] what, in which file`. `[P]` means it touches files no unfinished task in the same
phase touches. Paths are from the repository root; `CFG` is `plugins/corex-config/src`, `CORE` is
`plugins/corex-core/src`.

**Verification on this machine**: Pest with `-d memory_limit=512M`; Jest and Playwright from the repository root.
Every test that sends mail forces capture (`Corex\Tests\Support\MailDelivery::answerAs(MailDelivery::CAPTURE)` in Pest,
the `X-Corex-E2E-Mail: capture` header in Playwright): corex.local delivers real mail.

## Phase 1: Setup

- [x] T001 Confirm the owner's go-ahead to implement (Pre-Implementation Confirmation Rule) and whether spec 112 is
  pushed for review first; until then the branch `spec/112-access-and-logs` stays local
- [x] T002 Read `origin/main` and every open pull request for the next free DECISIONS number and record it in the first
  implementation commit's DECISIONS.md entry (the last on this branch is #372)
- [x] T003 Run the suites the stories touch and record the baseline: `vendor/bin/pest -d memory_limit=512M
  tests/Unit/Access tests/Integration/Access tests/Unit/Operations tests/Integration/Operations tests/Integration/Blog
  tests/Unit/Data tests/Integration/Data tests/Integration/Submissions tests/Integration/Forms`, then `npx jest
  plugins/corex-config/src/Access plugins/corex-config/src/Security plugins/corex-config/src/Blog
  plugins/corex-config/src/DataModels plugins/corex-config/src/Submissions`

## Phase 2: Foundational

No task blocks every story. Each story's shared pieces are in its own phase; the order between stories is under
Dependencies.

## Phase 3: User Story 1 - High-risk abilities are granted only through the role matrix (P1)

**Goal**: locked and critical abilities cannot be requested; approval shows what is granted and needs a note, an
expiry and a confirmation; grants are listed, revocable and end on time.

**Independent test**: [quickstart.md](quickstart.md) section 1.

### Tests for User Story 1

- [x] T004 [P] [US1] Unit tests for `requestable()` over every catalogue ability, including an area that contains a
  critical ability, in `tests/Unit/Access/RequestableAbilitiesTest.php`
- [x] T005 [P] [US1] Extend `tests/Integration/Access/AccessRouteRefusalTest.php`: a request for a locked or critical
  ability answers 400 with `details.code` `ability_not_requestable` and stores no row
- [x] T006 [P] [US1] Extend `tests/Integration/Access/AccessRequestFormTest.php` and
  `tests/Integration/Access/AccessDeniedGateTest.php`: the denied page of `corex-settings` (Overview), `corex-addons`,
  `corex-access`, `corex-operations-security` and `corex-releases` shows the role-matrix sentence and no form, and a
  direct POST of the form for those sections is refused
- [x] T007 [P] [US1] Integration tests through `rest_do_request()` for the preview, decision, grants and revoke routes
  of [contracts/rest.md](contracts/rest.md#access-requests) (note required, expiry in 7/30/90, typed name for a
  dangerous ability, stale hash, policy blocker, `manage_options` only) in `tests/Integration/Access/AccessGrantsTest.php`
- [x] T008 [P] [US1] Update `tests/Unit/Access/AccessServiceTest.php` and
  `tests/Integration/Access/AccessControllerTest.php` so existing approvals send a note, an expiry and a confirmation
- [x] T009 [P] [US1] Schema test: foundation schema 4 adds `grant_expires_at`, `ended_at`, `ended_by`,
  `ended_reason` and `cancel_reason` to an existing access requests table and keeps its rows, in
  `tests/Integration/Schema/AccessRequestsSchemaV4Test.php`
- [x] T010 [P] [US1] Scheduler tests: an expired grant is ended, its ability removed unless another live grant gives
  it, the activity recorded and both people notified (the approver skipped when deleted); a pending request that is no
  longer requestable is cancelled with `not_requestable`; in `tests/Integration/Access/AccessGrantSchedulerTest.php`
- [x] T011 [P] [US1] Unit test for the grant-ended producer in
  `tests/Unit/Notifications/AccessGrantEndedNotificationProducerTest.php`
- [x] T012 [P] [US1] Jest tests for the approval dialog (ability, risk, reason, note, expiry preselected at 30, typed
  name) and the grants list (rows, "no expiry (granted before expiries existed)", state notes, Revoke) in
  `plugins/corex-config/src/Access/__tests__/accessGrantsPanel.test.js`
- [x] T013 [US1] Change `tests/e2e/access-request.spec.js` so its `corex-access` steps request a requestable ability,
  and add a scenario that approves with a note and 7 days, sees the grant listed and revokes it

### Implementation for User Story 1

- [x] T014 [P] [US1] Create `CORE/Access/RequestableAbilities.php` (not locked and not critical, over
  `CorexAbilityCatalog::expanded()`)
- [x] T015 [P] [US1] Add the `ability_not_requestable` code to `CORE/Access/InvalidAccessInput.php`
- [x] T016 [US1] Call `RequestableAbilities` from `AccessService::assertTarget()` in `CFG/Access/AccessService.php`
  and bind it in `CFG/ConfigServiceProvider.php`
- [x] T017 [US1] Add the no-form state to `AdminPage::requestState()` and the denied body in `CORE/Admin/AdminPage.php`,
  and the matching problem code to the form controller in `CFG/Access/AccessRequestFormController.php`
- [x] T018 [US1] Raise `FOUNDATION_SCHEMA_VERSION` to `'4'` and add the five nullable columns in the access requests
  table definition in `CFG/Access/AccessTables.php` and `CFG/ConfigServiceProvider.php`
- [x] T019 [P] [US1] Add `approvedGrants()`, `endGrant()`, `expiredGrants()` and `cancelNotRequestable()` to
  `CORE/Access/AccessRequestStore.php` and implement them in `CFG/Access/AccessRequestRepository.php`
- [x] T020 [P] [US1] Add `revokeUserAbility()` and `holdsOwnAbility()` to `CORE/Access/AccessUserDirectory.php` and
  implement them in `CFG/Access/WpAccessUserDirectory.php`
- [x] T021 [US1] Add `previewDecision()` (abilities, risk, reason, `AccessPolicy::preview()` blockers, typed name,
  target hash) and the new approval rules (note, expiry, `Confirmation::verify()` with operation kind
  `access.request.approve` and the ability's name as phrase for a dangerous ability) to `CFG/Access/AccessService.php`
- [x] T022 [US1] Add `listGrants()` and `revokeGrant()` to `CFG/Access/AccessService.php`, recording
  `access.grant.revoked`
- [x] T023 [US1] Register `POST access/requests/{id}/preview`, `GET access/grants` and `POST access/grants/{id}/revoke`
  with the `allowed()` check, and the new decision body, in `CFG/Access/AccessController.php`
- [x] T024 [P] [US1] Create `CORE/Access/AccessGrantEndedEvent.php`
- [x] T025 [US1] Create `CFG/Access/AccessGrantScheduler.php` on the pattern of `CFG/Retention/RetentionScheduler.php`
  (daily hook `corex_access_grants_daily`), recording `access.grant.expired` and `access.request.cancelled`, and
  register it in `CFG/ConfigServiceProvider.php`
- [x] T026 [US1] Create `CFG/Notifications/Producers/AccessGrantEndedNotificationProducer.php` and register it in
  `ConfigServiceProvider::registerNotificationProducers()`
- [x] T027 [US1] Show the approval dialog (with `CorexDialog`) in `CFG/Access/AccessRequestsPanel.js`, and an area
  request's area name and abilities (FR-004); move the decision call into `CFG/Access/accessClient.js`
- [x] T028 [US1] Create `CFG/Access/AccessGrantsPanel.js`, mount it beside the requests panel in
  `CFG/Access/AccessWorkspace.js`, and pass `grants` in the localised config in `CFG/Access/AccessScreen.php`
- [x] T029 [US1] Make the Access screen's copy true for what is recorded (the waiting card and the audit tab's empty
  state) in `CFG/Access/AccessScreen.php`

**Built as** (2026-10-21, DECISIONS #376): the schema test is in `tests/Integration/Access/AccessGrantStoreTest.php`, the
scheduler test in `AccessGrantsTest.php`; listing, revoking, expiry and cancelling are a separate `AccessGrantService`
(with `AccessGrantPresenter`); approval and refusal are `approveRequest()` and `denyRequest()`; the typed name is the
ability key; a grant to a deleted account is not listed; "overridden by a role" is not shown (FR-005 does not ask
for it).

**Checkpoint**: quickstart section 1 passes; T004 to T013 pass.

## Phase 4: User Story 2 - The access-denied log keeps its history (P1)

**Goal**: one entry per person, section and hour, with a count, without pushing out other people's entries.

**Independent test**: [quickstart.md](quickstart.md) section 2.

### Tests for User Story 2

- [x] T030 [P] [US2] Rewrite `tests/Unit/Access/AccessAuditLogTest.php`: merging within 3,600 seconds of `first`, a new
  entry after it, a window across midnight, 20 entries per person before the global cap, 30-day prune, old-shape
  entries read as count 1, page-slug and section keys counted as one
- [x] T031 [P] [US2] Integration test counting database writes for 10 refusals with and without a persistent object
  cache (an in-memory cache drop-in for the test), in `tests/Integration/Access/AccessAuditLogWritesTest.php`
- [x] T032 [US2] Integration test: 1,000 refusals of one person leave every other entry listed, in
  `tests/Integration/Access/AccessAuditLogWritesTest.php` (after T031, same file)

### Implementation for User Story 2

- [x] T033 [US2] Change `CFG/Access/AccessAuditLog.php` to the merged entry of [data-model.md](data-model.md), with
  `wp_cache_incr()` while `wp_using_ext_object_cache()` and one write when the window closes, and an in-place update
  otherwise
- [x] T034 [US2] Normalise the section to the page slug with `AdminPage::pageSlugForSection()` where refusals are
  recorded (`CFG/Access/AccessDeniedGate.php`, `CORE/Admin/AdminPage.php`)
- [x] T035 [US2] Show the count and the first and last time in `AccessScreen::auditRow()` in
  `CFG/Access/AccessScreen.php` and in the audit list of `CFG/Access/AccessWorkspace.js`, in plain words instead of
  raw keys
- [x] T036 [US2] Flush closed windows in the daily sweep of `CFG/Access/AccessGrantScheduler.php`

**Built as** (2026-10-21, DECISIONS #377): T034 is done in `AccessAuditLog::recordDenied()`, which both refusal paths
reach; entries stored before spec 112 are folded on read; the daily `settle()` runs from `AccessGrantScheduler`.

**Checkpoint**: quickstart section 2 passes; `tests/Integration/Foundation/EveryRequestOptionQueriesTest.php` still
passes.

## Phase 5: User Story 3 - Maintenance mode closes public submissions (P1)

**Goal**: in Maintenance, public submissions answer 503 before any handler runs; sign-in and password reset stay
open; the panel says so.

**Independent test**: [quickstart.md](quickstart.md) section 3.

### Tests for User Story 3

- [x] T037 [P] [US3] Unit tests for the shared decision (mode, bypass filter, signed-in `manage_options`) in
  `tests/Unit/Operations/MaintenanceDecisionTest.php`, and keep `tests/Unit/Operations/MaintenanceGuardTest.php`
  passing on it
- [x] T038 [P] [US3] Data-provider integration test over the six marked routes through `rest_do_request()`: 503 with
  `Retry-After: 3600` and `site_in_maintenance`, nothing stored, mail spy empty; accepted for a signed-in administrator,
  in Coming soon and in Production; `account/login`, `account/reset-request` and `account/reset` accepted; read routes
  untouched; in `tests/Integration/Operations/MaintenanceClosesSubmissionsTest.php`
- [x] T039 [P] [US3] Update `tests/Unit/Config/Operations/ModeDisclosureTest.php` for the new Maintenance lines
- [x] T040 [P] [US3] Playwright: the Maintenance panel shows what is closed and what stays open without expanding
  anything, in `tests/e2e/operations-security.spec.js`; Coming soon accepting every submission is proved in T038

### Implementation for User Story 3

- [x] T041 [P] [US3] Create `CORE/Http/PublicSubmission.php` with the marker key constant
- [x] T042 [P] [US3] Create `CFG/Operations/MaintenanceDecision.php` from `MaintenanceGuard::shouldBlock()` without the
  request-context early return, and make `CFG/Operations/MaintenanceGuard.php` call it
- [x] T043 [US3] Create `CFG/Operations/MaintenanceSubmissionGuard.php` on `rest_request_before_callbacks` (503,
  `Retry-After: 3600`, translated message) and register it in `CFG/ConfigServiceProvider.php`
- [x] T044 [P] [US3] Declare the marker on `forms/{slug}` in `plugins/corex-forms/src/Submission/SubmitController.php`
  and on `flows/{id}/submit` in `plugins/corex-forms/src/Submission/FlowSubmissionController.php`
- [x] T045 [P] [US3] Declare the marker on `newsletter/subscribe` in
  `addons/corex-newsletter/src/NewsletterServiceProvider.php`, `bookings/request` in
  `addons/corex-bookings/src/BookingsServiceProvider.php` and `careers/apply` in
  `addons/corex-careers/src/CareersServiceProvider.php`
- [x] T046 [P] [US3] Declare the marker on `account/register` only in `addons/corex-profile/src/ProfileServiceProvider.php`
- [x] T047 [US3] Rewrite the Maintenance lines of `consequences()` and the REST line of `reference()` in
  `CFG/Operations/ModeDisclosure.php`, and the class docblock of `CFG/Operations/MaintenanceGuard.php`

**Checkpoint**: quickstart section 3 passes.

## Phase 6: User Story 4 - An owner without a command line can recover the sign-in (P1)

**Goal**: the Recovery panel gives both routes as copyable code, and the Security Center says when recovery is on.

**Independent test**: [quickstart.md](quickstart.md) section 4.

### Tests for User Story 4

- [x] T048 [P] [US4] Jest tests for the Recovery panel (both routes, what each does, code left to right and copyable),
  the bookmark warning and the "Recovery is on" notice in
  `plugins/corex-config/src/Security/__tests__/securityCenterRecovery.test.js`
- [x] T049 [P] [US4] Jest tests for the copy field (copies, falls back to selected text, `dir="ltr"`) in
  `plugins/corex-config/src/admin/components/__tests__/corexCopyField.test.js`
- [x] T050 [P] [US4] In `tests/Integration/Security/RecoveryOnTest.php`: `recoveryActive` in the screen config
  and the overview row's wording with the constant set and unset
- [x] T051 [P] [US4] Keep `tests/Unit/Security/LoginRouteGuardTest.php` passing (the guard keeps its boolean argument,
  now taken from `LoginRecovery` in the container; DECISIONS #374)

### Implementation for User Story 4

- [x] T052 [P] [US4] Create `CFG/Security/LoginProtection/LoginRecovery.php` (`active(): bool`), bind it in
  `CFG/ConfigServiceProvider.php` where `COREX_LOGIN_UNGUARD` is read, and inject it into
  `CFG/Security/LoginProtection/LoginRouteGuard.php`
- [x] T053 [US4] Inject `LoginRecovery` into `CFG/Security/OperationsSecurityScreen.php`; pass `recoveryActive` in
  `securityConfig()` and make the "Default login endpoints" row in `overviewCard()` read "Reachable (recovery on)"
- [x] T054 [P] [US4] Create `CFG/admin/components/CorexCopyField.js` with the markup and behaviour of
  `StandalonePage::copyField()`
- [x] T055 [US4] Rewrite `Recovery`, the bookmark warning and the Login protection notice in
  `CFG/Security/SecurityCenter.js`, with the code lines in `CorexCopyField` and the words around them translatable
- [x] T056 [P] [US4] Update the recovery steps in `docs/en/03-operations/security.md`

**Checkpoint**: quickstart section 4 passes; the owner's manual check (SC-005) is asked for in the pull request.

## Phase 7: User Story 5 - Blog Pro follows each person's WordPress permissions (P1)

**Goal**: one policy decides every Blog Pro screen element and route, per post and per comment.

**Independent test**: [quickstart.md](quickstart.md) section 5.

### Tests for User Story 5

- [ ] T057 [P] [US5] Unit tests for each rule of [contracts/rest.md](contracts/rest.md#blog-pro-changed-permissions) in
  `tests/Unit/Blog/BlogProPermissionsTest.php`
- [ ] T058 [P] [US5] Role matrix through `rest_do_request()`: Contributor, Author, Editor and Administrator × every
  route and `action` × own and another's post × draft and published × a held comment, including an author's own held
  comment on their own post, in `tests/Integration/Blog/BlogProPermissionMatrixTest.php` (FR-028, SC-006)
- [ ] T059 [P] [US5] Update `tests/Integration/Blog/BlogProControllerTest.php` and
  `tests/Integration/Blog/CommentModerationTest.php` for the new refusals and for `share-click` answering
  `recorded: false`
- [ ] T060 [P] [US5] Jest: controls follow the per-post and per-comment flags in
  `plugins/corex-config/src/Blog/__tests__/blogProActions.test.js`; Playwright as an Author in `tests/e2e/blog-pro.spec.js`

### Implementation for User Story 5

- [ ] T061 [US5] Create `CFG/Blog/BlogProPermissions.php` and bind it in `CFG/ConfigServiceProvider.php`
- [ ] T062 [US5] Use it in every `permission_callback` of `CFG/Blog/BlogProController.php`, refusing with a translated
  message that names what is not allowed; make `share-click` record nothing
- [ ] T063 [US5] Use it in `CFG/Blog/BlogProScreen.php` (menu capability `edit_posts`, `render()`, posts the viewer may
  edit, the authors table) and in `CFG/Blog/BlogProPresenter.php` (per-post `publish_post`, per-comment `edit_comment`
  and `moderate_comments` flags)

**Checkpoint**: quickstart section 5 passes.

## Phase 8: User Story 6 - Security records on the Data screen go only to the people who manage them (P1)

**Goal**: role grants, access requests and sign-in attempts need their owning ability on every Data route.

**Independent test**: [quickstart.md](quickstart.md) section 6.

### Tests for User Story 6

- [ ] T064 [P] [US6] Unit tests: `ManagedTable` keeps working without the new argument and reports the owner ability
  with it, in `tests/Unit/Data/ManagedTableTest.php`; `TableDataSource` exposes it, in
  `tests/Unit/Data/TableDataSourceTest.php`
- [ ] T065 [P] [US6] Integration test as a "Manage data" holder: the three sources are not in the catalogue; list,
  detail, query, export and an older export's download answer 403 `data_forbidden`; each refusal is in the access
  audit as kind `data`; an administrator and a holder of both abilities work as before; every other source works as
  before; in `tests/Integration/Data/RestrictedDataSourcesTest.php`
- [ ] T066 [US6] Integration test: an export set with a restricted model finishes without it and names it with the
  reason; a set made only of restricted models finishes empty with the explanation; in
  `tests/Integration/Data/RestrictedDataSourcesTest.php` (after T065, same file)
- [ ] T067 [P] [US6] Playwright as a "Manage data" holder in `tests/e2e/data-management.spec.js`

### Implementation for User Story 6

- [ ] T068 [P] [US6] Add the optional last argument `?string $ownerAbility = null` to
  `CORE/Database/Schema/ManagedTable.php`
- [ ] T069 [P] [US6] Create `CFG/Data/RestrictedDataSource.php` and `CFG/Data/DataAccessRestricted.php`; implement the
  interface in `CFG/Data/TableDataSource.php`
- [ ] T070 [P] [US6] Create `CORE/Access/AccessRefusalRecorder.php` and implement it in `CFG/Access/AccessAuditLog.php`
- [ ] T071 [US6] Declare `CorexAbility::MANAGE_ACCESS` on the two tables in `CFG/Access/AccessTables.php` and
  `CorexAbility::MANAGE_OPERATIONS` on `CFG/Security/LoginProtection/LoginAttemptTable.php`
- [ ] T072 [US6] Leave restricted sources out of `catalog()` and refuse them in `authorize()` with
  `DataAccessRestricted`, recording through `AccessRefusalRecorder`, in `CFG/Data/DataSourceService.php`; map the
  exception to 403 `data_forbidden` in `CFG/Data/DataRestGateway.php` and `CFG/Data/DataManagementController.php`
- [ ] T073 [US6] Leave restricted models out at request time with reason and label in
  `CFG/DataModels/DataExportService.php` (`requestSeveral()`, `previewSeveral()`, `download()`),
  `CFG/DataModels/DataExportPart.php` and `CFG/DataModels/DataExportAbout.php`
- [ ] T074 [US6] Show the `data` kind in the access audit's sentence in `CFG/Access/AccessScreen.php`

**Checkpoint**: quickstart section 6 passes.

## Phase 9: User Story 7 - Resending an email from the inbox reaches the people it was for (P1)

**Goal**: emails record their recipients; a resend goes to them, or to the re-checked recipients of an older email.

**Independent test**: [quickstart.md](quickstart.md) section 7.

### Tests for User Story 7

- [ ] T075 [P] [US7] Integration test on a capture site with delivery failing: a new submission's bindings hold
  `recipients`; resending the team notification goes to the team address only and never to the submitter; a
  confirmation goes to the submitter; an older submission (no `recipients`) is resent while the flow's recipients still
  match and refused with `recipients_unknown` once they changed; the timeline holds who it went to; in
  `tests/Integration/Submissions/ResendRecipientsTest.php`
- [ ] T076 [US7] Integration test: the recipients are removed by trash and permanent delete and appear in the
  personal-data export, in `tests/Integration/Submissions/ResendRecipientsTest.php` (after T075, same file)
- [ ] T077 [P] [US7] Update `tests/Unit/Submissions/SubmissionEmailServiceTest.php` and
  `tests/Integration/Submissions/SubmissionsControllerTest.php` for the answer's `recipients` and `basis`, and a
  gateway without `MultiRecipientResend` called once per recipient
- [ ] T078 [P] [US7] Jest: the drawer names each email, says why one cannot be resent and who a resend went to, in
  `plugins/corex-config/src/Submissions/__tests__/replyPane.test.js`; Playwright in `tests/e2e/submissions-inbox.spec.js`

### Implementation for User Story 7

- [ ] T079 [P] [US7] Make `plugins/corex-forms/src/Submission/FlowEmailSender.php` return the resolved recipients and
  record them in `EmailStage::outcome()` in `plugins/corex-forms/src/Submission/Stages/EmailStage.php`
- [ ] T080 [P] [US7] Create `CORE/Mail/MultiRecipientResend.php`; implement it in
  `addons/corex-email/src/Studio/EmailStudioSubmissionGateway.php`; leave `CORE/Mail/SubmissionEmailGateway.php`
  unchanged
- [ ] T081 [US7] In `CFG/Submissions/SubmissionEmailService.php`: resend to the recorded recipients, or re-resolve and
  check each with `EmailAttemptRepository::wasSentTo()`; call `MultiRecipientResend` when implemented, else the gateway
  once per recipient; add `kind`, `resendable` and `not_resendable_reason` to each binding; record recipients in the
  timeline
- [ ] T082 [US7] Show the email's name, the reason it cannot be resent and who a resend went to in `RelatedEmails` of
  `CFG/Submissions/detail/Reply.js`, the timeline line in `CFG/Submissions/detail/replyState.js`, and the resend answer
  in `CFG/Submissions/useInbox.js`

**Checkpoint**: quickstart section 7 passes.

## Phase 10: Polish and cross-cutting

- [ ] T083 Every new string translatable with the `corex` text domain; check the new screens right to left (FR-035)
- [ ] T084 WCAG 2.2 AA in dark and light for the approval dialog, the grants list, the audit rows and the recovery
  panel, checked in Playwright (FR-036); measure their spacing on the rendered page
- [ ] T085 [P] Update `docs/en/03-operations/operations-and-security.md` (Maintenance), `docs/en/03-operations/exporting-data.md`
  (restricted models) and `docs/en/03-operations/notifications.md` (grant expiry notices)
- [ ] T086 Add the DECISIONS.md entries for research R1, R4, R7, R8, R12 and R13 at the numbers read in T002, and update
  PROGRESS.md
- [ ] T087 Write the release notes in `CHANGELOG.md` (FR-037), saying what each change does and nothing about how the
  earlier behaviour could be misused, and the client lines for Muva and Perego
- [ ] T088 Guard Gate: `clean-code-guard` and `wp-guard` on every PHP and JS change, `test-guard` on the tests,
  `docs-guard` on the docs; every finding fixed
- [ ] T089 Run the whole integration suite, Jest and the Playwright files above, and every check in
  [quickstart.md](quickstart.md)

## Dependencies

- Within each story: tests, then the corex-core contracts, then corex-config services, then routes, then screens.
- US1 before US2: T036 adds to the scheduler T025 creates.
- US2 before US6's recording (T070, T072): data refusals are counted with US2's merged entries.
- US1's T018 (schema 4) before US6's T071 in the same release, so both table declarations change once.
- US3, US4, US5 and US7 depend on no other story.

Suggested order (research R14): US3, US4, US1, US2, US6, US5, US7, then Phase 10.

## Parallel work

- US3 and US4 touch different files and can be built at the same time; inside US3, T041, T042, T044, T045 and T046 are
  separate files.
- US5 (Blog) and US7 (Submissions and forms) share no file with each other or with US1, US2 and US6.
- Each story's test tasks marked `[P]` can be written at the same time.

## Implementation strategy

- **First increment**: US3 and US4 (smallest, no schema change), merged behind the full suite.
- **Second**: US1 and US2 (schema 4, grants, merged audit entries).
- **Third**: US6, US5, US7.
- One release, R0.1, after all seven and Phase 10; the client sessions are sent the client lines before the tag.
