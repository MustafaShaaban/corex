# Evidence Ledger: CoreX Product Functional Completion

This ledger maps direct completion evidence to Spec 068. An empty row is incomplete. Indirect evidence, intent, or a green unrelated suite does not count.

## Planning and Governance

| Task | Evidence | Result |
|---|---|---|
| T001 | `spec.md`: 167 unique FR IDs and 20 unique SC IDs | Pass |
| T002 | `plan.md`, `research.md`, `data-model.md`, `contracts/`, `quickstart.md` | Pass |
| T003 | `CLAUDE.md` managed block points to Spec 068 plan | Pass (manual fallback after extension script Python/YAML failure) |
| T004 | `DECISIONS.md` #115 | Pass |
| T005 | Top `PROGRESS.md` resume block | Pass |
| T006 | `ROADMAP.md` §17 and `design/INVENTORY.md` | Pass |
| T007 | `scripts/audit-product-completion.mjs` and pure `scripts/product-completion-audit.mjs` | Pass |
| T008 | `tests/product-completion-audit.test.js`: 9/9 Jest tests | Pass |
| T009 | This ledger | Pass |
| T010 | Clean-code, test, and docs guard reviews; focused JS lint; `git diff --check`; baseline audit | Pass (audit intentionally exits 1 with the 31 known completion findings below) |

## Baseline Audit

- Repository root: `C:\wamp64\www\corex`
- Active branch: `fix/067-admin-shell-and-completion`
- Baseline commit: `3ce717b`
- Remote parity at audit: `HEAD...origin/fix/067-admin-shell-and-completion = 0/0`
- Worktrees: one, normal root
- Adopted prior work: `plugins/corex-config/src/Insights/InsightWidgets.php`
- Design authority: `F:\Work\CoreX.zip`
- Primary `.dc.html` files read/inventoried: 44
- WordPress: 7.0
- Theme: `corex` active, version 0.33.0
- Required plugins: `corex-core`, `corex-blocks`, `corex-config`, `corex-forms` active
- Boot probe: `BOOT_OK`
- `wp corex doctor`: pass with one non-blocking neutral-brand recommendation

### Baseline contradictions to completion

- Forms & Flows explicitly describes code-first, planned, read-only behavior.
- Access & Abilities explicitly disables the request workflow and role editing.
- Blog Pro identifies itself as a future/reference surface and renders sample analytics.
- Email Studio disables editing/test/routing/partials and describes planned capabilities.
- Data Models stops at read-only/validation preview without write commit.
- Operations & Security defers login protection.
- Overview includes planned/read-only summaries and no unified event store.
- Setup remains a three-step chooser rather than the approved nine-step launch workflow.
- Adopted Insights work contains a `Planned` state.
- Roadmap, progress, decisions, and design inventory previously authorized conflicting deferrals.

Executable baseline scan: 31 findings in 12 files. Rule totals: code-defined-editor 1; disabled-required-action 1;
future-add-on 6; planned-capability 6; planned-state 5; read-only-surface 7; reference-layout 2; sample-data 3.
This is expected failure evidence and remains a completion blocker until T223 reaches zero.

## Spec Kit Quality

- Specification checklist: all items passing
- Clarification questions: zero; owner requirements and standing recommended-choice instruction resolved critical scope decisions
- Tasks: 235, unique IDs 235, format failures 0
- Story tasks: US1 12; US2 28; US3 20; US4 21; US5 23; US6 17; US7 22; US8 22; US9 15; US10 14
- Requirement ranges: FR-001–FR-167 and SC-001–SC-020 mapped in `tasks.md`
- Constitution issues: none found
- Consistency remediation applied: Profile add-on scaffolding, Woo email layout, missing-package guidance, named settings domains, and concrete performance contract

## Phase Evidence

| Phase | Focused tests | Runtime/E2E | Visual matrix | Guards | Docs | Status |
|---|---|---|---|---|---|---|
| 1 | 9/9 Jest tests pass; focused JS lint passes | N/A | N/A | Clean-code, test, and docs guards pass | Complete | Complete |
| 2 | Full unit 997/997 (4,309 assertions); full integration 43/43 (137 assertions); final focused unit 67/67 and integration 11/11 | `BOOT_OK`; ACF-aware driver resolution; four managed schemas exist and migrate twice without error; activity/job REST round trips pass | N/A (shared contracts only) | Clean-code, WP, and test guards pass; all changed/new PHP syntax passes; Composer valid; `git diff --check` clean | Tasks/evidence/PROGRESS synchronized | Complete |
| 3 | Full unit 1,027/1,027 (4,441 assertions); full integration 52/52 (202 assertions); full Jest 141/141 | Live WP 7.0 REST-backed screen and schema migration; Playwright 3/3 passes | Dark/light/RTL and 375px overflow matrix passes; no console errors | Clean-code, WP, test, docs, UI/UX guards pass; JS/CSS lint, 45-file PHP syntax, Composer, dependency security, builds, token inventory, diff check pass | Add-on/config/root README, architecture, and Email Studio guide updated; docs build 278 pages | Complete |
| 4 | Full unit 1,070/1,070 (4,610 assertions); full integration 59/59 (252 assertions); full Jest 156/156; focused Forms unit 79/79 (302 assertions), integration 11/11 (61 assertions) | Versioned REST builder, six dynamic blocks, seven-stage visitor/test pipeline, real Email Studio capture, Inbox/timeline persistence; Config/Forms builds pass | Playwright admin/visitor + 375/768/1024/1440/RTL test implemented; launch pending external approval credit | Clean-code/WP/test/docs guards applied with findings fixed; PHP/token/diff checks pass; scoped JS/CSS lint pending external approval credit | Forms README + Forms & Flows guide; docs build 279 pages | In progress (T091 external checks pending) |
| 5 | Full unit 1,093/1,093 (4,738 assertions); full integration 63/63 (294 assertions); full Jest 161/161; focused Inbox/Retention unit 34/34 (171 assertions), focused integration 5/5 (56 assertions) | Permission-scoped Inbox REST, optimistic workflow, single-use bulk previews, Email Studio reply/resend/log, bounded private exports, archive/trash/anonymize retention; Config/docs builds pass | Playwright full workflow + personal-data export and 375/768/1024/1440/RTL source implemented; launch pending external approval credit | Clean-code/WP/test/docs guard findings fixed; PHP/token/diff checks pass; default Pest needed 256 MB because Patchwork exhausted 128 MB; JS/CSS lint pending external approval credit | Config README + Submissions Inbox guide; docs build 280 pages | In progress (T111 external checks pending) |
| 6 | Pending | Pending | Pending | Pending | Pending | Pending |
| 7 | Pending | Pending | Pending | Pending | Pending | Pending |
| 8 | Pending | Pending | Pending | Pending | Pending | Pending |
| 9 | Pending | Pending | Pending | Pending | Pending | Pending |
| 10 | Pending | Pending | Pending | Pending | Pending | Pending |
| 11 | Pending | Pending | Pending | Pending | Pending | Pending |
| 12 | Pending | Pending | Pending | Pending | Pending | Pending |

