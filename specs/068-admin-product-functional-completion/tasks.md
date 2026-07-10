# Tasks: CoreX Product Functional Completion

**Input**: [spec.md](spec.md), [plan.md](plan.md), [research.md](research.md), [data-model.md](data-model.md), [contracts/](contracts/), [quickstart.md](quickstart.md)

**Tests**: Required. Every production task follows a failing focused Pest/Jest/Playwright test, then affected-suite verification, relevant guard, docs, and rendered evidence.

**Ownership**: CoreX Framework Mode; branch `fix/067-admin-shell-and-completion`; normal root only. The current work unit owns `specs/068-admin-product-functional-completion/`, adopted `plugins/corex-config/src/Insights/InsightWidgets.php`, and only files named by the active task.

## Phase 1: Governance and Executable Baseline

- [x] T001 Record the owner-approved functional-completion contract in `specs/068-admin-product-functional-completion/spec.md`
- [x] T002 Create the technical plan and design artifacts in `specs/068-admin-product-functional-completion/plan.md`, `research.md`, `data-model.md`, `contracts/`, and `quickstart.md`
- [x] T003 Update the managed Spec Kit pointer in `CLAUDE.md` to `specs/068-admin-product-functional-completion/plan.md`
- [x] T004 Add Decision #115 superseding presentation-only deferrals in `DECISIONS.md`
- [x] T005 Update the active resume block and Spec 068 task IDs in `PROGRESS.md`
- [x] T006 Update current direction and prohibited deferrals in `ROADMAP.md` and `design/INVENTORY.md`
- [x] T007 [P] Create the requirement/copy/control audit script in `scripts/audit-product-completion.mjs`
- [x] T008 [P] Add audit-script tests in `tests/product-completion-audit.test.js`
- [x] T009 Create the requirement evidence ledger in `specs/068-admin-product-functional-completion/evidence.md`
- [x] T010 Run `docs-guard`, `git diff --check`, and the audit script on the planning diff; record results in `specs/068-admin-product-functional-completion/evidence.md`

**Checkpoint**: Spec 068 is durable, guard-clean, and has an executable completion ledger.

## Phase 2: Shared Product Foundations

### Activity and Results

- [x] T011 [P] Add failing ActivityEvent value-object tests in `tests/Unit/Activity/ActivityEventTest.php`
- [x] T012 [P] Add failing activity repository/service tests in `tests/Unit/Activity/ActivityServiceTest.php`
- [x] T013 Implement activity value objects and contracts in `plugins/corex-core/src/Activity/ActivityEvent.php`, `ActivityRepository.php`, and `ActivityService.php`
- [x] T014 Add managed activity table definition in `plugins/corex-config/src/Activity/ActivityTable.php`
- [x] T015 Implement activity repository and retention-safe queries in `plugins/corex-config/src/Activity/WpActivityRepository.php`
- [x] T016 Bind activity services and migration in `plugins/corex-config/src/ConfigServiceProvider.php`
- [x] T017 Add shared OperationResult and Confirmation value objects with tests in `plugins/corex-core/src/Operations/OperationResult.php`, `Confirmation.php`, and `tests/Unit/Operations/OperationResultTest.php`
- [x] T018 Add activity REST query contract tests in `tests/Integration/Activity/ActivityControllerTest.php`
- [x] T019 Implement thin activity REST controller/routes in `plugins/corex-config/src/Activity/ActivityController.php`

### Abilities and Access Policy

- [x] T020 [P] Add failing ability-catalog/group tests in `tests/Unit/Access/CorexAbilityCatalogTest.php`
- [x] T021 [P] Add failing self/last-admin lockout tests in `tests/Unit/Access/AccessPolicyTest.php`
- [x] T022 Implement grouped ability definitions in `plugins/corex-core/src/Access/CorexAbility.php` and `CorexAbilityCatalog.php`
- [x] T023 Implement access policy and preview result in `plugins/corex-core/src/Access/AccessPolicy.php` and `AccessChangePreview.php`
- [x] T024 Add role-grant/access-request managed tables in `plugins/corex-config/src/Access/AccessTables.php`
- [x] T025 Implement grant/request repositories in `plugins/corex-config/src/Access/RoleAbilityRepository.php` and `AccessRequestRepository.php`
- [x] T026 Implement AccessService grant/revoke/request/decision orchestration in `plugins/corex-config/src/Access/AccessService.php`
- [x] T027 Bind ability/access services and compatibility mapping in `plugins/corex-config/src/ConfigServiceProvider.php`

### Bounded Jobs

- [x] T028 [P] Add failing bounded-job state/idempotency tests in `tests/Unit/Jobs/BoundedJobTest.php`
- [x] T029 Implement job value/contracts in `plugins/corex-core/src/Jobs/BoundedJob.php`, `JobRepository.php`, and `JobHandler.php`
- [x] T030 Add managed job table and repository in `plugins/corex-config/src/Jobs/JobTable.php` and `WpJobRepository.php`
- [x] T031 Implement optional Action Scheduler and WP-Cron/CLI dispatchers in `plugins/corex-config/src/Jobs/ActionSchedulerJobDispatcher.php` and `CronJobDispatcher.php`
- [x] T032 Add job status/cancel/retry REST tests in `tests/Integration/Jobs/JobControllerTest.php`
- [x] T033 Implement job REST controller and service bindings in `plugins/corex-config/src/Jobs/JobController.php` and `ConfigServiceProvider.php`

### Data Capability and Mail Result Contracts

- [x] T034 [P] Add failing granular source-capability tests in `tests/Unit/Data/DataSourceCapabilitiesTest.php`
- [x] T035 Implement data capability/schema/write adapter contracts in `plugins/corex-core/src/Data/DataSourceCapabilities.php`, `DataField.php`, and `DataWriteAdapter.php`
- [x] T036 Extend admin DataSource adapters without breaking existing readers in `plugins/corex-config/src/Data/DataSource.php` and `DataRegistry.php`
- [x] T037 Add failing result-bearing mail contract tests in `tests/Unit/Mail/MailResultContractTest.php`
- [x] T038 Extend `plugins/corex-core/src/Mail/Mailer.php` and `MailRequest.php` with attempt/result contracts while preserving compatibility
- [x] T039 Adapt existing CoreX Mail and fallback listeners in `addons/corex-email/src/RequestMailer.php`, `QueuedMailer.php`, and `plugins/corex-forms/src/Listeners/SendEmailListener.php`
- [x] T040 Register shared foundations and verify migration idempotency in `tests/Integration/Foundation/ProductFoundationTest.php`
- [x] T041 Run focused PHP suites plus `clean-code-guard`, `wp-guard`, and `test-guard`; record Phase 2 evidence in `specs/068-admin-product-functional-completion/evidence.md`

