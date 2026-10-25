# Implementation Plan: An agent works on a CoreX site, and installs a release, through MCP

**Branch**: `spec/123-agent-abilities` | **Date**: 2026-10-25 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/123-agent-abilities/spec.md`, clarified with the owner on
2026-10-25 (DECISIONS #500 to #505). Research: [research.md](research.md). Plan decisions: DECISIONS #506 to #511.

## Summary

CoreX's agent abilities become visible to MCP by setting `meta.mcp.public` (R2). Every agent ability is an
instance of one CoreX subclass of `WP_Ability`, `AgentAbility`. It:
- asks for one CoreX ability, once per request;
- returns the release gateway's answer shape so that reason codes survive the adapter;
- refuses changes while the `agent_changes` switch is off;
- journals every call once (R1, R6).

The release abilities wrap spec 107's services one to one (R10). A package arrives through a Node script that
uses the existing resumable upload, signed with an Application Password, which the three upload routes now
accept in place of the nonce (R4, R5, R11).

The installation finishes through MCP because the maintenance gate also reads the installation key from a small
JSON body, which is where an MCP call carries its input (R3).

A "CoreX agent" role, an "Agent access" panel holding the switch, a LiteSpeed page-cache provider, and agent
abilities for coming-soon and the flags complete it (R7, R8, R9).

## Technical Context

**Language/Version**: PHP 8.3; JavaScript (Node 20+ ES module for the script; the admin panel is server-rendered)

**Primary Dependencies**:
- WordPress 7.0+: Abilities API, Application Passwords, REST API.
- The CoreX container and the spec 107 release services.
- Spec 117's `AbilityCheck` (prerequisite, R12).
- The MCP Adapter is optional and never required (DECISIONS #501).

**Storage**:
- Existing stores only: the activity table, the access audit option, spec 107's `wp-content/corex-releases/`, and
  `corex_features_agent_changes`.
- One new option, `corex_agent_role_version`. One new role, `corex_agent`. No schema change.

**Testing**:
- Pest unit and integration (`-d memory_limit=512M`): ability registration and execution on real WordPress, as a
  `corex_agent` user.
- Jest: `tests/release-push.test.js`.
- Playwright: `operations-security.spec.js` gains the Agent access panel.
- A manual end-to-end run against the local CoreX install with the MCP Adapter plugin ([quickstart.md](quickstart.md)).

**Target Platform**: single site and multisite (network installs keep `mayManage()`'s super-administrator rule);
shared hosts with no command line

**Project Type**: WordPress plugin monorepo (corex-core, corex-config) plus one Node script

**Performance Goals**:
- A permission check costs one `current_user_can()` per request per ability, whatever the adapter repeats.
- The gate reads at most 64 KB of body, and only on JSON `POST`s while a release is being installed.

**Constraints**:
- No package byte passes through MCP.
- No secret is stored in a journal.
- Visitors are never held off longer than an installation from the screen holds them.
- Everything degrades to today's behaviour without the adapter.

**Scale/Scope**: 19 agent abilities (2 existing, 17 new). The full list is in
[contracts/agent-abilities.md](contracts/agent-abilities.md). Three routes change their sign-in rule, one gate
template changes, one script is added and one admin panel is added.

## Constitution Check

*Re-checked after Phase 1 design: unchanged.*

- [x] **I. Theme is a skin**: no theme change.
- [x] **II. Plugins boot themselves**: registration happens on the Abilities API's own init hooks from the
  corex-core and corex-config providers. The role is ensured on `init`. All of it works in REST, CLI and cron.
- [x] **III. Thin controllers, fat services**:
  - Each agent ability's handler validates its input shape and calls one spec 107, operations or cache service
    method.
  - `AgentAbility` holds only the permission, switch and journal rules.
  - The panel's save is one `admin_post` controller over `AgentSwitch`.
- [x] **IV. Everything injected**:
  - Handlers and `AgentCallJournal` are built by the container.
  - `AgentAbility` is instantiated by WordPress's registry, so it takes its collaborators from a registrar-held
    resolver set once at registration (the same seam as `AbilitiesProvider` today). The code justifies this in
    one comment.
- [x] **V. Runtime tokens**: the panel uses the admin tokens only.
- [x] **VI. Conditional assets**: the panel adds no script. It is a form.
- [x] **VII. Declarative security**:
  - The upload routes keep their permission callback, and the gateway's sign-in rule widens only as DECISIONS
    #502 states.
  - The panel goes through `AdminGuard`.
  - Abilities check capabilities through `AbilityCheck`.
  - Escaping and sanitising stay where they are today.
- [x] **VIII. RTL-first**: the panel uses logical properties, and its strings are translatable.
- [x] **IX. No optional dependency is hard**:
  - The MCP Adapter and LiteSpeed Cache are detected, never required.
  - The Abilities API is guarded by `function_exists( 'wp_register_ability' )`, as today.
- [x] **X. Spec is source of truth**: this plan traces to spec 123. Its amendments (FR-001, US4, US5.6, FR-017)
  and spec 107's FR-024a are made in the same branch, before any code.
- [x] **Guard Gate + Definition of Done** acknowledged:
  - clean-code-guard and wp-guard for PHP;
  - test-guard for tests;
  - docs-guard for the guide;
  - UI/UX review for the panel;
  - WCAG 2.2 AA;
  - PROGRESS and DECISIONS.

## Project Structure

### Documentation (this feature)

```text
specs/123-agent-abilities/
├── spec.md
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── agent-abilities.md     # every agent ability: name, CoreX ability, switch, hints, input, output
│   ├── release-push.md        # the script: settings file, arguments, HTTP sequence, exit codes
│   └── upload-and-gate.md     # upload routes' sign-in rule, part_bytes, the gate's body source
├── checklists/requirements.md
└── tasks.md                   # /speckit-tasks
```

### Source Code (repository root)

```text
plugins/corex-core/
├── config/features.php                       # agent_changes => false
├── src/Abilities/
│   ├── AbilitiesProvider.php                 # mcp.public on the two existing; `read`; collects definitions
│   ├── AgentAbility.php                      # NEW: WP_Ability subclass (permission once, switch, journal)
│   ├── AgentAbilityDefinition.php            # NEW: value object (name, labels, ability, writes, hints, schemas, handler)
│   ├── AgentAbilitySource.php                # NEW: interface a module implements to offer definitions
│   ├── AgentAnswer.php                       # NEW: {ok, data} / {ok:false, reason, message}
│   ├── AgentCallJournal.php                  # NEW: one agent.call event; refusals through AbilityCheck
│   ├── AgentSwitch.php                       # NEW: reads features.agent_changes, says where the value came from
│   └── AgentRole.php                         # NEW: ensures corex_agent ("CoreX agent", read)
├── src/Activity/ActivityEvent.php            # AREA_AGENTS
└── src/Cache/
    ├── CacheManager.php                      # page scope uses a provider's purge
    ├── Status/CacheStatusReport.php          # provider array may carry purge
    └── Providers/LiteSpeedPageCache.php      # NEW: registers when has_action('litespeed_purge_all')