### Phase 2 shared-foundation evidence

- Activity: immutable secret-rejecting events, append-only indexed repository, bounded retention, capability-gated
  collection/detail REST, and a unified managed data source.
- Access: ten approved ability groups, explicit/inherited/denied/locked states, self/last-admin policy, role grants,
  access-request decisions, administrator compatibility mapping, and bounded user discovery.
- Jobs: database-enforced active idempotency, immutable state transitions, Action Scheduler/WP-Cron fallback,
  one-step runner, and nonce + ability-gated status/cancel/retry REST.
- Data: granular source capabilities, typed/privacy-aware fields, optional write adapter, conservative legacy-source
  inference, and explicit table/submission descriptors.
- Mail: legacy `Mailer::send()` compatibility plus correlation-aware result contracts for queued, captured, sent,
  failed, rejected, and legacy-accepted attempts; fallback `wp_mail()` now returns its real outcome to the listener.
- Safety/performance: all variable SQL uses placeholders or WordPress CRUD helpers; migrations are version-gated;
  runtime role effects are request-cached; large role lookups are paged; mutation routes require ability + nonce.
- Debugging evidence: the only initial full-integration failure was a stale test assumption that ACF was absent;
  runtime inspection confirmed ACF 6.8.4 active and `FieldResolver` correctly selected `AcfFieldDriver`. The test now
  asserts the available provider in both optional-dependency states.

### Phase 3 functional Email Studio evidence

- Persistence/rendering (T042–T046): immutable template drafts and activation, structured five-region layout
  revisions, five reusable partials, dependency-gated Woo layout, schema-validated variables, partial expansion,
  selected-layout rendering, automatic/manual plain text, and rejection of blank/unsafe/executable content.
- Delivery (T047–T052): Development capture cannot contact the provider; staging/production fail closed unless the
  configured provider matches the bound driver and live delivery is deliberate; attempts retain redacted recipient
  evidence, typed state/provider events, correlation IDs, provenance, and immutable retry lineage.
- Routing/consumers (T050–T052, T059): existing templates only, active-version dispatch, literal or whitelisted
  context recipient/reply-to rules, and neutral `RoutedMailer` integration for Forms and Access with safe fallbacks.
- REST/UI (T053–T058): `manage_options` reads and nonce-gated writes; all eleven approved sections call real routes;
  selectable layout revisions; revision prefill; sandboxed desktop/mobile/RTL composition; truthful environment,
  provider, capture, attempt, health, and resend states; no disabled required action or fabricated result.
- Runtime/visual (T060–T061): WordPress integration proves Development route→render→capture→attempt and blocked/
  approved Production paths. Live browser shows the rebuilt screen, three native version-2 layouts after idempotent
  schema upgrade, populated variables/partials, settings resolution path, RTL `srcdoc`, zero console warnings/errors,
  and `scrollWidth <= clientWidth` at 375px while only the intended tab rail scrolls.
- Guard remediation: server now rejects blank subject/body, unavailable layout revisions, and routes referencing
  unavailable templates; preview mode buttons expose `aria-pressed`; integration cleanup deletes only test-created
  records and preserves the developer site's default assets. The client is split into focused panels and state/API
  hooks; routing now separates rule resolution, active-template preparation, and policy-controlled dispatch; the
  REST boundary receives a typed repository record rather than nine constructor arguments.
- Verification: full unit 1,027/1,027 (4,441 assertions), full integration 52/52 (202 assertions), full Jest
  141/141, focused Playwright 3/3, scoped JS/CSS lint, Config production build, docs build (278 pages), Composer,
  dependency security, PHP syntax (45 production files), token inventory generation/drift tests, and
  `git diff --check` all pass. Guard remediation also added explicit WordPress package metadata, label/control
  associations, translator context, token-inventory coverage for untracked source, and a saved-session E2E fallback.

### Phase 4 functional Forms & Flows evidence

- Domain/REST (T064–T076): private Flow/FlowVersion persistence, canonical checksums, optimistic conflicts,
  publication validation, lifecycle transitions, 16 built-in field types, bounded pattern validation, first-match
  routing, stable extension registries, declared admin middleware, public nonce/sanitize/throttle submission, and
  explicit unpublished/invalid-flow rejection.
- Builder/blocks (T077–T087): REST-backed list/create/search/filter/editor; immutable field add/edit/reorder/remove;
  validation/routing/active-template email/success/preview/test stages; six persisted-flow dynamic blocks; legacy
  registered Form compatibility; conditional runtime/styles; accessible field/help/error semantics; token-only
  logical CSS with reduced-motion behavior.
