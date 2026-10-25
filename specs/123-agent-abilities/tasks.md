# Tasks: An agent works on a CoreX site, and installs a release, through MCP

**Input**: `specs/123-agent-abilities/spec.md`, `plan.md`, `research.md`, `data-model.md`, `contracts/`,
`quickstart.md`

**Tests**: these are required for CoreX (constitution, Definition of Done). Each story's tests are listed with
it, and a task is done only when its tests pass and its guard has run clean.

**Format**: `[P]` means the task can run beside its neighbours (different files, nothing pending between them).
`[USn]` names the spec's user story. Paths are relative to the repository root.

**Order, and why it differs from the template.** User Story 1 (visibility) needs nothing from the foundation or
from spec 117. It comes straight after Setup, so it can ship as a patch on its own (plan, "Order of work").
Everything from Phase 3 on waits for spec 117 to be on `main` (T002).

## Phase 1: Setup

- [x] T001 Branch `spec/123-agent-abilities` from `main` (v0.48.0) with spec, checklist, plan, research, data
  model, three contracts and quickstart in `specs/123-agent-abilities/`, and spec 107 FR-024a amended in
  `specs/107-release-from-admin/spec.md`.
- [ ] T002 Gate for Phase 3 onwards: confirm spec 117 (`spec/117-abilities`) is merged to `main`, and merge
  `main` into this branch. Confirm `Corex\Access\AbilityCheck` exists in `plugins/corex-core/src/Access/`, and
  that `AccessAuditLog` records kind `action`. If 117 is not merged, stop and ask the owner. Do not copy its
  helpers (research R12).
- [x] T003 Environment Gate, done on a throwaway install inside the branch's worktree (runtime only, nothing
  committed). `C:\wamp64\www\corex\wp` serves the root checkout's code, so it could not test this branch.
  - `scripts/setup-wordpress.ps1 -DbName corex_w123 -SiteUrl http://127.0.0.1:8923` in the worktree:
    WordPress 7.1.3, every CoreX plugin active.
  - MCP Adapter 0.7.1 installed from wordpress.org and active.
  - `WP_ENVIRONMENT_TYPE` set to `local`.
  - In place of an MCP client's settings, a scripted MCP session over HTTP. It signs in with a test user's
    Application Password (kept outside the repository) and runs initialize, discover, get-ability-info and
    execute. Done 2026-10-25.

**Checkpoint**: the environment is ready for User Story 1. Phase 3 also waits for T002.

---

## Phase 2: User Story 1 - An agent can see what CoreX offers it (Priority: P1) MVP, ships alone