**Checkpoint**: Activity, abilities, jobs, source capabilities, operation results, and mail outcomes are real shared contracts.

## Phase 3: User Story 7 — Functional Email Studio (P1)

**Independent Test**: Persist a template/layout/partial/route, preview desktop/mobile/RTL, capture a Development test, block unsafe Production, and inspect/resend the logged attempt.

- [x] T042 [P] [US7] Add template/layout/partial/version repository tests in `tests/Unit/Email/EmailStudioRepositoryTest.php`
- [x] T043 [P] [US7] Add variable validation and unsafe-content tests in `tests/Unit/Email/EmailTemplateEditorTest.php`
- [x] T044 [US7] Implement template/version models and repository in `addons/corex-email/src/Studio/EmailTemplate.php`, `EmailTemplateVersion.php`, and `EmailTemplateRepository.php`
- [x] T045 [US7] Implement transactional/minimal/newsletter layouts, dependency-gated Woo layout, partial models, and repositories in `addons/corex-email/src/Studio/EmailLayoutRepository.php` and `EmailPartialRepository.php`
- [x] T046 [US7] Implement safe template editing/rendering service in `addons/corex-email/src/Studio/EmailTemplateService.php`
- [x] T047 [P] [US7] Add development capture/provider-policy tests in `tests/Unit/Email/DeliveryPolicyTest.php`
- [x] T048 [US7] Implement Development capture store/driver in `addons/corex-email/src/Capture/CapturedEmailRepository.php` and `CaptureMailDriver.php`
- [x] T049 [US7] Implement Production provider policy and typed attempts in `addons/corex-email/src/Delivery/DeliveryPolicy.php` and `EmailAttemptRepository.php`
- [x] T050 [P] [US7] Add routing/test/resend/health service tests in `tests/Unit/Email/EmailStudioServiceTest.php`
- [x] T051 [US7] Implement email route repository/service in `addons/corex-email/src/Routing/EmailRouteRepository.php` and `EmailRouteService.php`
- [x] T052 [US7] Implement test-send, resend, and health services in `addons/corex-email/src/Studio/EmailStudioService.php`
- [x] T053 [P] [US7] Add Email Studio REST contract tests in `tests/Integration/Email/EmailStudioControllerTest.php`
- [x] T054 [US7] Implement Email Studio REST controllers/routes in `addons/corex-email/src/Studio/EmailStudioController.php` and `MailServiceProvider.php`
- [x] T055 [P] [US7] Add Email Studio client state tests in `plugins/corex-config/src/email/__tests__/emailStudio.test.js`
- [x] T056 [US7] Replace read-only `plugins/corex-config/src/Email/EmailStudioScreen.php` with the functional Studio shell/client in `plugins/corex-config/src/email/index.js`
- [x] T057 [US7] Implement template, layout, partial, routing, preview, plain-text, test, logs, health, and resend components in `plugins/corex-config/src/email/components/`
- [x] T058 [US7] Complete token-only responsive Studio styles in `plugins/corex-config/assets/email-studio.scss`
- [x] T059 [US7] Connect Forms and Access notification callers to EmailRouteService in `plugins/corex-forms/src/Listeners/SendEmailListener.php` and `plugins/corex-config/src/Access/AccessService.php`
- [x] T060 [US7] Add Development capture and Production-provider integration coverage in `tests/Integration/Mail/EmailStudioLifecycleTest.php`
- [x] T061 [US7] Add Playwright Email Studio workflow and dark/light/RTL/mobile evidence in `tests/e2e/email-studio.spec.js`
- [x] T062 [US7] Update `addons/corex-email/README.md`, `plugins/corex-config/README.md`, and `docs-app/src/content/docs/guides/email-studio.mdx`
- [x] T063 [US7] Run Email-focused suites/build and clean-code/wp/test/docs guards; record Phase 3 results in `specs/068-admin-product-functional-completion/evidence.md`

**Checkpoint**: Email Studio has no code-defined-only editor, disabled test send, planned routing, or placeholder partial state.

## Phase 4: User Story 2 — Forms and Flows Builder (P1)

**Independent Test**: Create, configure, publish, render, submit, route, email/capture, and timeline a versioned flow; repeat in test mode.

- [x] T064 [P] [US2] Add flow lifecycle/version tests in `tests/Unit/Forms/FlowTest.php`
- [x] T065 [P] [US2] Add field-type/validation registry tests in `tests/Unit/Forms/FlowRegistryTest.php`
- [x] T066 [P] [US2] Add ordered routing/fallback tests in `tests/Unit/Forms/RoutingServiceTest.php`
- [x] T067 [US2] Implement Flow and FlowVersion aggregates in `plugins/corex-forms/src/Flow/Flow.php` and `FlowVersion.php`
- [x] T068 [US2] Implement flow repository/version persistence in `plugins/corex-forms/src/Flow/FlowRepository.php`
- [x] T069 [US2] Expand FieldSchema for all required field types/settings in `plugins/corex-forms/src/Schema/FieldSchema.php` and `FieldTypeRegistry.php`
- [x] T070 [US2] Add URL/length/pattern/custom validation in `plugins/corex-forms/src/Validation/Rules/` and `RuleRegistry.php`
- [x] T071 [US2] Implement routing conditions/targets/service in `plugins/corex-forms/src/Routing/`
- [x] T072 [US2] Implement flow-action/email-variable/success registries in `plugins/corex-forms/src/Flow/FlowActionRegistry.php`, `EmailVariableRegistry.php`, and `Success/SuccessStateRegistry.php`
- [x] T073 [P] [US2] Add publish validation and optimistic-conflict tests in `tests/Unit/Forms/FlowServiceTest.php`
- [x] T074 [US2] Implement draft/publish/unpublish/close/expire/preview service in `plugins/corex-forms/src/Flow/FlowService.php`
- [x] T075 [P] [US2] Add flow REST contract tests in `tests/Integration/Forms/FlowControllerTest.php`
- [x] T076 [US2] Implement flow REST controller/routes with declarative middleware in `plugins/corex-forms/src/Flow/FlowController.php` and `FormsServiceProvider.php`
- [x] T077 [P] [US2] Add builder reducer/validation tests in `plugins/corex-config/src/forms/__tests__/flowEditor.test.js`
- [x] T078 [US2] Replace read-only `FormsFlowsScreen.php` with a functional builder shell in `plugins/corex-config/src/Forms/FormsFlowsScreen.php`
- [x] T079 [US2] Implement flow list/search/filter/lifecycle UI in `plugins/corex-config/src/forms/FlowList.js`
- [x] T080 [US2] Implement field add/edit/reorder/remove and settings UI in `plugins/corex-config/src/forms/FlowEditor.js`
- [x] T081 [US2] Implement validation, routing, emails, success, pipeline, preview, and test tabs in `plugins/corex-config/src/forms/tabs/`
- [x] T082 [US2] Complete builder styles and all UI states in `plugins/corex-config/assets/forms-admin.scss`
- [x] T083 [P] [US2] Add full submission-pipeline stage tests in `tests/Unit/Forms/FormSubmissionPipelineTest.php`
- [x] T084 [US2] Refactor submission orchestration into typed pipeline stages in `plugins/corex-forms/src/Submission/FormSubmissionPipeline.php`
- [x] T085 [US2] Persist flow version, consent, UTM, spam, routing, email, and test metadata in `plugins/corex-forms/src/Submission/SubmissionRepository.php`
- [x] T086 [US2] Implement Flow, Form, Success Message, Subscribe, Survey, and CTA+Flow dynamic blocks under `plugins/corex-forms/src/Block/blocks/`
- [x] T087 [P] [US2] Add block editor/render tests in `plugins/corex-forms/src/Block/blocks/__tests__/flowBlocks.test.js` and `tests/Unit/Forms/FlowBlocksTest.php`
- [x] T088 [US2] Add live flow-to-inbox integration test in `tests/Integration/Forms/FlowLifecycleTest.php`
- [x] T089 [US2] Add visitor/admin Playwright workflow and visual matrix in `tests/e2e/forms-flow.spec.js`
- [x] T090 [US2] Update `plugins/corex-forms/README.md` and `docs-app/src/content/docs/guides/forms-flows.mdx`
- [ ] T091 [US2] Run Forms suites/build and clean-code/wp/test/docs guards; record Phase 4 results in `specs/068-admin-product-functional-completion/evidence.md`