- Pipeline (T083–T088): validation → protection → storage → routing → email → Inbox → timeline, including optional
  provider-neutral captcha verification, flow/version/consent/UTM/spam/routing/email/test evidence, bound active
  Email Studio template + recipient/reply-to mapping, Development capture, and fail-fast preservation of stored
  state. Real WordPress integration proves published visitor → captured email → Inbox → timeline.
- Guard remediation: fixed an invalid leading CSS token, undefined front-end color token, multi-select incorrectly
  entering the radio-group renderer, unsafe/unbounded custom regex evaluation, incomplete publish validation,
  missing server field-error envelopes, unpublished-flow exceptions, and runtime-bound Email Studio route identity.
- Verification so far: full unit 1,070/1,070 (4,610 assertions), integration 59/59 (252 assertions), Jest 156/156,
  focused Forms 79/79 unit + 11/11 integration, Config/Forms production builds, docs build (279 pages), token inventory,
  PHP syntax, and `git diff --check` pass. T089 is implemented; Playwright launch and scoped JS/CSS lint remain pending
  because the Codex app rejected required external execution after its approval-credit limit was reached.

### Phase 5 functional Submissions Inbox evidence

- Query/workflow (T092–T096): normalized search/flow/status/owner/date/test filters, repository-level record scope,
  permission-first counts/detail, six statuses, unread state, user/team/role assignment, attributed team/restricted
  notes, optimistic `updated_at` conflicts, and an append-only privacy-safe timeline. Trashed or inaccessible records
  resolve identically as unavailable.
- Bulk/export/email (T097–T103): exact selections are capped at 100; actor-bound preview tokens expire after five
  minutes and are consumed once; stale/inaccessible members abort the whole action before mutation. Accessible,
  filtered, and selected exports require explicit columns and personal-data acknowledgement, exclude marked tests by
  default, run through the bounded-job system, store CSV privately, mark exported records, and write scope/count/
  columns to history and shared activity without copying payload values. The optional Email Studio add-on implements
  a neutral reply/resend/redacted-log gateway and reconstructs retries from immutable template versions.
- Client/retention (T104–T110): the REST-backed Inbox has responsive filters/table/unread/test states, selection and
  bulk confirmation, detail/assignment/status/read controls, fields/metadata/UTM/consent, notes, email actions,
  timeline, export/history/download modal, pagination, and empty/loading/error/success states. Retention excludes tests
  unless explicitly included and offers confirmed archive, recoverable trash, and personal-data anonymization with
  timeline evidence.
- Guard remediation: corrected an invalid over-20-character WordPress post type, delayed Email Studio resolution until
  `rest_api_init` to avoid early translation/theme access, excluded marked tests from ordinary Data/dashboard counts,
  prevented trashed detail access, stripped note markup, mapped hidden missing/inaccessible detail to the same 404,
  added first-class drawer assignment, registered every new token consumer, and documented raw layout allowances.
- Verification so far: full unit 1,093/1,093 (4,738 assertions) with a 256 MB test-process limit, integration 63/63
  (294 assertions), Jest 161/161, focused Inbox/Retention 34/34 unit + 5/5 integration, Config production build, docs
  build (280 pages), token inventory/drift, PHP syntax, synchronized CSS/SCSS, and `git diff --check` pass. T109 is
  implemented; Playwright launch and JS/CSS lint remain pending because the app-level approval-credit gate rejects the
  required external execution path.

### Phase 6 Data foundation evidence (T112–T117)

- Source/query: granular capabilities are projected through actor abilities; write actions are hidden unless the
  source exposes a concrete `DataWriteAdapter`. Declared filters and sortable fields are validated before adapters,
  page sizes are source-capped, and table/submission sources provide real query, count, schema, and detail paths.
- Mutations: create, update, delete, bulk update, and bulk delete validate exact IDs and writable fields before issuing
  an actor-bound five-minute preview. Preview tokens are transient-backed, hashed at rest, consumed once, re-authorized
  on apply, capped at 100 bulk records, dispatched only through the source adapter, and linked to shared activity
  without copying record values into audit context.
- Verification so far: all Data unit tests **76/76 (280 assertions)**; mutation and adapter-visibility tests
  **13/13 (101 assertions)**; changed PHP syntax and `git diff --check` pass.
- Import (T118–T120): exact/alias/explicit mapping, reject-or-ignore unknown columns, required/email/select/length
  validation, personal-data classification, immutable accepted and rejected rows, checksum-bound confirmed commit,
  private WordPress run persistence, shared bounded-job dispatch, accepted-row-only adapter writes, queued/completed
  activity counts, and formula-safe rejected-row CSV. Data Models tests **16/16 (70 assertions)** pass.
- Export (T121–T122): filtered, selected, and all scopes use real accessible query/detail counts; explicit columns,
  source format capability, and personal-data acknowledgement are enforced; private actor-scoped history is queued
  through bounded jobs; CSV chunks neutralize formulas and XLSX is a valid inline-string OpenXML ZIP with no formula
  cells. Binary artifacts are base64-safe at the WordPress meta boundary. Data Models tests **22/22 (108 assertions)**.
- Migrations (T123–T124): source-declared plans remain read-only through preview; five-minute actor-bound tokens are
  consumed once; apply creates a provider snapshot before queueing; bounded jobs execute the exact unchanged plan and
  preserve transactional metadata; supported rollback reuses the original snapshot; failed runs retain recovery and
  history evidence. Import/export/migration actions now hide when their required adapters are absent. Combined Data
  and Data Models tests **104/104 (434 assertions)** pass; module PHP syntax and diff checks are clean.