**Goal**: `corex/site-info` and `corex/list-blocks` are listed and runnable through the MCP Adapter's default
server (FR-001 exception, FR-002, FR-003; DECISIONS #500, #508).

**Independent test**: quickstart Phase 1. Discover lists both abilities, and running `corex/site-info` returns
`{name, framework, version, corex_blocks}`. With the adapter deactivated, the admin loads with no notice.

- [x] T004 [US1] In `plugins/corex-core/src/Abilities/AbilitiesProvider.php`, `registerReadOnlyAbility()`:
  - add `'mcp' => ['public' => true]` to `meta`;
  - keep `show_in_rest => true`;
  - set the annotations `readonly => true` and `idempotent => true`;
  - keep `permission_callback` on `current_user_can('edit_posts')`. The plan said `read`; wp-guard found
    that it would show the CoreX version to every subscriber, against spec 111 (DECISIONS #508, corrected).

  Update the class docblock to say the abilities are public to MCP on purpose (research R2).
- [x] T005 [P] [US1] Unit test `tests/Unit/Abilities/AbilitiesProviderTest.php` (Brain Monkey). It captures the
  args passed to `wp_register_ability` for both names and asserts: `meta.mcp.public === true`,
  `meta.show_in_rest === true`, both annotations, the `edit_posts` check (and a refusal for `read` alone), and
  the category `corex`. The "Abilities API absent" path is not unit-tested: Brain Monkey cannot undefine a
  function another test stubbed, and the path is unchanged.
- [x] T006 [P] [US1] Integration test `tests/Integration/Abilities/AbilitiesRegistrationTest.php` on real
  WordPress. It asserts:
  - `wp_get_ability('corex/site-info')` and `wp_get_ability('corex/list-blocks')` exist, and their `get_meta()`
    carries `mcp.public`;
  - when `WP\MCP\Abilities\McpAbilityExposure` is loaded, `McpAbilityExposure::is_public()` is true for both;
  - an author can run `corex/site-info`, and a subscriber and a signed-out request cannot.
- [x] T007 [US1] `CHANGELOG.md` (Unreleased): "CoreX's abilities are visible to MCP agents". Name the
  `edit_posts` check kept and why. Run clean-code-guard, wp-guard and test-guard on the diff. Guards run
  2026-10-25. wp-guard caught the `read` change (reverted). test-guard merged five unit tests into one and
  two integration tests into one. clean-code-guard and docs-guard were clean after two CHANGELOG
  corrections.
- [ ] T008 [US1] After the patch release reaches mu-va.com, repeat quickstart Phase 1 through the owner's `muva`
  MCP connection, and record the result in `PROGRESS.md` (SC-002).

**Checkpoint**: User Story 1 can be released on its own.

---

## Phase 3: Foundational (blocks User Stories 2 to 6; needs T002)

**Purpose**: the shared agent machinery of `plan.md` ("Source Code", corex-core) and `data-model.md`.

- [ ] T009 [P] Add `'agent_changes' => false` to `plugins/corex-core/config/features.php`. Extend
  `tests/Unit/Foundation/FeatureFlagsTest.php` so that `FeatureFlags::all()` includes `agent_changes` as false by default.
- [ ] T010 [P] Add `ActivityEvent::AREA_AGENTS = 'agents'` to the constant list and to the private `AREAS`
  whitelist in `plugins/corex-core/src/Activity/ActivityEvent.php`. Test in
  `tests/Unit/Activity/ActivityEventTest.php` that an `agents` event validates. Leave `AREA_NOTIFICATIONS` as it
  is (plan, "Open").
- [ ] T011 [P] `Corex\Abilities\AgentAnswer` in `plugins/corex-core/src/Abilities/AgentAnswer.php`:
  - `ok(array $data)` and `refused(string $reason, string $message)`, where `reason` matches `^[a-z_]+$`;
  - `toArray()`;
  - `outputSchema()`, the shared schema of `contracts/agent-abilities.md`.

  Unit test `tests/Unit/Abilities/AgentAnswerTest.php`.
- [ ] T012 [P] `Corex\Abilities\AgentAbilityDefinition` in `plugins/corex-core/src/Abilities/AgentAbilityDefinition.php`,
  with the fields of `data-model.md`. Its validation throws when:
  - `name` breaks core's rule;
  - `inputSchema` is not an object with `additionalProperties: false`;
  - `kept` names a field missing from the schema;
  - `kept` names `corex_release_key`, `phrase`, or a name ActivityEvent treats as secret.

  Unit test `tests/Unit/Abilities/AgentAbilityDefinitionTest.php`.
- [ ] T013 [P] `Corex\Abilities\AgentSwitch` in `plugins/corex-core/src/Abilities/AgentSwitch.php`:
  - `enabled()`, read through `FeatureFlags`;
  - `source()`, one of `environment`, `option` or `default`;
  - `turn(bool $on, int $actorId)`, which writes or deletes `corex_features_agent_changes` and refuses when the
    environment sets the flag.

  Unit test `tests/Unit/Abilities/AgentSwitchTest.php`.
- [ ] T014 `Corex\Abilities\AgentCallJournal` in `plugins/corex-core/src/Abilities/AgentCallJournal.php`:
  - `call()`, `uploaded()` and `switchChanged()`, each writing one event of `data-model.md` through
    `ActivityService::record()`;
  - context holds only the definition's `kept` fields plus the Application Password UUID from
    `rest_get_authenticated_app_password()`;
  - sensitivity `personal` when the definition says so;
  - 180 days of retention;
  - a failing write is caught and let go, as `SecurityActivityRecorder::write()` does.

  Unit test `tests/Unit/Abilities/AgentCallJournalTest.php`, with a fake `ActivityService`. One case puts
  `corex_release_key` and `phrase` in the input and asserts that neither is recorded.
- [ ] T015 `Corex\Abilities\AgentAbility extends WP_Ability` in `plugins/corex-core/src/Abilities/AgentAbility.php`
  (research R1, plan D1, D3, D6, D7). The class is loaded only after the `function_exists('wp_register_ability')`
  guard. It overrides:
  - `check_permissions()`: once per request per user, ask `AbilityCheck::allows($asks, 'agent:' . $name)`, then
    the definition's `extraCheck`. A refusal is a `WP_Error`, recorded once through `AgentCallJournal` with
    outcome `denied`.
  - `execute()`: for a `writes` definition, check `AgentSwitch` and answer `agent_changes_off` with the panel's
    name in the message. Then call the handler. A thrown exception becomes `refused('internal_error', ...)` with
    outcome `failure`. Journal the call once, and return `toArray()`.

  Collaborators come from a resolver the registrar sets once (plan, Principle IV note). If `WP_Ability` is
  absent in the unit environment, add a minimal stub in `tests/Support/`. Unit test
  `tests/Unit/Abilities/AgentAbilityTest.php`, which covers:
  - a permission asked three times gives one `AbilityCheck` call and one refusal record;
  - the switch off gives `agent_changes_off` and the handler is not called;
  - a read-only definition ignores the switch;
  - a handler exception gives a `failure` journal entry.
- [ ] T016 `Corex\Abilities\AgentAbilitySource` interface (`definitions(): list<AgentAbilityDefinition>`) in
  `plugins/corex-core/src/Abilities/AgentAbilitySource.php`. In `AbilitiesProvider`, on `wp_abilities_api_init`,
  read the container list `agent.ability.sources` and register each definition with:
  - `ability_class = AgentAbility::class`;
  - `meta.mcp.public = true`;
  - `show_in_rest = false`;
  - its annotations and input schema;
  - the shared output schema.

  A definition that fails validation is skipped with `_doing_it_wrong` (and throws under tests). Bind the empty
  list and the new services in `plugins/corex-core/src/Foundation/CoreServiceProvider.php`. Extend
  `tests/Unit/Abilities/AbilitiesProviderTest.php`.

**Checkpoint**: a module can offer agent abilities by binding an `AgentAbilitySource`.

---

## Phase 4: User Story 2 - The owner decides what an agent may do (Priority: P1)

**Goal**: the CoreX agent role, the off-by-default switch and its panel, refusals counted once, and every call
journaled (FR-005 to FR-008, FR-021, FR-022; DECISIONS #504, #507, #509).

**Independent test**: quickstart Phase 2, steps 1 to 6.

- [ ] T017 [US2] `Corex\Abilities\AgentRole` in `plugins/corex-core/src/Abilities/AgentRole.php`:
  - `ensure()` runs on `init`. It adds the role `corex_agent` ("CoreX agent", `['read' => true]`) when
    `corex_agent_role_version` is older than the class's version, then stores that version.
  - It never changes an existing role's capabilities except to restore `read`.
  - On a network it runs per site.

  Register it in `CoreServiceProvider`. Unit test `tests/Unit/Abilities/AgentRoleTest.php`.
- [ ] T018 [P] [US2] Integration test `tests/Integration/Abilities/AgentRoleTest.php` on real WordPress:
  - the role exists with `read` only;
  - it is listed by the Access screen's role matrix source (`wp_roles()`);
  - allowing it `corex_manage_releases` through `RoleAbilityStore::apply()` makes `current_user_can()` true for a
    user who holds the role.
- [ ] T019 [US2] `Corex\Config\Agents\AgentAccessPanel` in `plugins/corex-config/src/Agents/AgentAccessPanel.php`,
  rendered by `plugins/corex-config/src/Security/OperationsSecurityScreen.php`. It shows:
  - the switch's state, and its source with the environment-override sentence;
  - whether the MCP Adapter is active (check its main class or function, verified against v0.7.1 when this is
    built);
  - the number of users with `corex_agent`, linking to `users.php?role=corex_agent`;
  - the last ten `agent.call` events through `ActivityService::query(['area' => 'agents'], 1, 10)`.

  The panel uses only translatable strings, escaped output, logical CSS properties and admin tokens.
- [ ] T020 [US2] `Corex\Config\Agents\AgentSwitchController` in `plugins/corex-config/src/Agents/AgentSwitchController.php`.
  It is an `admin_post` handler through `AdminGuard` (site administrator only) that calls `AgentSwitch::turn()`,
  then `AgentCallJournal::switchChanged()`, then redirects back with a notice. Bind the panel and the controller
  in `plugins/corex-config/src/ConfigServiceProvider.php`. Unit test
  `tests/Unit/Agents/AgentSwitchControllerTest.php`, which covers the guard refusing, a save on, a save off, and
  the environment-set flag refused.
- [ ] T021 [US2] Integration test `tests/Integration/Abilities/AgentAbilityGateTest.php`. It registers a
  test-only `AgentAbilitySource` with one read and one write definition, then checks, as a `corex_agent` user:
  - Without the CoreX ability, the call is a `WP_Error`, and there is one access-denied entry (kind `action`,
    section `agent:<name>`) and one `denied` event, even when `check_permissions()` is called three times.
  - With the ability and the switch off, the write answers `agent_changes_off` and the read answers ok.
  - With the switch on, both answer ok.
  - No event context holds a value of a non-kept input.
- [ ] T022 [P] [US2] Playwright: extend `tests/e2e/operations-security.spec.js`. Cover:
  - the Agent access panel renders with the switch off;
  - turning it on and off persists;
  - the control works by keyboard and has an accessible name;
  - an axe scan passes;
  - the panel reads correctly in RTL.

**Checkpoint**: an agent account can be set up and limited. No real agent ability exists yet beyond User Story 1.

---

## Phase 5: User Story 3 - The agent reads where the site stands (Priority: P1)

**Goal**: four read abilities (FR-009 to FR-011; `contracts/agent-abilities.md`, "Read").

**Independent test**: quickstart Phase 3.

- [ ] T023 [US3] `ReleaseOverview::read(DateTimeImmutable $now)` in `plugins/corex-config/src/Releases/ReleaseOverview.php`
  returns what `whenOpened()` returns, without calling `ReleaseUpkeep` (plan D5). `whenOpened()` then calls
  `read()` after upkeep. New unit test `tests/Unit/Releases/ReleaseOverviewTest.php`, which asserts that `read()`
  never touches upkeep.
- [ ] T024 [US3] `Corex\Config\Agents\ReadAgentAbilities` (an `AgentAbilitySource`) in
  `plugins/corex-config/src/Agents/ReadAgentAbilities.php`. It holds:
  - `corex/release-installed` (`MANAGE_RELEASES`, through `InstalledRelease`; it carries the version);
  - `corex/release-overview` (`MANAGE_RELEASES` plus `ReleaseRestGateway::mayManage()`, through `read()`);
  - `corex/activity-recent` (`MANAGE_OPERATIONS`; areas agents, operations, security and access only; at most
    50; personal-sensitivity events left out);
  - `corex/submissions-recent` (`MANAGE_SUBMISSIONS`; at most 20; through
    `SubmissionQueryService::query($actorId, SubmissionInboxQuery::from(...))`; journaled with sensitivity
    `personal`).

  Schemas and kept fields are as in the contract. Bind it into `agent.ability.sources` in `ConfigServiceProvider`.
- [ ] T025 [P] [US3] Unit test `tests/Unit/Agents/ReadAgentAbilitiesTest.php`, with fakes. Each handler maps its
  input to the right service call, enforces its bounds, and answers ok.
- [ ] T026 [P] [US3] Integration test `tests/Integration/Agents/ReadAgentAbilitiesTest.php`, as `corex_agent`:
  - the overview's `data` equals `ReleaseOverview::read()`;
  - nothing in `wp-content/corex-releases/` changes;
  - `activity-recent` with `count: 5` returns at most five and no personal events;
  - `submissions-recent` is refused without `corex_manage_submissions`, and is answered and journaled as
    `personal` with it.

**Checkpoint**: an agent can tell what is installed and what happened. With User Story 1, this is a useful
read-only release.

---

## Phase 6: User Story 4 - The owner installs a release with one command (Priority: P1)

*Amended 2026-10-25 (DECISIONS #512, #513): T027 to T032 are replaced by the tasks below. This phase no longer
needs Phase 2 or spec 117: the push is gated by a constant, not by the agent switch.*

**Goal**: every release route accepts an Application Password behind `COREX_ALLOW_RELEASE_PUSH`, and
`npm run release:push` builds, uploads, installs and checks (FR-012 to FR-015d; `contracts/upload-and-gate.md`
section 1, `contracts/release-push.md`).

**Independent test**: quickstart Phase 4.

- [x] T027 [US4] New `plugins/corex-config/src/Releases/ReleaseCaller.php`: `PUSH_ALLOWED_BY`, `ofThisSite()`,
  `pushAllowed()`, `applicationPassword()` (the password's name from WordPress's record), `overHttps()`. Registered
  as a singleton in `ConfigServiceProvider`.
- [x] T028 [US4] `ReleaseRestGateway::answer()`: the nonce, or an Application Password with the constant, HTTPS
  and `mayManage()`; refusals `push_off`, `push_needs_https`, `not_allowed`. Every route alike.
- [x] T029 [US4] `ReleaseRecord` takes the caller's `applicationPassword()` and writes `signed_in_with` on the
  record and the activity entry; `ReleaseHistory.js` shows it beside who installed.
- [x] T030 [P] [US4] Tests: `tests/Integration/Releases/ReleaseApplicationPasswordTest.php` and
  `tests/Unit/Releases/ReleaseCallerTest.php` (contract section 1). `tests/Support/ReleaseInstaller.php` gains
  `pushAllowed` and `$signedByTheScreen`.
- [x] T031 [US4] `createReleaseClient()` gains `key` and an optional nonce. New `scripts/release-push.mjs`
  following `contracts/release-push.md`, driving the screen's client and `runInstallation()`;
  `"release:push"` in `package.json`.
- [x] T032 [P] [US4] `tests/release-push.test.js` (contract "Tested by").
- [x] T032a [US4] `docs/en/05-deployment/releases-from-your-machine.md`, linked from the deployment index and from
  `releases-from-the-admin.md`.

**Checkpoint**: a release goes from the owner's machine to the site, installed, with one command and no browser.

---

## Phase 7: User Story 5 - The agent installs the release (Priority: P1)

**Goal**: ten release abilities over spec 107's services, with the key carried in the body
(FR-016 to FR-019; `contracts/agent-abilities.md`, "Release"; `contracts/upload-and-gate.md` section 2;
DECISIONS #506, #510).

**Independent test**: quickstart Phase 5, steps 1 to 7, through a real MCP client.

- [ ] T033 [US5] `plugins/corex-config/src/Releases/ReleaseMaintenance.php`:
  - `GATE` gains the body source exactly as in `contracts/upload-and-gate.md` section 2: `POST`, JSON, at most
    65,536 bytes, depth 16, at most 64 nodes, key `corex_release_key`;
  - add a `FROM_BODY` constant with the value `body`;
  - `keyCameFrom()` returns it.
- [ ] T034 [P] [US5] Extend `tests/Unit/Releases/ReleaseMaintenanceTest.php` (the gate is included the way
  `wp_is_maintenance_mode()` includes it) with these cases:
  - a matching key in an MCP-shaped body (`params.arguments.parameters.corex_release_key`) passes, with `from`
    as `body`;
  - a wrong key is held;
  - a body over 64 KB is held;
  - a non-JSON content type is held;
  - a `GET` is held;
  - a key nested beyond the walk limit is held;
  - `php://input` is still readable after the gate.
- [ ] T035 [US5] `ReleaseLeftovers` in `plugins/corex-config/src/Releases/ReleaseLeftovers.php` gains:
  - `found()`, returning the list it already computes for the overview;
  - `fingerprint(list)`, the SHA-256 of the sorted `what:where` lines.

  Test this in a new `tests/Unit/Releases/ReleaseLeftoversTest.php`.
- [ ] T036 [US5] `Corex\Config\Agents\ReleaseAgentAbilities` (an `AgentAbilitySource`) in
  `plugins/corex-config/src/Agents/ReleaseAgentAbilities.php`. Every definition asks for `MANAGE_RELEASES` and
  has `mayManage()` as its extra check. The ten abilities, with the hints, inputs and kept fields of the
  contract, are:
  - **`release-inspect`**: `ReleaseDesk::inspect`, plus the `sha256` of the received file.
  - **`release-prepare`**: `ReleaseInstallation::prepare`, plus `confirm_needs`, built from the statement's
    `older`, `client_unconfirmed` and `downgrades`.
  - **`release-confirm`**: `ReleaseInstallation::confirm`, with a `ReleaseConfirmationGiven`.
  - **`release-step`**, **`release-finish`** and **`release-cancel`**: each first compares
    `sha256(corex_release_key)` with the installation's stored key hash (add a read accessor on
    `ReleaseProgress` if none exists) and refuses `not_this_installation` otherwise.
  - **`release-return-prepare`**: the overview's `previous`.
  - **`release-return`**: `ReleaseReturn::goBack`.
  - **`release-leftovers-prepare`** and **`release-leftovers-remove`**: remove refuses `leftovers_changed` when
    the fingerprint differs.

  Map `ReleaseRefused`, `ReleaseUploadOutOfStep` and `InvalidArgumentException` to `AgentAnswer::refused()` with
  the same codes as `ReleaseRestGateway::answer()`, through one shared private helper. Bind the source in
  `ConfigServiceProvider`.
- [ ] T037 [P] [US5] Unit test `tests/Unit/Agents/ReleaseAgentAbilitiesTest.php`, with fake services. It covers:
  - each ability calls its one service method with the mapped input;
  - `confirm_needs` for an older package, an unconfirmed client and downgrades;
  - a key mismatch gives `not_this_installation`;
  - a stale fingerprint gives `leftovers_changed`;
  - every refusal code is passed through unchanged;
  - no handler returns the key except prepare.
- [ ] T038 [US5] Integration test `tests/Integration/Agents/ReleaseAgentInstallTest.php`, reusing the fixture
  packages of `tests/Integration/Releases/`. As `corex_agent` with the switch on, it runs:
  - inspect, prepare, confirm, step until `finish`, finish until `done:true`;
  - then return-prepare and return.

  It asserts the installed version after each stage, that both events appear in the release record by the agent,
  that a second prepare while one is in progress gives `installation_in_progress`, and that the journal holds no
  key or phrase. (The gate itself is covered by T034; here there is no `.maintenance` between requests.)
- [ ] T039 [US5] Manual end-to-end check through a real MCP client on the local install (quickstart Phase 5).
  Confirm that `key_from` is `body` in the release record and that no call waited for maintenance to time out.
  Record the result in `PROGRESS.md`.

**Checkpoint**: SC-001 is reachable. The owner can push and have an agent install, with no admin upload.

---

## Phase 8: User Story 6 - The small jobs around a release (Priority: P3)

**Goal**: clear the page cache, coming-soon on and off, and read the flags (FR-020; `contracts/agent-abilities.md`,
"Settings"; DECISIONS #505, #511).

**Independent test**: quickstart Phase 6.

- [ ] T040 [US6] Page-cache purge seam:
  - `plugins/corex-core/src/Cache/Status/CacheStatusReport.php`: document and accept an optional `purge`
    callable in the `corex_page_cache_provider` array.
  - `plugins/corex-core/src/Cache/CacheManager.php`: `clear(CacheScope::Page, true)` calls that purge when
    present, and otherwise answers "unsupported" as today.

  New unit test `tests/Unit/Cache/CacheManagerTest.php` for the page scope with and without a purge, and
  extend `tests/Integration/Cache/CacheClearingTest.php` with a provider that carries one.
- [ ] T041 [P] [US6] `Corex\Cache\Providers\LiteSpeedPageCache` in
  `plugins/corex-core/src/Cache/Providers/LiteSpeedPageCache.php`:
  - it adds itself to `corex_page_cache_provider` only when `has_action('litespeed_purge_all')` is true;
  - its purge runs `do_action('litespeed_purge_all', 'CoreX: agent request')`.

  Register it in `CoreServiceProvider`. Unit test `tests/Unit/Cache/LiteSpeedPageCacheTest.php` (Brain Monkey),
  covering the listener present, absent, and the purge firing the action with the reason.
- [ ] T042 [US6] `Corex\Config\Agents\SettingsAgentAbilities` (an `AgentAbilitySource`) in
  `plugins/corex-config/src/Agents/SettingsAgentAbilities.php`. It holds:
  - **`page-cache-clear`**: `MANAGE_OPERATIONS`, writes; answers `nothing_to_clear` when there is no provider.
  - **`operations-mode-set`**: `MANAGE_OPERATIONS`, plus `RUN_DANGEROUS_ACTIONS` when the mode is
    `production`, matching the screen after spec 117. It accepts only the modes coming-soon and production, and
    refuses `mode_not_allowed` otherwise. It calls `ModeChangeService::apply(new ModeChangeRequest(...))` and
    passes `confirmed` as `acknowledged`.
  - **`feature-flags`**: `MANAGE_OPERATIONS`, read-only; returns `FeatureFlags::all()` and
    `AgentSwitch::source()`.

  Bind the source in `ConfigServiceProvider`.
- [ ] T043 [P] [US6] Unit test `tests/Unit/Agents/SettingsAgentAbilitiesTest.php`. It covers:
  - `nothing_to_clear`;
  - a purge through a fake provider;
  - each `ModeChangeResult` status passed through;
  - a mode outside the list refused;
  - production refused without `RUN_DANGEROUS_ACTIONS`;
  - flags returned with their source;
  - no definition in the source writes a flag.
- [ ] T044 [P] [US6] Integration test `tests/Integration/Agents/SettingsAgentAbilitiesTest.php`. It covers:
  - coming-soon on returns `confirm` without `confirmed`, then `saved`, and `operations.mode.changed` is
    recorded;
  - the flags ability lists `agent_changes`.

**Checkpoint**: every story is built.

---

## Phase 9: Polish and cross-cutting

- [ ] T045 [P] Operator guide `docs/en/03-operations/agents-over-mcp.md`. It covers:
  - what an agent can do, and what it cannot (no flags, no access changes);
  - installing the MCP Adapter;
  - creating the agent user with the CoreX agent role;
  - allowing its abilities in the role matrix;
  - making and revoking its Application Password;
  - the switch;
  - the push settings file and the command;
  - an installation walked through;
  - keeping approval on for `mcp-adapter-execute-ability`;
  - what the journal keeps and what it never keeps;
  - the multisite rule.

  Link to it from `docs/en/05-deployment/releases-from-the-admin.md`. Add it to the docs site navigation if
  `docs-app` lists pages by hand.
- [ ] T046 [P] `CHANGELOG.md`: the spec's user-facing changes under one release heading. Add the client-impact
  note from `plan.md`: the role appears, the panel appears with the switch off, and the setup steps.
- [ ] T047 Run `php scripts/i18n-lint.php` (the PHP translation lint in `CONTRIBUTING.md`) and the
  pseudo-locale browser check (`tests/e2e/quality/pseudo-locale.spec.js`, run by `npm run test:quality`) over the new strings, then check the panel in Arabic (RTL).
- [ ] T048 Guard Gate on the whole diff:
  - clean-code-guard and wp-guard on PHP;
  - test-guard on every new or changed test;
  - docs-guard on the guide, the CHANGELOG and docblocks;
  - a UI/UX review and WCAG 2.2 AA check of the Agent access panel.

  Fix what they find before the pull request.
- [ ] T049 Run the suites `quickstart.md` lists, plus the full quickstart on the local install. Record commands
  and results in `PROGRESS.md` (replace the spec 123 "In flight" entry), and log in `DECISIONS.md` any choice
  made while building that this plan did not make.

---

## Dependencies

```text
T001 ─┬─ T003 ── Phase 2 (US1) ── can be released alone
      └─ T002 (spec 117 on main) ── Phase 3 (Foundational)
                                      ├── Phase 4 (US2: role, switch, panel)
                                      │     ├── Phase 5 (US3: read)
                                      │     ├── Phase 6 (US4: push)
                                      │     │     └── Phase 7 (US5: release)  needs US4 for a package to install
                                      │     └── Phase 8 (US6: settings)
                                      └── Phase 9 (polish) after the stories being shipped
```

- **Within Phase 3**: T009 to T013 can run in parallel. T014 needs T010. T015 needs T011 to T014. T016 needs
  T012 and T015.
- **US3 and US4** are independent of each other once US2 is done. **US5** needs US4 (and T023 from US3 for the
  return's `previous`). **US6** needs only US2.

## Parallel examples

- **Phase 2 (US1)**: T005 and T006, once T004 is written.
- **Phase 3**: T009, T010, T011, T012 and T013, all different files.
- **Phase 6 (US4)**: T029 and T030 beside T031 and T032. The PHP side and the script do not share files.
- **Phase 7 (US5)**: T034 (gate tests) and T035 (leftovers) while T036 is written. T037 once T036 is done.
- **Phase 8 (US6)**: T041 beside T042. T043 and T044 together.

## Implementation strategy

1. **MVP, the patch**: Phases 1 and 2 only (T001, T003 to T008). Release it, and check it live on mu-va.com.
2. **Read-only agents**: Phases 3 to 5, once spec 117 is merged. An agent can see everything about releases and
   activity, and change nothing.
3. **No more manual upload**: Phases 6 and 7. This is the release that meets SC-001.
4. **Settings and polish**: Phases 8 and 9.

Each step leaves `main` green and is releasable. Stop at any checkpoint to validate against the quickstart.