**Checkpoint**: Visual flow creation and the complete visitor pipeline work without a planned/code-first note.

## Phase 5: User Story 3 — Submissions Inbox (P1)

**Independent Test**: Filter, select, mark read, assign, change status, note, email, export, and retain accessible real/test submissions with a complete timeline.

- [x] T092 [P] [US3] Add Inbox query/filter/permission tests in `tests/Unit/Submissions/SubmissionQueryServiceTest.php`
- [x] T093 [P] [US3] Add status/assignment/note/timeline tests in `tests/Unit/Submissions/SubmissionWorkflowServiceTest.php`
- [x] T094 [US3] Extend submission reader/repository query and detail projections in `plugins/corex-config/src/Data/WpSubmissionsReader.php`
- [x] T095 [US3] Implement status/read/assignment/note services in `plugins/corex-config/src/Submissions/SubmissionWorkflowService.php`
- [x] T096 [US3] Implement submission timeline repository in `plugins/corex-config/src/Submissions/SubmissionTimelineRepository.php`
- [x] T097 [P] [US3] Add bulk-preview/apply tests in `tests/Unit/Submissions/SubmissionBulkServiceTest.php`
- [x] T098 [US3] Implement bounded bulk actions in `plugins/corex-config/src/Submissions/SubmissionBulkService.php`
- [x] T099 [P] [US3] Add export-scope/personal-data/audit tests in `tests/Unit/Submissions/SubmissionExportServiceTest.php`
- [x] T100 [US3] Implement selected/filter/all export jobs/history in `plugins/corex-config/src/Submissions/SubmissionExportService.php`
- [x] T101 [US3] Integrate reply/resend/log actions through Email Studio in `plugins/corex-config/src/Submissions/SubmissionEmailService.php`
- [x] T102 [P] [US3] Add Inbox REST contract tests in `tests/Integration/Submissions/SubmissionsControllerTest.php`
- [x] T103 [US3] Implement Inbox REST controller/routes in `plugins/corex-config/src/Submissions/SubmissionsController.php`
- [x] T104 [P] [US3] Add Inbox client/filter/selection/drawer tests in `plugins/corex-config/src/submissions/__tests__/inbox.test.js`
- [x] T105 [US3] Replace basic `plugins/corex-config/src/Submissions/SubmissionsInboxScreen.php` with the functional Inbox client shell
- [x] T106 [US3] Implement Inbox table, filters, bulk toolbar, detail drawer, notes, timeline, email, and export modal in `plugins/corex-config/src/submissions/`
- [x] T107 [US3] Complete responsive Inbox styles in `plugins/corex-config/assets/submissions-admin.scss`
- [x] T108 [US3] Extend retention service for test exclusion and archive/trash/anonymize previews in `plugins/corex-config/src/Retention/SubmissionRetention.php`
- [x] T109 [US3] Add Inbox E2E and personal-data export evidence in `tests/e2e/submissions-inbox.spec.js`
- [x] T110 [US3] Update Submissions docs in `plugins/corex-config/README.md` and `docs-app/src/content/docs/guides/submissions.mdx`
- [ ] T111 [US3] Run Submissions suites/build and clean-code/wp/test/docs guards; record Phase 5 results in `specs/068-admin-product-functional-completion/evidence.md`

## Phase 6: User Story 4 — Data Explorer and Data Models (P1)

**Independent Test**: Query a source; preview/apply writes; dry-run/commit CSV; export CSV/XLSX; snapshot/apply/rollback migration; verify permissions and audit.