- REST (T125–T126): canonical routes expose source catalog/query/detail, one-time mutation preview/apply, bounded
  server-side CSV upload/remap/report/commit, export history/download, and migration preview/apply/rollback. Mutations
  require REST nonce before parsing or state changes. Focused verification is **110/110 unit (448 assertions)** and
  **5/5 real WordPress integration (36 assertions)**. Integration boot also regressed and fixed PHP-invalid SQL
  argument unpacking plus URL-unsafe underscore table-source keys.
- Client/Data Explorer (T127–T128): `plugins/corex-config/src/admin/dataClient.js` serializes nested filters and
  exposes reducer/capability helpers plus mutation/export/migration endpoint builders. The Data Explorer is a
  functional React client with source selection, schema/action projection, search/filter/sort/page controls, detail
  fetch, create/edit/delete/bulk mutation preview/apply dialogs, export queue/history, empty/loading/error/success
  states, and action visibility derived from source capabilities.
- Data Models workspace (T129): the legacy server-rendered/read-only tabs are replaced with the shared React bundle
  mounted by `DataModelsScreen`. Models, Import, Export, and Migrations panels call the canonical `corex/v1/data`
  routes for real source schemas/actions, CSV upload/dry-run/remap/report/commit, CSV/XLSX exports with personal-data
  acknowledgement and base64 artifact download, and migration preview/apply/rollback history.
- E2E/docs (T130–T131): `tests/e2e/data-management.spec.js` covers the Data query/detail/export path, Data Models tab
  workflow, and responsive/RTL overflow probes for `corex-data` and `corex-data-models`. The CoreX Config README,
  `docs-app/src/content/docs/guides/data-management.mdx`, `docs-app/src/content/docs/guides/data.md`, and the design-gap
  status page now describe the shipped capability-driven Data/Data Models workflows and adapter extension contract.
- Verification update (T127–T132, current run on 2026-07-07): focused Data/DataModels unit suite **116/116
  (497 assertions)**; Data admin client contracts **5/5 (42 assertions)**; mutation/export/migration subset **19/19
  (123 assertions)**; token contracts **21/21 (166 assertions)**; full unit suite **1,134/1,134 (5,023 assertions)**;
  focused JS **17/17** across `dataClient.test.js` and `dataModels.test.js`; `plugins/corex-config` production build
  passes; docs-app build passes with **281 pages** and the existing non-fatal sitemap/site warning; `node --check
  tests/e2e/data-management.spec.js` passes; `git diff --check` passes. Current full integration rerun is blocked
  because the local WAMP MySQL service is stopped/inaccessible: direct `mysqld.exe` launch did not bind port 3306,
  `Start-Service wampmysqld64` failed with service access denied, and WordPress bootstrap returns "Error establishing
  a database connection". T132 remains open for a real current integration total, JS/CSS lint, and Playwright
  execution; JS/CSS lint and browser launch are also still constrained by the Codex app approval-credit gate recorded
  in earlier Phase 4/5 evidence.

### Phase 7 Operations/Security foundation evidence (T133–T134)

- Production readiness gate (T133–T134): `ReadinessSnapshot` records immutable readiness checks with pass/warning/
  blocking/unavailable states and a stable target hash. `ProductionReadinessSnapshotFactory` builds that snapshot
  from the same real WordPress hardening facts used by the Operations & Security page, mapping hardening warnings to
  blocking Production launch checks. `ProductionLaunchService` issues actor-bound, five-minute confirmations that
  require typing `PRODUCTION`; blocking snapshots do not switch modes without the typed override, wrong phrases are
  blocked, and consumed confirmations cannot be replayed in the same operation context.
- Admin wiring (T134): `OperationsModeController` now routes Production transitions through the readiness factory and
  `ProductionLaunchService`, while Maintenance keeps the existing explicit checkbox path. The Operations & Security
  form exposes a typed Production confirmation input plus a live blocker count sourced from the shared snapshot, so
  the visible launch gate and mutation gate share one evidence source.
- Verification update (T133–T134, current run on 2026-07-07): `php vendor/bin/pest tests/Unit/Operations` passes
  **31/31 tests (86 assertions)**; PHP syntax passes for `ReadinessSnapshot.php`, `ProductionLaunchService.php`,
  `ProductionLaunchRequest.php`, `ProductionLaunchPreview.php`, `ProductionLaunchOverride.php`,
  `ProductionReadinessSnapshotFactory.php`, `OperationsModeController.php`, and `OperationsSecurityScreen.php`.
  Focused stale-copy scan over Operations/Security sources finds no false read-only/future/mutation-deferral wording.
- Maintenance safety source (T135): `tests/Integration/Operations/MaintenanceModeTest.php` now covers
  `template_redirect` registration priority, anonymous front-end blocking, signed-in administrator recovery, and an
  explicit `corex_maintenance_bypass` emergency filter that bypasses only the guard response without changing the
  stored mode. `MaintenanceGuard` honors that filter after confirming Maintenance mode and before request-context
  checks. PHP syntax passes for the guard and integration file; focused Operations unit remains **31/31 (86
  assertions)**. The focused integration command is still externally blocked at WordPress bootstrap by the local WAMP
  MySQL refusal (`No connection could be made because the target machine actively refused it`), so T135 remains open
  until the DB-backed integration test can actually execute.
- Login protection policy/store (T136–T137): `LoginProtectionServiceTest` covers disabled policy behavior, repeated
  failure threshold lockouts, sliding-window release, success logging toggle, retention dates, hashed identity/network
  evidence, and trusted-proxy IP resolution for IPv4 and IPv6 without accepting spoofed forwarded headers.
  `plugins/corex-config/src/Security/LoginProtection/` now contains immutable settings/context/decision/attempt
  records, pure policy/service logic, `ClientIpResolver`, a `LoginAttemptStore` port, `LoginAttemptTable`, and
  `WpLoginAttemptStore` with retention pruning over a managed CoreX table. Provider wiring registers the store and
  table schema with the shared migration/Data registry. Verification: focused login protection unit **5/5 (22
  assertions)**, full Security unit **31/31 (78 assertions)**, LoginProtection PHP syntax clean, and `git diff
  --check` clean.