plugins/corex-config/src/
├── Agents/
│   ├── ReleaseAgentAbilities.php             # NEW: AgentAbilitySource for the release group
│   ├── ReadAgentAbilities.php                # NEW: activity, submissions
│   ├── SettingsAgentAbilities.php            # NEW: page cache, mode, flags
│   ├── AgentAccessPanel.php                  # NEW: Operations & Security panel
│   └── AgentSwitchController.php             # NEW: admin_post save through AdminGuard
├── Security/OperationsSecurityScreen.php     # renders the panel
└── Releases/
    ├── ReleaseRestGateway.php                # Application Password on flagged routes
    ├── ReleasesController.php                # flags the three upload routes; received adds part_bytes
    ├── ReleaseMaintenance.php                # the gate's body source; FROM_BODY
    └── ReleaseOverview.php                   # read() without upkeep, for the read-only ability
scripts/release-push.mjs                      # NEW
tests/release-push.test.js                    # NEW (Jest)
tests/Unit/Abilities/                         # AgentAbility, definitions, answer, journal, role, switch
tests/Unit/Releases/ReleaseMaintenanceTest.php    # body source cases
tests/Unit/Cache/                             # provider purge
tests/Integration/Abilities/                  # NEW: registration + execution as corex_agent on real WP
tests/e2e/operations-security.spec.js         # Agent access panel
docs/en/03-operations/agents-over-mcp.md      # NEW: the operator's guide
docs/en/05-deployment/releases-from-the-admin.md  # link to the agent route
specs/107-release-from-admin/spec.md          # FR-024a amended (body source)
```

**Structure Decision**:
- Shared agent machinery goes in corex-core, beside the existing `Abilities/` provider, so every module offers
  abilities the same way.
- The release, read and settings groups live in corex-config, where the services they wrap live.
- Each module's service provider binds its `AgentAbilitySource` implementations into one list in the container,
  and the core registrar reads that list on `wp_abilities_api_init`. corex-core does not import corex-config.

## Design decisions (beyond research)

- **D1, answer shape.** The output schema of every agent ability is the same object: `ok` (bool), `data` (when
  ok), and `reason` plus `message` (when not). This mirrors `ReleaseRestGateway::answer()`. A missing CoreX
  ability is the only `WP_Error` (DECISIONS #507).
- **D2, input shape.** Every input schema is an object with `additionalProperties: false`. Fields are typed and
  bounded (package names match spec 107's rule, phrases at most 100 characters, counts 1 to 50), so core's
  `validate_input()` refuses anything else before CoreX code runs.
- **D3, the switch.** `AgentAbility::execute()` checks `AgentSwitch` before the handler for every definition
  marked `writes`. The refusal reason is `agent_changes_off`, and its message names the panel.
- **D4, release key binding.** Step, finish and cancel compare `sha256(corex_release_key)` with the
  installation's stored hash and refuse `not_this_installation` otherwise. Confirm needs no key, as on the screen.
- **D5, read-only overview.** `ReleaseOverview::read()` returns the overview without running `ReleaseUpkeep`.
  The `readonly` hint is then true. The screen keeps `whenOpened()`.
- **D6, what the journal keeps.** Each definition names its `kept` input fields. Everything else, the key and
  any phrase included, is dropped before `ActivityService::record()`.
- **D7, network installs.** Release agent abilities ask `ReleaseRestGateway::mayManage()` itself, so a network
  stays super-administrator-on-the-main-site only.

## Order of work

Each phase ships on its own and leaves `main` green.

1. **Visibility (US1).**
   - `meta.mcp.public` on `corex/site-info` and `corex/list-blocks`.
   - Their permission moves to `read`.
   - An integration test that both register with the adapter's rule satisfied.
   - It needs nothing from spec 117 and can be a patch release on its own. Verified live on mu-va.com.
2. **Foundation (US2).** These need spec 117 merged first:
   - `AgentAbility`, the definition, the answer, the journal, the switch, the role;
   - `AREA_AGENTS`;
   - the Agent access panel.
3. **Read (US3).** Release overview, installed release, recent activity, recent submissions.
4. **Push (US4).** The gateway's sign-in rule, `part_bytes`, `scripts/release-push.mjs` and its tests, and the
   upload event.
5. **Release (US5).**
   - The gate's body source, with spec 107's FR-024a amended.
   - Inspect, prepare, confirm, step, finish, cancel.
   - Return prepare and return.
   - Leftovers prepare and remove.
   - An end-to-end install and return on the local install through the adapter.
6. **Settings (US6).** The page-cache purge seam, `LiteSpeedPageCache`, the mode ability, the flags ability.
7. **Docs and records.** `agents-over-mcp.md`, a link from the releases guide, CHANGELOG, PROGRESS, and the
   DECISIONS made while building.

## Client impact

- **Muva and Perego, after phase 1:** an agent connected to the site sees `corex/site-info` and
  `corex/list-blocks`. Nothing else changes.
- **After phase 2:** a `corex_agent` role appears in Users and in the role matrix, and Operations & Security
  shows the Agent access panel with the switch off.
- **For the owner to stop uploading by hand:**
  1. install the MCP Adapter plugin;
  2. create the agent user with the role;
  3. allow the role `corex_manage_releases` (and `corex_manage_operations` for the settings abilities);
  4. make its Application Password;
  5. turn the switch on;
  6. write the settings file under `C:\Users\pc\.muva\`.

  [quickstart.md](quickstart.md) is the checklist. The guide repeats it for operators.

## Open, and not this spec's

- Nothing prunes the activity log (`ActivityService::pruneExpired()` has no caller). Agent calls add to it. A
  retention fix belongs to its own change.
- `ActivityEvent::AREA_NOTIFICATIONS` is missing from the area whitelist (`ActivityEvent.php:39` against
  `:67-84`), so recording with it throws. It is unused today and noted here only because this plan adds an area
  beside it.
- Under the default server the agent's client sees one destructive tool for every call. Per-ability hints show
  only in get-ability-info. A per-ability tool list (a custom server or the config filter's `tools`) would show
  them directly. It is left out by DECISIONS #8, and the guide tells the owner to keep approval on for
  `mcp-adapter-execute-ability`.

## Complexity Tracking

None. The one judgement call, `AgentAbility` taking its collaborators from a registrar-held resolver, is
justified under Principle IV above.