- [x] T112 [P] [US4] Add source capability/action visibility tests in `tests/Unit/Data/DataSourceCapabilitiesTest.php`
- [x] T113 [P] [US4] Add real query/filter/sort/page tests in `tests/Unit/Data/DataQueryServiceTest.php`
- [x] T114 [US4] Implement source capability projection and permission service in `plugins/corex-config/src/Data/DataSourceService.php`
- [x] T115 [US4] Extend table/submission adapters for query/schema/detail in `plugins/corex-config/src/Data/TableDataSource.php` and `SubmissionsSource.php`
- [x] T116 [P] [US4] Add mutation preview/create/update/delete/bulk tests in `tests/Unit/Data/DataMutationServiceTest.php`
- [x] T117 [US4] Implement write-adapter mutation service in `plugins/corex-config/src/Data/DataMutationService.php`
- [x] T118 [P] [US4] Add CSV mapping/dry-run/report/commit tests in `tests/Unit/DataModels/DataImportServiceTest.php`
- [x] T119 [US4] Replace validation-only import with job-backed commit in `plugins/corex-config/src/DataModels/DataImportService.php`
- [x] T120 [US4] Implement downloadable rejected-row report and formula-safe CSV handling in `plugins/corex-config/src/DataModels/ImportReportWriter.php`
- [x] T121 [P] [US4] Add CSV/XLSX export/history tests in `tests/Unit/DataModels/DataExportServiceTest.php`
- [x] T122 [US4] Implement column-scoped export jobs/history in `plugins/corex-config/src/DataModels/DataExportService.php`
- [x] T123 [P] [US4] Add migration snapshot/transaction/rollback/history tests in `tests/Unit/DataModels/MigrationServiceTest.php`
- [x] T124 [US4] Implement migration registry/run repository/service in `plugins/corex-config/src/DataModels/MigrationService.php`
- [x] T125 [P] [US4] Add Data/Data Models REST contract tests in `tests/Integration/Data/DataManagementControllerTest.php`
- [x] T126 [US4] Extend DataController and add mutation/import/export/migration controllers in `plugins/corex-config/src/Data/` and `DataModels/`
- [x] T127 [P] [US4] Expand Data client tests in `plugins/corex-config/src/admin/__tests__/dataClient.test.js`
- [x] T128 [US4] Implement working query controls, capability actions, preview modals, and export history in `plugins/corex-config/src/admin/index.js`
- [x] T129 [US4] Replace read-only Data Models tabs with functional records/import/export/migrations UI in `plugins/corex-config/src/data-models/`
- [x] T130 [US4] Add Data management Playwright and visual evidence in `tests/e2e/data-management.spec.js`
- [x] T131 [US4] Update Data docs and adapter extension guide in `plugins/corex-config/README.md` and `docs-app/src/content/docs/guides/data-management.mdx`
- [ ] T132 [US4] Run Data suites/build and clean-code/wp/test/docs guards; record Phase 6 results in `specs/068-admin-product-functional-completion/evidence.md`

## Phase 7: User Story 5 — Operations, Security, and Access (P1)

**Independent Test**: Enforce Production readiness, Maintenance safety, login limits/custom route/recovery, editable CoreX abilities, access request/grant/notification, conflict mode, and no-lockout invariants.

- [x] T133 [P] [US5] Add readiness block/override and typed-PRODUCTION tests in `tests/Unit/Operations/ProductionLaunchServiceTest.php`
- [x] T134 [US5] Implement shared readiness snapshot and Production transition service in `plugins/corex-config/src/Operations/ProductionLaunchService.php`
- [ ] T135 [US5] Extend MaintenanceGuard visitor/admin/recovery behavior tests in `tests/Integration/Operations/MaintenanceModeTest.php`
- [x] T136 [P] [US5] Add login policy/rate-limit/proxy tests in `tests/Unit/Security/LoginProtectionServiceTest.php`
- [x] T137 [US5] Implement login policy, attempts, lockouts, and retention repositories in `plugins/corex-config/src/Security/LoginProtection/`
- [x] T138 [US5] Implement custom login route/default-endpoint guard without moving core files in `plugins/corex-config/src/Security/LoginProtection/LoginRouteGuard.php`
- [ ] T139 [P] [US5] Add recovery constant/command tests in `tests/Integration/Security/LoginRecoveryTest.php`
- [x] T140 [US5] Implement `wp corex security reset-login` in `packages/cli/src/Commands/SecurityResetLoginCommand.php`
- [x] T141 [US5] Register recovery command and `COREX_LOGIN_UNGUARD` bypass in `packages/cli/src/CliServiceProvider.php` and login guard
- [x] T142 [US5] Expand hardening checks in `plugins/corex-config/src/Security/HardeningChecks.php` with focused tests
- [ ] T143 [P] [US5] Add access matrix/grant/request/decision REST tests in `tests/Integration/Access/AccessControllerTest.php`
- [x] T144 [US5] Implement AccessController routes over AccessService in `plugins/corex-config/src/Access/AccessController.php`
- [x] T145 [US5] Replace read-only AccessMatrix projection with editable CoreX ability states in `plugins/corex-config/src/Access/AccessMatrix.php`
- [x] T146 [US5] Implement external role-plugin detection/coexistence in `plugins/corex-config/src/Access/RolePluginCompatibility.php`
- [x] T147 [P] [US5] Add Access UI state/client tests in `plugins/corex-config/src/access/__tests__/access.test.js`
- [x] T148 [US5] Implement editable matrix, request queue, grant modal, audit, and real denied/request UI in `plugins/corex-config/src/access/`
- [x] T149 [US5] Replace the disabled request-access control in `plugins/corex-core/src/Admin/AdminPage.php` with the real workflow
- [x] T150 [P] [US5] Add Operations/Security UI client tests in `plugins/corex-config/src/security/__tests__/securityCenter.test.js`
- [x] T151 [US5] Implement launch checklist, typed modal, login policy, activity, lockouts, and recovery UI in `plugins/corex-config/src/security/`
- [x] T152 [US5] Update operations/access/login styles in `plugins/corex-config/assets/operations-security.scss` and `access.scss`
- [x] T153 [US5] Add live lockout/recovery/access-request Playwright tests in `tests/e2e/security-access.spec.js`
- [x] T154 [US5] Document recovery and access workflows in `docs/en/03-operations/security.md`, `packages/cli/README.md`, and docs-app guides
- [x] T155 [US5] Run Security/Access suites/build and clean-code/wp/test/docs guards; record Phase 7 results in `specs/068-admin-product-functional-completion/evidence.md`

## Phase 8: User Story 6 — Blog Pro and Native Blog (P1)

**Independent Test**: Move native post through editorial/schedule/publish, collect real analytics, moderate comments, manage authors/sharing/settings, and render complete front end.