- Custom login route guard (T138): `LoginRouteGuard` registers a custom-slug rewrite and blocks anonymous default
  `/wp-login.php` and `/wp-admin` access with an honest 404 decision when login protection is enabled, without moving
  or renaming WordPress core files. Authenticated users, `COREX_LOGIN_UNGUARD`, disabled policy, the custom slug
  with or without a trailing slash, and unrelated public routes are allowed. Runtime binding resolves settings from
  `LoginProtectionSettingsStore` and registers the guard disabled-by-default until the admin UI enables it.
  Verification: `LoginRouteGuardTest` **4/4 (12 assertions)** and full Security unit **35/35 (90 assertions)** pass;
  route guard/settings PHP syntax and `git diff --check` are clean.
- Login recovery command (T139–T141): `tests/Integration/Security/LoginRecoveryTest.php` covers the unguard recovery
  path and the DB-backed `wp corex security reset-login` behavior that disables protected-login settings, releases
  active lockouts, preserves users/password hashes, and reports the restored login URL. The integration command is
  currently blocked by the same local WAMP MySQL refusal at WordPress bootstrap, so T139 remains open until it can
  execute. T140–T141 implementation is covered by `SecurityResetLoginCommandTest`, which verifies disabling
  `enabled`/`block_default_endpoints`, preserving unrelated settings, reporting the login URL, and calling the
  lockout-release port. `SecurityResetLoginCommand` is registered as `corex security reset-login`, depends on
  `LoginLockoutStore`, and `LoginRouteGuard` honors `COREX_LOGIN_UNGUARD`. Verification: CLI recovery unit **1/1
  (6 assertions)**, full Security unit **35/35 (90 assertions)**, changed CLI/LoginProtection PHP syntax, and `git
  diff --check` clean; DB-backed integration pending.
- Expanded hardening checks (T142): Security Center hardening now includes six locally verified checks: HTTPS, disabled
  file editor, hidden debug display, no default `admin` account, search indexing allowed for Production launch, and
  configured WordPress authentication unique keys/salts. `HardeningFacts` gathers indexing via `blog_public` and
  validates all eight auth key/salt constants without remote probes or invented state. Verification: focused
  `HardeningChecksTest` **5/5 (21 assertions)**, full Security unit **36/36 (98 assertions)**, changed hardening PHP
  syntax, and `git diff --check` clean.
- Access REST boundary (T143–T144): `tests/Integration/Access/AccessControllerTest.php` now covers route
  registration, role ability preview/apply with confirmation, and access request creation/approval without depending
  on the protected login route. The integration command is DB-blocked at WordPress bootstrap by the local WAMP MySQL
  refusal, so T143 remains open until it can execute. `AccessController` is implemented over `AccessService` with
  REST nonce/capability gates, role preview/apply, request create/decision routes, catalog projection, bounded
  response arrays, and provider registration. Verification: AccessController/provider/integration-test PHP syntax
  clean; focused Access+Security unit **67/67 (199 assertions)**; `git diff --check` clean; DB-backed integration
  pending.
- Editable CoreX ability matrix (T145): `AccessMatrix` keeps its legacy truthful native-capability projection for
  compatibility and now adds `editableCorexMatrix()` over `CorexAbilityCatalog` and explicit role effects. The new
  matrix projects every CoreX-owned ability with group/risk/locked metadata, per-role `allow`/`deny`/`inherit` state,
  editable flags, locked-definition reasons, and external-role-plugin context that leaves native/platform capabilities
  read-only while keeping CoreX ability states visible. Verification: focused `AccessMatrixTest` **12/12 (38
  assertions)**, Access+Security unit **69/69 (208 assertions)**, syntax, and `git diff --check` clean.
- External role-plugin compatibility (T146): `RolePluginCompatibility` centralizes detection of known role/capability
  plugins (Members, User Role Editor, PublishPress Capabilities, Advanced Access Manager, WPFront User Role Editor)
  and reports coexistence state: native platform capabilities become read-only while CoreX-owned abilities remain
  editable. `AccessMatrix` now delegates conflict detection to this seam. Verification: focused compatibility/matrix
  tests **14/14 (43 assertions)**, Access+Security unit **71/71 (213 assertions)**, syntax, and `git diff --check`
  clean.
- Access UI state/client tests (T147): `plugins/corex-config/src/access/accessState.js` defines pure Access client
  helpers for REST endpoint construction, editable CoreX matrix normalization, staged per-role ability effects,
  locked-definition protection, preview/apply notices, request queue state, modal state, and recoverable errors.
  Verification: focused Jest `plugins/corex-config/src/access/__tests__/access.test.js` **4/4** passes.
- Access workspace client (T148): `AccessScreen` now localizes editable CoreX ability matrix state, denied-audit
  entries, REST root, and nonce into `window.corexAccess`, mounts `corex-access-app` on the Role Matrix tab, and
  enqueues the shared admin bundle. `AccessWorkspace` renders role selection, editable per-ability state controls,
  locked-definition disabled states, conflict messaging, request queue and audit panels, while stale read-only/
  deferred editor copy was removed from the screen and comments. Verification: focused Access Jest **4/4**,
  `plugins/corex-config` production build passes (`index.js` 123 KiB), changed Access PHP syntax clean, and `git
  diff --check` clean.
- Real denied request workflow (T149): `AdminPage::permissionDenied()` and `deniedSurface()` now replace the old
  disabled "Request access" placeholder with a real POST form to `corex/v1/access/requests`, including a REST nonce,
  a mapped CoreX ability target per denied section, and a required bounded reason field. `AccessDeniedGate` reuses the
  same denied surface for the true WordPress menu-level 403 path, and `AccessController` accepts `_wpnonce` for
  progressive form posts in addition to the JavaScript `X-WP-Nonce` header. Verification: changed PHP syntax clean;
  focused `AdminPageTest` **17/17 (75 assertions)**; focused AdminPage+Access unit **52/52 (190 assertions)**.
- Operations/Security client tests (T150): `plugins/corex-config/src/Security/securityCenterState.js` and focused
  Jest coverage now define the Security Center client-state contract for stable REST endpoints, Production readiness
  blocker normalization, typed `PRODUCTION` launch confirmation, Maintenance confirmation, login-policy edit
  serialization without raw credential fields, lockout summaries, recovery results, activity rows, and recoverable
  errors. Verification: focused Security Center Jest **5/5**.
- Operations/Security workspace UI (T151): `SecurityCenter` now mounts from the shared admin entry on
  `#corex-security-app` and renders the launch checklist, typed Production confirmation dialog, Maintenance
  confirmation, login-policy controls, lockout summary/list, CLI recovery guidance, activity feed, and recoverable
  notices from localized real Operations/Security facts. `OperationsSecurityScreen` enqueues the shared admin bundle,
  localizes readiness snapshots, login-protection settings, mode history activity, nonce, REST root, and recovery
  command, while preserving the existing server-side nonce-gated mode form as a fallback. Verification: focused
  Security Center Jest **5/5**, combined Access+Security Jest **9/9**, Operations+Security PHP unit **67/67 (184
  assertions)**, changed PHP syntax clean, production Config build passes (`index.js` 134 KiB), stale placeholder scan
  reviewed, and `git diff --check` clean apart from the known line-ending warning on `AdminPage.php`.
- Operations/Access/Login styles (T152): the existing direct CSS sources (`operations-security.css`, `access.css`, and
  shared `corex-admin-shell.css`) now style the Security Center workspace, launch checklist, typed confirmation,
  login-policy controls, lockouts/recovery/activity cards, editable Access workspace panels, and denied request-access
  form using CoreX admin tokens and logical properties. Verification: touched-CSS `git diff --check` clean, no new raw
  color/directional-property hits (only pre-existing select background-position hits in the shell), combined
  Access+Security Jest **9/9**, and Config production build passes (`index.js` 134 KiB).
- Security/Access E2E source (T153): `tests/e2e/security-access.spec.js` now covers the live Operations & Security
  workspace rendering (launch checklist, typed Production dialog, login policy, lockouts, recovery, activity), recovery
  command review, an Access REST request through localized `window.corexAccess`/`window.Corex.api`, and responsive/RTL
  containment for Security and Access workspaces. Verification: `node --check tests/e2e/security-access.spec.js` passes;
  full Playwright execution remains external-gated with the local browser/WP environment.
- Security/recovery/access docs (T154): added `docs/en/03-operations/security.md`, documented
  `wp corex security reset-login` in `packages/cli/README.md`, added the docs-app Security Center guide, and registered
  it in the Starlight sidebar. The docs cover Production readiness/typed confirmation, Maintenance confirmation, login
  protection, lockout recovery, `COREX_LOGIN_UNGUARD`, denied-screen access requests, and CoreX-owned ability review.
  Verification: docs source/link scan passes and `docs-app` build succeeds, generating `/guides/security/`; build emits
  the existing non-fatal sitemap/site warning and "Entry docs → 404 was not found" notice with exit 0.
- Phase 7 verification/guards (T155): focused PHP unit for Access, Operations, Security, CLI recovery, and AdminPage
  passes **120/120 (380 assertions)**; focused Access+Security Jest passes **9/9**; `node --check
  tests/e2e/security-access.spec.js` passes; changed PHP syntax sweep passes; Config production build passes
  (`index.js` 134 KiB); docs-app build passes and generates `/guides/security/`; integration probes for
  Operations/Security/Access now cleanly skip **9 skipped** because WordPress `./wp` is not loaded locally; guard
  scans found no stale disabled request workflow or "still to land" Security copy, only benign docs/test mentions and
  the pre-existing shell select-position directional CSS hits. `git diff --check` is clean apart from the known
  `AdminPage.php` CRLF/LF warning.
- Blog analytics service tests (T156): `BlogAnalyticsServiceTest` now covers consented first-party view recording,
  no raw visitor key/IP/user-agent persistence, no-consent event dropping, and aggregation for views, reads, share
  clicks, unique visitors, and average read seconds. The pure analytics service seam (`ReadingEvent`,
  `ReadingEventStore`, `BlogAnalyticsAggregate`, `BlogAnalyticsService`) is in place. Verification: focused Blog
  analytics unit **3/3 (15 assertions)** and changed PHP syntax clean.
- Blog reading-event persistence (T157–T158): added the managed `blog_reading_events` table, `ReadingEventRepository`,
  provider bindings for `ReadingEventStore`/`BlogAnalyticsService`, managed Data registration, and foundation schema
  version bump. Repository tests verify schema/indexes, managed columns, UTC persistence/hydration, retention dates,
  and absence of raw visitor key/IP/user-agent columns. Verification: focused Blog analytics + repository unit **6/6
  (57 assertions)** and changed PHP syntax clean.
- Blog editorial workflow (T159–T160): `EditorialWorkflowServiceTest` covers native status synchronization for Draft,
  Ready for Review, Needs Changes, Approved, Scheduled, and Published, assignee/due-date/review-note persistence with
  actor timestamps, and fail-closed scheduling without a native schedule timestamp. The implementation adds
  `EditorialItem`, `EditorialNote`, `EditorialTransitionRequest`, `EditorialWorkflowStore`,
  `EditorialWorkflowService`, `WpEditorialWorkflowStore`, and provider bindings over native WordPress posts/meta.
  Verification: full Blog unit folder **15/15 (91 assertions)** and changed PHP syntax clean.