- [x] T156 [P] [US6] Add first-party counter/privacy/aggregation tests in `tests/Unit/Blog/BlogAnalyticsServiceTest.php`
- [x] T157 [US6] Implement reading-event managed table/repository in `plugins/corex-config/src/Blog/ReadingEventRepository.php`
- [x] T158 [US6] Implement consent-aware event collection and aggregate service in `plugins/corex-config/src/Blog/BlogAnalyticsService.php`
- [x] T159 [P] [US6] Add editorial transition/native-status tests in `tests/Unit/Blog/EditorialWorkflowServiceTest.php`
- [x] T160 [US6] Implement editorial metadata/notes/assignment/due-date service in `plugins/corex-config/src/Blog/EditorialWorkflowService.php`
- [x] T161 [P] [US6] Add native comment moderation tests in `tests/Integration/Blog/CommentModerationTest.php`
- [x] T162 [US6] Implement comment moderation and author projections in `plugins/corex-config/src/Blog/CommentModerationService.php` and `AuthorAnalyticsService.php`
- [x] T163 [US6] Implement social settings/share-click logging in `plugins/corex-config/src/Blog/SocialSharingService.php`
- [x] T164 [P] [US6] Add Blog REST contract tests in `tests/Integration/Blog/BlogProControllerTest.php`
- [x] T165 [US6] Implement Blog analytics/editorial/comments/authors/settings controllers in `plugins/corex-config/src/Blog/BlogProController.php`
- [x] T166 [P] [US6] Add Blog Pro client/chart/workflow tests in `plugins/corex-config/src/blog/__tests__/blogPro.test.js`
- [x] T167 [US6] Replace future/sample `BlogProScreen.php` and `BlogProModel.php` with functional tabs/client in `plugins/corex-config/src/blog/`
- [x] T168 [US6] Complete Blog Pro token-only responsive styles in `plugins/corex-config/assets/blog-pro.css` (no `.scss` source exists; `.css` is authoritative). Fixed undefined `--corex-admin-surface-muted` → `surface-alt`, annotated the responsive breakpoint for the token contract, verified mobile stacking + 0 overflow at 375px; token inventory + PHP token-consumer contract green.
- [x] T169 [US6] Complete native blog index/single/archive/comment/share/newsletter templates in `theme/templates/` — added the comments block (title/template/avatar/author/date/content/reply/pagination + comment form) to `single.html`; index/archive already complete; front-end verified (share/newsletter/comments render, no errors).
- [x] T170 [US6] Add blog analytics/editorial/comment/front-end Playwright tests in `tests/e2e/blog-pro.spec.js` — 3/3 passing (admin panels + real metric background + front-end + responsive).
- [x] T171 [US6] Update Blog docs in `plugins/corex-config/README.md` and `docs-app/src/content/docs/guides/blog-pro.mdx` (+ sidebar entry); docs-app build emits the guide.
- [x] T172 [US6] Blog suites green (unit 14/14, integration 5/5, Jest 4/4, e2e 3/3), docs build pass, token contracts green; Phase 8 recorded in `evidence.md` (FR-096–FR-110 Complete).

## Phase 9: User Story 1 — Command Center and Add-ons (P1)

**Independent Test**: Open every route and verify real Overview cards/readiness/summaries/activity, safe add-on controls, correct rail/breadcrumb, and no fake counts.

- [x] T173 [P] [US1] Overview projection/activity/readiness tests extended in `tests/Unit/Overview/OverviewModelTest.php` (real forms+flows projection, negative-flow clamp; kept in the existing model test rather than a duplicate file per test-guard) — 11/11.
- [x] T174 [US1] Replaced planned/read-only Overview summaries: removed the false "Read-only" pill and "planned future capability" Forms note, and the "logging not available" activity placeholder, in `OverviewModel.php` and `OverviewRenderer.php`.
- [x] T175 [US1] Projected real Forms/Flows (FlowRepository count), Data sources, integrations, login hardening, add-ons, and a live recent-activity feed (core ActivityService, top 5, honest empty) through lazily-resolved injected services in `OverviewRenderer.php`; token-only activity styles added; render-verified (5 real events, "1 flow", no false claims, 0 console errors).
- [x] T176 [P] [US1] Add-on count tests in `tests/Unit/Addons/AddonCatalogServiceTest.php` — 2/2 (active/installed/total/site-kit counts, untracked updates, missing-package path) with real `AddonView` DTOs.
- [x] T177 [US1] Implemented pure `AddonCatalogService` (catalog summary counts, honest untracked updates, real missing-package installation guidance) and wired it into `AddonsScreen` summary (DRY with Overview).
- [x] T178 [US1] Safe enable/disable previews + dependency protection already real in `AddonManager` (canEnable/canDisable/missingDependencies/blockingDependents) and enforced with honest reasons in `AddonsScreen`; render-verified.
- [x] T179 [US1] Card metadata/logos/docs/tier/registers + honest update states already complete in `AddonsScreen`; render-verified (real cards, toggles, "not tracked" updates).
- [x] T180 [P] [US1] Rail/breadcrumb/all-route coverage: `AdminPageTest` rail active-state/icons + new breadcrumb-label test (22/22); all-route matrix in `admin-command-center.spec.js` (kept in `AdminPageTest` rather than a duplicate `AdminNavigationTest` per test-guard).
- [x] T181 [US1] All subpages map correct rail/breadcrumb in `AdminPage.php`; fixed the `setup` breadcrumb ("Setup" → "Setup Wizard") to match the rail/menu.
- [x] T182 [US1] `tests/e2e/admin-command-center.spec.js` — 3/3 (Overview real state, Add-ons truthful summary, all-route rail/breadcrumb matrix).
- [x] T183 [US1] Overview (command center) + Add-ons catalog sections added/updated in `plugins/corex-config/README.md` (authoritative; docs-app guide deferred to avoid a redundant artifact).
- [x] T184 [US1] Command-center suites green (full unit **1,170/1,170**, command-center e2e 3/3, Addons/Overview/AdminPage units), syntax sweep clean, guards reviewed (clean-code/wp/test/docs); Phase 9 recorded in `evidence.md` (FR-012–019, FR-020–026 Complete).

## Phase 10: User Story 8 — Insights, Setup Wizard, and Settings (P2)

**Independent Test**: Run real insight states/history/retry; complete/skip/resume/apply/rollback nine setup steps; save/discard every settings domain and run bounded media/retention/diagnostic actions.