- Blog comments and authors (T161–T162): `CommentModerationTest` runs against native WordPress posts/comments and
  verifies moderation queue classification plus approve, reply, edit, spam, and trash through native comment APIs.
  `AuthorAnalyticsServiceTest` verifies real author projection using native author users/post counts plus first-party
  analytics views, reads, and engagement. The implementation adds moderation request/result/item contracts,
  `CommentModerationService`, `AuthorAnalyticsService`, and provider bindings. Verification: Blog integration **2/2
  (13 assertions)**, full Blog unit folder **16/16 (98 assertions)**, and changed PHP syntax clean.
- Blog social sharing (T163): `SocialSharingServiceTest` verifies configured X/LinkedIn/copy-link controls with
  encoded native post URLs/titles and share-click logging through consent-aware first-party analytics only when enabled.
  The implementation adds social settings, an option-backed settings store, share-control URL generation, sanitized
  target keys, and provider bindings. Verification: full Blog unit folder **18/18 (108 assertions)**, Blog comment
  integration **2/2 (13 assertions)**, and changed PHP syntax clean.
- Blog REST contracts (T164–T165): `BlogProControllerTest` verifies route registration for analytics, share controls,
  share click, editorial transition, comments, comment moderation, and authors; real analytics/share-control responses
  for a native post; and REST-driven editorial transition plus native comment approval. The implementation adds
  `BlogProController` and `BlogProServices` with nonce/capability permission callbacks, sanitized params, and thin
  delegation to the Blog services. Verification: Blog integration folder **5/5 (31 assertions)**, full Blog unit folder
  **18/18 (108 assertions)**, and changed PHP syntax clean.
- Blog Pro client replacement (T166–T167): `blogPro.test.js` verifies stable Blog REST endpoints, analytics card/chart/
  top-post normalization without sample metrics, editorial/share-click payload serialization without raw network
  fields, and reducer state for analytics/editorial/comments/authors/sharing/errors. `BlogProScreen` now mounts the
  shared admin client with localized real posts, analytics, editorial, comment, author, and share-control state;
  `BlogProModel` is functional tab metadata only. Stale future/reference/sample Blog source scan is clean.
  Verification: Blog JS **4/4**, Config build **pass** (`index.js` 139 KiB), full Blog unit **14/14 (102 assertions)**,
  Blog integration **5/5 (31 assertions)**, and changed PHP/JS syntax clean.

## Requirement Evidence

Every item remains incomplete until populated with direct source, test, runtime, and rendered evidence as applicable.

| Requirement range | Task range | Direct evidence | Status |
|---|---|---|---|
| FR-001–FR-011 | T007–T041, T222–T235 | Pending | Incomplete |
| FR-012–FR-019 | T173–T184 | Overview command center projects real state: removed the false "Read-only"/"planned future"/"logging unavailable" placeholders; live `FlowRepository` flow count + core `ActivityService` recent-activity feed (top 5, honest empty), token-only activity styles. Evidence: `OverviewModelTest` **11/11**, `admin-command-center.spec.js` **3/3** (Overview real state + all-route rail/breadcrumb matrix), full unit **1,170/1,170**, live render (5 real events, "1 flow", 0 console errors), README Overview section. | Complete |
| FR-020–FR-026 | T176–T184 | Add-ons catalog projection consolidated into pure `AddonCatalogService` (active/installed/site-kit counts, honest untracked updates, real missing-package install path) and wired into `AddonsScreen` summary; dependency-protected enable/disable, logos/docs/tier/registers, and honest states already real and render-verified. Evidence: `AddonCatalogServiceTest` **2/2**, Addons unit **8/8**, `admin-command-center.spec.js` Add-ons route green, live render (ACTIVE 8/10, SITE KITS 3, no errors), README Add-ons section. Navigation: `AdminPageTest` rail+breadcrumb (incl. T181 setup-label fix) **22/22**. | Complete |
| FR-027–FR-045 | T064–T091 | Repositories/services/REST/builder/blocks/pipeline/docs and PHP/JS/integration/build evidence above; Playwright/lint gate pending | In progress |
| FR-046–FR-058 | T092–T111 | Permission-scoped repository/services/REST/client/bulk/email/export/retention/docs and PHP/JS/integration/build evidence above; Playwright/lint gate pending | In progress |
| FR-059–FR-070 | T112–T132 | Capability/query/schema/detail, previewed write adapters, import/export/migrations/REST, React Data/Data Models clients, docs, E2E source, unit/JS/build evidence above; current DB integration, lint, and Playwright gates pending | In progress |
| FR-071–FR-083 | T133–T155 | Production readiness snapshot, typed PRODUCTION override, maintenance bypass source, login protection policy/stores/route guard/recovery command, Security Center UI/styles/docs/E2E source, and unit/JS/build/docs evidence above; DB-backed integration T135/T139 remains external-gated | In progress |
| FR-084–FR-095 | T020–T027, T143–T155 | Access REST source, editable CoreX matrix, role-plugin coexistence, request-access workflow, Access UI/styles/E2E source, and focused unit/JS/build evidence above; DB-backed integration T143 remains external-gated | In progress |
| FR-096–FR-110 | T156–T172 | First-party consent-aware analytics service, hashed reading-event table/repository, native editorial workflow/status synchronization, native comment moderation, author analytics (modernized `get_users` `capability` query — removed the WP 5.9 `who` deprecation), social sharing/share-click logging, REST contracts/controllers, functional Blog Pro client, token-only responsive styles (fixed undefined `surface-muted` → `surface-alt`), and complete native templates (single with comments/share/newsletter, index/archive grids). Evidence: Blog unit **14/14 (102 assertions)**, Blog integration **5/5 (31)**, Blog Jest **4/4**, `tests/e2e/blog-pro.spec.js` **3/3** (admin panels + real metric background + front-end share/newsletter/comments + responsive), docs-app build **pass** (blog-pro guide emitted), token inventory + PHP token-consumer contract green, live dark render verified. Requires active CoreX Email + CoreX Newsletter for the front-end blocks. | Complete |
| FR-111–FR-125 | T037–T039, T042–T063 | Repositories/services/controllers/client above; full unit 1,027/4,441, integration 52/202, Jest 141/141, Playwright 3/3; live delivery/settings/defaults/sections/RTL/mobile/console probes | Complete |
| FR-126–FR-133 | T185–T191 | Insights data layer complete: `InsightWidgets` planned-state removed and Forms & Flows widget made a real `STATE_LIVE` projection (`InsightWidgetsTest` 3/3); `InsightRunService` run/history/latest/recommendation aggregation (`InsightRunServiceTest` 4/4); `InsightsController` delegates run + exposes `GET /insights/recommendations` (`InsightsControllerTest` updated, Insights unit 36/36). `insightsClient.js` normalization (Jest 8/8, T190); `InsightWidgetFacts` real-facts gatherer + `InsightsController` `GET /insights/widgets` (T191) with `InsightWidgetsPipelineTest` integration 1/1 (24 assertions); `assets/insights.js` v1.1.0 renders the full designed widget set (Performance + Readiness run-cards + Cloudflare/Security-events/SEO/Operations/Forms informational widgets) — render-verified all 7 with real data, 0 console errors, token-only CSS. Full suites: unit 1,177, integration 83/83, Jest 192. | Complete |
| FR-134–FR-143 | T192–T196 | Full Setup Wizard built + verified: `SetupProgress` nine-step state machine (FR-134), real brand Config fields (FR-135), demo levels Minimal/Standard/Full (FR-137), `ConflictResolver` Keep/Replace/Suffix with default-keep (FR-139/143, integration-verified no silent overwrite), `LaunchChecklist` (FR-142/134), `SetupWizardController` REST `/setup/state|plan|apply` cap+nonce+confirm-gated (FR-140), and the nine-step JS wizard UI (progressive enhancement, render-verified functional). Evidence: `SetupWizardCompletionTest` 10/10, `SetupWizardTest` 6/6, `CompanyKitPagesTest` 5/5, `SetupConflictTest` + `SetupWizardControllerTest` integration 5/5, live render (nine steps, real kit/levels, plan+conflicts, 0 console errors). Unit 1,189, integration 88, Jest 192. | Complete |
| FR-144–FR-153 | T197–T206 | Settings + media/retention/diagnostics: pure `SettingsSanitizer` (rejects invalid email/URL/select, preserves empty secrets — `SettingsWorkflowTest` 3/3); new Advanced read-only diagnostics Settings section (live facts, render-verified); `MediaRegenerationJob` bounded resumable job + `WpMediaRegenerationSource`, registered live (`MediaRegenerationJobTest` 2/2); unified `RetentionSweep` over a `PrunableStore` seam (`RetentionSweepTest` 2/2); `AdvancedSettingsService` diagnostics + fail-closed typed-confirmation danger gate (`AdvancedSettingsServiceTest` 3/3); `setup-settings-insights.spec.js` **4/4** (Insights widgets, nine-step wizard, Settings incl. Advanced, 375px overflow); docs updated. Verified other named sections (Operations/Data-Sources/Design-Tokens/Retention) are real dedicated surfaces, not duplicated. Suites: unit 1,199, integration 88, Jest 192, e2e 10. Remaining WP wiring: prunable-store adapters + reset execution behind the gate, jobs CLI. | In progress |
| FR-154–FR-167 | T207–T221 | **T207–T209 done:** `Corex\Ui\ApprovedComponentInventory` declares all 77 approved Blocks & Components items (33 content / 8 Woo / 13 admin / 23 core-UI) with design status + real delivery resolution; `ApprovedComponentInventoryTest` (5 tests, 186 assertions) proves per-category counts, no phantom `corex/*` block, allowed defer reasons, and that every non-deferred item ships. New `corex/carousel` scroll-snap slider primitive (FR-154/162): `perView` 1–6, opt-in autoplay that pauses on hover/focus/tab-blur and never runs under reduced motion, RTL-correct, keyboard-scrollable + swipeable with no JS, enhanced prev/next/dot buttons — covers Testimonial slider / Logo carousel / Gallery carousel. `.corex-toast` wired to a real Settings-save confirmation and `.corex-tooltip` on the settings secret field (token-only admin DLS primitives). Evidence: `CarouselRendererTest` 6/6 (27 assertions), `carousel/index.test.js` + `carousel/view.test.js` **10/10**, full Jest **205/205**, full Pest unit **1,214** + token-consumer contract regenerated green, CSS/JS lint clean, wp/clean-code/test guards clean, live WP render (`corex/carousel` registers + renders per-2/autoplay/3 dots; settings tooltip role=tooltip + describedby). **Remaining Phase 11:** rich-tabs finalize + other content-block gaps (T208 remainder), nav/theme templates (T210–T213), Profile add-on (T214–T216), Docs UI (T217–T218), E2E/docs/guards (T219–T221). | In progress |
| SC-001–SC-009, SC-011–SC-020 | T222–T235 and all checkpoints | Pending | Incomplete |
| SC-010 | T042–T063 | Development and Production lifecycle integration; repository/editor/service/client suites; live WP visual/runtime matrix and Playwright 3/3 above | Complete |

## Final Verification

Pending. Do not use this section to claim completion until every command, guard, screen/state matrix, and requirement row has direct current evidence.