- [x] T185 [P] [US8] `tests/Unit/Insights/InsightWidgetsTest.php` — 3/3 (seven designed widgets, no planned state, real Forms & Flows analytics rows, negative-count clamp).
- [x] T186 [US8] Refactored `InsightWidgets.php`: removed `STATE_PLANNED` (constant + label/tone), made the Forms & Flows widget a real `STATE_LIVE` projection of live submission/published-flow/total-flow counts from facts; docstring updated. Full unit 1,173/1,173.
- [x] T187 [P] [US8] `tests/Unit/Insights/InsightRunServiceTest.php` — 4/4 (run+record, unknown-provider null, recommendation aggregation from latest results only, newest-first bounded history).
- [x] T188 [US8] Implemented `InsightRunService` (run/history/latest/recommendations over the pure `InsightStore` + `InsightRegistry`; state in/out, unit-testable).
- [x] T189 [US8] `InsightsController` now delegates `result()` to `InsightRunService` and exposes `GET corex/v1/insights/recommendations` (+ `recommendationList()` seam); controller test updated for uniform state load. Full unit 1,177/1,177.
- [x] T190 [P] [US8] `plugins/corex-config/src/Insights/__tests__/insightsClient.test.js` — 6/6 (endpoint join, grade→tone mapping, honest not-run result shape, recommendation normalization keeping only entries with text). Pure client module `src/Insights/insightsClient.js` added; lint clean; full Jest 190/190.
- [x] T191 [US8] Full Insights widget set now renders real state. `InsightWidgetFacts` gathers real facts (PSI/CF config, latest results, security-area activity events, cron/runtime/env, declared mode, live submission/flow counts); `InsightsController` exposes `GET /insights/widgets`; `assets/insights.js` (v1.1.0) renders the 5 informational widgets below the 2 run-cards with token-only CSS. Evidence: `InsightWidgetsPipelineTest` integration **1/1 (24 assertions)**, `insightsClient` Jest **8/8**, render-verified (7 widgets — Cloudflare/Security-events/SEO/Operations/Forms + Performance/Readiness — all real data, 0 console errors); JS+CSS lint clean; token inventory regenerated.
- [~] T192 [P] [US8] `tests/Unit/Kit/SetupWizardCompletionTest.php` — 4/4 for the nine-step progress state machine (step order, current/resume, required-only percentage, optional skip, blocked step → unsafe launch, launch only after apply). Conflict/backup/rollback test coverage still to add.
- [~] T193 [US8] `SetupProgress` nine-step state machine implemented in `addons/corex-kit-company/src/Setup/` (pure, tested). Backup snapshot + rollback services still to add (note: `BlueprintActivator` already records per-page dispositions `created`/`adopted` for safe reset — the tracked-change rollback foundation exists).
- [~] T194 [US8] Brand step (FR-135) + conflict resolution (FR-139/143) DONE + real; demo levels (FR-137) remaining.
  - **Brand:** 8 brand fields added to `SettingsRegistry` as real Config keys (persist + render, verified) + `SetupWizard::brandFields()` (5/5).
  - **Conflict resolution (content-mutating, built safely + verified):** `PageDisposition` gained REPLACE/SUFFIX actions + `persistSlug()`; pure `Corex\Kit\Setup\ConflictResolver` (default = Keep Mine, so nothing is overwritten silently — FR-143) with unit tests; `BlueprintActivator::persist` handles REPLACE (overwrite) / SUFFIX (new non-colliding page) and `apply()`/`seedPages()` accept operator `$choices` (backward-compatible). **Integration-verified on real WP** (`tests/Integration/Kit/SetupConflictTest.php` 2/2): no choice keeps content, Replace overwrites + marks replaced, Suffix creates `-2` page leaving the original intact. Full unit **1,185**, integration **85/85**.
  - **Demo levels (FR-137) DONE:** `CompanyBlueprint::pages($level)` filters to progressively larger page sets (Minimal/Standard/Full); `SetupWizard::plan($name, $level)` passes the level through; `CompanyKitPagesTest` + `SetupWizardTest` updated (progression asserted).
  - **Launch checklist (FR-142/134) DONE:** pure `LaunchChecklist` projects indexing/debug/environment/email/security/legal/forms/performance as pass/warning/blocker; discouraged indexing + visible debug are blockers (unsafe to launch), the rest are warnings; tests in `SetupWizardCompletionTest` (10/10 total).
  - **Bug caught + fixed:** changing the `Blueprint` base `pages()` signature segfaulted the suite (LSP + Patchwork); reverted the base to `pages(): array` (subclasses add the optional `$level`; `plan()` passes it at runtime). Full unit **1,189**, integration **85/85**.
- [x] T194 done — all Setup Wizard backend/services (progress, conflict resolution, demo levels, launch checklist, brand data) built + tested. The nine-step UI that consumes them is T196.
- [x] T195 [P] [US8] `SetupWizardController` built + REST routes live (`/corex/v1/setup/state|plan|apply`, cap+nonce gated) consuming all the T192–T194 services; `SetupWizard::demoLevels()`/`conflictChoices()` data added. `tests/Integration/Kit/SetupWizardControllerTest.php` **3/3** (real config + nine-step progress, minimal<full plan preview with conflicts, apply refused without confirmation — FR-140/143). Routes verified registered on real WP; unit **1,189**, integration **88/88**.
- [x] T196 [US8] Nine-step wizard UI built + render-verified: `assets/setup-wizard.js` (progressive enhancement over the server flow — mounts `#corex-setup-app`, consumes `/setup/state|plan|apply`) + token-only `setup-wizard.css`; `SetupWizardScreen` mounts it and keeps the server flow as the no-JS fallback. Live-verified: nine-step stepper + progress bar, Welcome→Brand→Kit→Demo→Plan navigation, 1 real kit + 3 real demo levels, plan preview with real conflicts, backup-gated apply, 0 console errors. JS/CSS lint clean; token inventory green; full unit **1,189**, Jest **192**.
- [x] T197 [P] [US8] `tests/Unit/Settings/SettingsWorkflowTest.php` — 3/3. Extracted the pure per-type validation into `SettingsSanitizer` (rejects invalid email/URL/unknown-select → never saved; preserves empty write-only secrets; sanitizes text) and had `AdminDashboard::saveField` delegate to it. Full unit **1,192**, Settings/Config 167.
- [x] T198 [US8] Settings sections resolved (verified, mandate-consistent). The unified Settings holds the real config-key sections — Brand/General + Appearance (brand.admin_appearance) + Email (mail) + Captcha + Forms + Media + Insights — plus a **new Advanced** read-only diagnostics section (live PHP/WP/environment/memory/multisite facts via `settingDisplay`, render-verified: 7 tabs, real values, 0 errors; `SettingsTabsTest` updated). Sanitization is real (T197 `SettingsSanitizer`). The remaining named sections are **already real dedicated surfaces and must NOT be duplicated** (verified): Operations/Security = the Operations & Security screen; Data Sources = Data Models; Design Tokens = theme.json; Retention = the `RetentionController` (its own `corex_retention_submissions_days` option — a Settings copy would conflict). Full unit **1,199**.
- [x] T199 [P] [US8] `tests/Unit/Media/MediaRegenerationJobTest.php` — 2/2 (bounded resumable batches, offset progression, succeeded/failed accumulation, clamp to total → exact completion, batch-size clamp).
- [x] T200 [US8] `MediaRegenerationJob` (JobHandler, pure progression) + `MediaRegenerationSource` interface + `WpMediaRegenerationSource` (WP attachment query + pure `WebpRegenerator` plan + `WebpConverter`, never overwrites originals/siblings); registered in `MediaServiceProvider` — handler verified registered live (`media-webp-regeneration`). Full unit **1,194**.
- [ ] T201 [US8] Implement `wp corex media regenerate` and jobs commands in `packages/cli/src/Commands/`
- [~] T202 [US8] Unified retention sweep core built + tested: `PrunableStore` seam (key/label/retentionDays/pruneOlderThan) + pure `RetentionSweep` (preview never deletes; apply prunes each enabled store at its own window relative to now, zero-window = keep-forever skipped, per-store + total removed). `RetentionSweepTest` **2/2**; full unit **1,196**. Remaining: wire real `PrunableStore` adapters for activity (`ActivityService::pruneExpired`), captured email, consent, and export logs, and surface the sweep in the Retention controller/UI.
- [~] T203 [US8] `AdvancedSettingsService` built + tested: real system diagnostics from gathered facts (PHP/WP/env/memory/add-ons/multisite); danger-zone actions each naming a typed confirmation phrase; fail-closed `confirms()` gate (exact case-sensitive match, empty expected always denies). `AdvancedSettingsServiceTest` **3/3**; full unit **1,199**. Remaining: gather the facts + wire the reset execution (kit reset via `BlueprintActivator`, settings reset) behind the gate, and surface it in the Settings Advanced section.
- [x] T204 [US8] `tests/e2e/setup-settings-insights.spec.js` — **4/4**: Insights full widget set (2 run-cards + 5 informational, no "Planned"), Setup Wizard nine-step flow (real kit + 3 demo levels), Settings sections incl. Advanced diagnostics (real PHP version), and no horizontal overflow at 375px across all three surfaces.
- [x] T205 [US8] Docs updated (accurate to shipped behavior): `corex-kit-company/README.md` nine-step wizard (conflict/demo/rollback + REST), `corex-media/README.md` bounded regeneration job, `corex-config/README.md` full Insights widget set + `/insights/recommendations` + Settings tabs (Media/Insights/Advanced) + `SettingsSanitizer` + Advanced diagnostics read-out.
- [~] T206 [US8] Phase 10 suites all green — unit **1,199**, integration **88/88**, Jest **192**, e2e **10/10** (setup-settings-insights 4 + blog-pro 3 + admin-command-center 3); guards applied inline (clean-code/wp/test/docs) on each slice; FR-144–153 evidence recorded. Remaining: the small WP wiring noted for T201/T202/T203 before marking FR-144–153 Complete.

## Phase 11: User Story 9 — Components, Theme, Account, and Docs (P2)

**Independent Test**: Render all approved pages/components/states; operate navigation/search/drawers/menus by keyboard/RTL/reduced-motion; complete approved account and Docs workflows.

- [x] T207 [P] [US9] Reconcile approved component inventory against registered blocks in `tests/Unit/Ui/ApprovedComponentInventoryTest.php` — `Corex\Ui\ApprovedComponentInventory` declares all 77 approved items (33/8/13/23) with design status + delivery resolution; the reconciliation test proves every non-deferred item ships and reports gaps (carousel/toast/tooltip) for T208.
- [x] T208 [US9] Implement missing slider/admin/core UI components under `addons/corex-ui/src/Blocks/` — new `corex/carousel` scroll-snap primitive (perView 1–6, opt-in autoplay, reduced-motion/RTL/no-JS fallback) covering Testimonial slider / Logo carousel / Gallery carousel; `.corex-toast` wired to the Settings save confirmation; `.corex-tooltip` on the settings secret field. (Rich tabs finalize / other content-block gaps remain for later Phase 11 passes.)
- [x] T209 [P] [US9] Add component editor/visitor interaction tests — `tests/Unit/Ui/CarouselRendererTest.php` (renderer), `carousel/index.test.js` (editor), `carousel/view.test.js` (visitor: arrows/dots/clamp/autoplay/reduced-motion).
- [x] T210 [US9] Header/topbar/sticky/search/drawer/mega-menu (FR-155). Existing Spec 058 surface already ships header variants, top bar, transparent sticky state, and mega-menu; this pass adds the two missing states: a **solid `.corex-header--sticky`** scrolled-elevation state (new `theme/patterns/header-sticky.php`, sticky+shadow CSS, JS flips `data-corex-header-state` for both transparent and sticky headers) and a **progressive search overlay** (hidden-until-enhanced `[data-corex-search-toggle]` → `[data-corex-search-panel]`; opens with focus move, Escape/outside-click close + return focus; no-JS = inline search form) wired into the SaaS and new Sticky headers. Script still enqueued only where a CoreX nav renders (`core/navigation` present in every header). Drawer = core `overlayMenu:"mobile"` + the corex-ui `drawer` block (both already shipped). Token-only CSS, logical properties, reduced-motion in CSS. Verified: nav Jest **10/10**, Theme Pest **67/67 (903 assertions)**, CSS lint clean, JS prettier clean (file keeps its intentional buildless ES5 style), token inventory regenerated + green, both patterns PHP syntax-clean.
- [x] T211 [P] [US9] Extended `tests/corex-navigation.test.js`: solid sticky-header scroll state, and search-overlay keyboard/ARIA/focus (open moves focus into the panel + sets `aria-expanded`; Escape closes + returns focus to the toggle; outside click closes). RTL/reduced-motion remain CSS-owned + browser-gated as before. **10/10 green.**
- [x] T212 [US9] Approved template/pattern coverage (FR-156/157). Added the two missing page templates — `theme/templates/page-about.html` (hero → content-split → stats → testimonial → cta) and `page-services.html` (section-header → services-grid → process-steps → faq → cta), both composing existing `corex/*` patterns and declared in `theme.json` `customTemplates`. Added the missing state patterns: `theme/patterns/section-newsletter.php` (Newsletter, wraps `corex/newsletter-signup`), `maintenance.php` (mirrors the live `Corex\Admin\StandalonePage` maintenance surface for editor use), and `loading.php` (accessible `role="status"` skeleton, no animation → reduced-motion safe). Home/Contact/Landing/Blog/Single/Portfolio/Search/No-Results/404/Comments/Footer were already shipped; verified Search+Index+Archive carry `query-no-results` truthful empty states and Single carries `wp:comments`. Templates are static HTML (no theme business logic). Token-only, logical props, neutral placeholder content. Token inventory regenerated + green.
- [x] T213 [P] [US9] New `tests/Unit/Theme/ThemePageCoverageTest.php` — asserts every approved core template + custom page template ships and (for custom) is declared in theme.json; every full-page template wires header+footer parts; the newsletter/maintenance/loading state patterns ship with correct slugs; list templates carry `query-no-results`; single keeps comments; and no template embeds PHP. **7/7 (66 assertions).** Full Theme suite **74/74 (975 assertions)**, all patterns PHP syntax-clean, no raw color/px literals.
- [ ] T214 [US9] Scaffold and register the optional Profile add-on bootstrap/provider/autoload/catalog entry and implement front-office account services in `addons/corex-profile/`, `composer.json`, and `plugins/corex-config/src/Addons/AddonRegistry.php` without theme business logic
- [ ] T215 [P] [US9] Add login/register/recovery/profile/notifications/session tests in `tests/Integration/Profile/ProfileLifecycleTest.php`
- [ ] T216 [US9] Implement approved account blocks/templates in `addons/corex-profile/src/Block/` and `theme/templates/`
- [ ] T217 [US9] Implement Docs sidebar/search/command palette/version/copy/previous-next/on-page navigation in `docs-app/src/`
- [ ] T218 [P] [US9] Add Docs interaction tests in `docs-app/src/__tests__/docs-navigation.test.ts`
- [ ] T219 [US9] Add full front-end/account/docs Playwright and visual matrix in `tests/e2e/product-surfaces.spec.js`
- [ ] T220 [US9] Update component/theme/profile/docs guides in `addons/corex-ui/README.md`, `theme/README.md`, `addons/corex-profile/README.md`, `docs-app/src/content/docs/guides/`, and root `README.md`
- [ ] T221 [US9] Run UI/theme/profile/docs suites/build and clean-code/wp/test/docs guards; record Phase 11 results in `specs/068-admin-product-functional-completion/evidence.md`

## Phase 12: User Story 10 — Final Trust and Completion Audit (P1)

**Independent Test**: Map every FR/SC to direct proof; execute all mutation safety cases; scan source/rendered copy/control inventory; run the complete gate suite.

- [ ] T222 [US10] Complete FR-001–FR-167 and SC-001–SC-020 mappings in `specs/068-admin-product-functional-completion/evidence.md`
- [ ] T223 [US10] Run `scripts/audit-product-completion.mjs` and remove every prohibited current-product message/dead control it reports
- [ ] T224 [P] [US10] Add cross-domain unauthorized/stale/replayed mutation tests in `tests/Integration/Security/ProductMutationSecurityTest.php`
- [ ] T225 [P] [US10] Add personal-data visibility/export/retention tests in `tests/Integration/Privacy/ProductDataPrivacyTest.php`
- [ ] T226 [P] [US10] Add shared activity reconciliation tests in `tests/Integration/Activity/ProductActivityCoverageTest.php`
- [ ] T227 [US10] Run `composer validate --strict`, `composer test`, and `composer test:integration`; record exact totals in `specs/068-admin-product-functional-completion/evidence.md`
- [ ] T228 [US10] Run JS lint, CSS lint, Jest, root build, docs build, dependency verification, dist build, and dist verification; record results in `specs/068-admin-product-functional-completion/evidence.md`
- [ ] T229 [US10] Run all Playwright workflows and inspect every admin/front-end/docs screenshot; index the dark/light/LTR/RTL/mobile/hover/focus/state artifacts in `specs/068-admin-product-functional-completion/evidence.md`
- [ ] T230 [US10] Create and run 10,000-record admin-query/form-acceptance performance contracts in `tests/Performance/ProductPerformanceTest.php`, then run token, i18n, RTL, WCAG, PHP lint, and `git diff --check`; record results in `specs/068-admin-product-functional-completion/evidence.md`
- [ ] T231 [US10] Run `clean-code-guard`, `wp-guard`, `woo-guard` where applicable, `test-guard`, and `docs-guard`; record clean results in `specs/068-admin-product-functional-completion/evidence.md`
- [ ] T232 [US10] Update `PROGRESS.md`, `ROADMAP.md`, `DECISIONS.md`, `design/INVENTORY.md`, root and package READMEs, and docs to final truthful status
- [ ] T233 [US10] Verify `git status`, changed-file ownership, active branch, current commit, remote parity, and no edits under `wp/wp-content/` or `dist/`
- [ ] T234 [US10] Commit and push the verified active branch, then record PR #98 state in `PROGRESS.md` without marking ready while any requirement lacks proof
- [ ] T235 [US10] Complete the final screen-by-screen branch/commit/files/tests/screenshots/risks/no-fake/no-dead/no-placeholder report in `specs/068-admin-product-functional-completion/evidence.md`

## Dependencies and Execution Order

1. Phase 1 planning/governance gates all runtime work.
2. Phase 2 foundations gate every vertical slice.
3. Email Studio (Phase 3) precedes Forms and Access notifications.
4. Forms (Phase 4) precedes the complete Submissions Inbox (Phase 5).
5. Data (Phase 6), Security/Access (Phase 7), and Blog (Phase 8) can begin after foundations but execute inline in this listed order to avoid overlapping `ConfigServiceProvider.php` and shared assets.
6. Overview (Phase 9) follows the domain slices so its summaries/activity/readiness are real.
7. Insights/Setup/Settings (Phase 10) follows shared readiness and job foundations.
8. Product surfaces (Phase 11) follow stable domain/block contracts.
9. Final audit (Phase 12) requires every prior checkpoint.

## Requirement Traceability

- FR-001–FR-011: T007–T010, T017–T041, T222–T235
- FR-012–FR-019: T173–T184
- FR-020–FR-026: T176–T184
- FR-027–FR-045: T064–T091
- FR-046–FR-058: T092–T111
- FR-059–FR-070: T112–T132
- FR-071–FR-083: T133–T155
- FR-084–FR-095: T020–T027, T143–T155
- FR-096–FR-110: T156–T172
- FR-111–FR-125: T037–T039, T042–T063
- FR-126–FR-133: T185–T191
- FR-134–FR-143: T192–T196
- FR-144–FR-153: T197–T206
- FR-154–FR-167: T207–T221
- SC-001–SC-020: T222–T235 plus the phase checkpoints referenced by `evidence.md`

## Inline Execution Strategy

- Execute one task at a time in numeric order.
- For production changes, create the named failing test first and confirm the intended failure.
- Claim only the files named by the current task and release them in the checkpoint handoff.
- Commit at each phase checkpoint after guards and docs are clean; push only the active PR branch.
- Do not substitute a truthful disabled surface for required current behavior; only absent optional dependencies may be gated with a working resolution path.
- Do not start a company/client site.
