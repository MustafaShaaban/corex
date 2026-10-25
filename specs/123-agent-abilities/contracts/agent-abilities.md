# Contract: CoreX agent abilities

Every ability below:
- is registered in category `corex` with `ability_class` = `Corex\Abilities\AgentAbility`;
- sets `meta.mcp.public = true`;
- sets `show_in_rest = false`, except the two marked "existing";
- is reached through the MCP Adapter's `mcp-adapter-execute-ability` tool.

The research and plan sections each rule comes from are in [research.md](../research.md) and
[plan.md](../plan.md).

## Common rules

**Permission.** The ability's CoreX ability (or `read`) is checked once per request through spec 117's
`AbilityCheck` (R1, R7). A missing ability is a `WP_Error`, recorded once in the access-denied log as kind
`action`. Release abilities also require `ReleaseRestGateway::mayManage()` (D7).

**Switch.** An ability marked *writes* refuses `agent_changes_off` while `features.agent_changes` is off (D3).

**Input.** An object with `additionalProperties: false` (D2). Inputs not listed below are refused by core
validation.

**Output.** Every ability answers with one of these two objects (D1):

```json
{ "ok": true,  "data": { } }
{ "ok": false, "reason": "<code>", "message": "<translated sentence>" }
```

**Reason codes.** These are spec 107's existing codes (`ReleaseRefused`, `ReleaseInstallation`,
`ReleaseCompletion`, `ReleaseReturn`, `ReleaseStore`, `ReleaseUpload`), plus the new ones:

| Code | Meaning |
|---|---|
| `agent_changes_off` | the switch is off |
| `not_this_installation` | the key does not match the installation |
| `leftovers_changed` | the leftovers are not the ones prepare listed |
| `nothing_to_clear` | no page-cache provider can purge |
| `mode_not_allowed` | the mode is outside the list this ability accepts |

**Journal.** Each call writes one `agent.call` activity event (area `agents`) that holds the "kept" fields only
(D6, R6).

## Abilities

Hints are written as `readonly / destructive / idempotent`. A dash means the hint is not set.

### Existing (DECISIONS #53)

| Name | Asks for | Writes | Hints | Input | `data` |
|---|---|---|---|---|---|
| `corex/site-info` | `read` | no | true / - / true | none | `{name, framework, version, corex_blocks}` |
| `corex/list-blocks` | `read` | no | true / - / true | none | list of CoreX block names |

Both keep `show_in_rest = true`, so existing REST callers keep working (R2).

### Read

| Name | Asks for | Writes | Hints | Input (kept) | `data` |
|---|---|---|---|---|---|
| `corex/release-installed` | `read` | no | true / - / true | none | `{known, version, client}` (`InstalledRelease`) |
| `corex/release-overview` | `MANAGE_RELEASES` | no | true / - / true | none | `ReleaseOverview::read()`: installed, blockers, packages, part_bytes, installation, leftovers, previous, record |
| `corex/activity-recent` | `MANAGE_OPERATIONS` | no | true / - / true | `area` (agents, operations, security, access), `outcome`, `since` (ISO date), `count` 1-50 (all kept) | list of `{occurred_at, area, kind, outcome, actor_label, target_label, summary}`; personal-sensitivity events left out |
| `corex/submissions-recent` | `MANAGE_SUBMISSIONS` | no | true / - / true | `status`, `flow`, `count` 1-20 (kept) | `SubmissionQueryService::query()` items, as the inbox shows the same account; the call is journaled with sensitivity `personal` |

### Release

| Name | Asks for | Writes | Hints | Input (kept in bold) | Calls | `data` |
|---|---|---|---|---|---|---|
| `corex/release-inspect` | `MANAGE_RELEASES` | yes | false / false / true | **`package`** | `ReleaseDesk::inspect` | the statement, plus `sha256` of the received file |
| `corex/release-prepare` | `MANAGE_RELEASES` | yes | false / false / false | **`package`** | `ReleaseInstallation::prepare` | statement, `drops`, `corex_release_key`, `recovery_link`, `copies_days`, and `confirm_needs`: `{phrase, client?, older?, downgrade?}` |
| `corex/release-confirm` | `MANAGE_RELEASES` | yes | false / true / false | `phrase`, `client`, `older`, `downgrade` | `ReleaseInstallation::confirm` | progress (`forTheScreen()`) |
| `corex/release-step` | `MANAGE_RELEASES` | yes | false / true / false | `corex_release_key` | `ReleaseInstallation::step` | progress |
| `corex/release-finish` | `MANAGE_RELEASES` | yes | false / true / false | `corex_release_key` | `ReleaseCompletion::finish` | `{done:false, ...progress}` or `{done:true, version, changes}` |
| `corex/release-cancel` | `MANAGE_RELEASES` | yes | false / false / true | `corex_release_key` | `ReleaseInstallation::cancel` | `{cancelled:true}` |
| `corex/release-return-prepare` | `MANAGE_RELEASES` | no | true / - / true | none | overview `previous` | `{version, replaced_by, phrase, copies}`, or reason `nothing_kept` |
| `corex/release-return` | `MANAGE_RELEASES` | yes | false / true / false | `phrase` | `ReleaseReturn::goBack` | `{version, left, database_changes_reversed}` |
| `corex/release-leftovers-prepare` | `MANAGE_RELEASES` | no | true / - / true | none | `ReleaseLeftovers` list | `{leftovers: list<{what, where}>, fingerprint}` |
| `corex/release-leftovers-remove` | `MANAGE_RELEASES` | yes | false / true / false | `fingerprint` | `ReleaseLeftovers::remove` | `{removed}` |

Further rules for the release group:
- `corex_release_key` is 64 lowercase hex characters. It is never kept, logged or echoed back after prepare.
- The step, finish and cancel calls carry it so that the maintenance gate lets them through (R3) and the ability
  can bind them to the installation (D4).
- The agent repeats `release-step` until progress reaches `finish`, then repeats `release-finish` until
  `done:true`.

### Settings

| Name | Asks for | Writes | Hints | Input (kept) | Calls | `data` |
|---|---|---|---|---|---|---|
| `corex/page-cache-clear` | `MANAGE_OPERATIONS` | yes | false / false / true | none | `CacheManager::clear(CacheScope::Page, true)` | `{cleared, provider}`, or reason `nothing_to_clear` |
| `corex/operations-mode-set` | `MANAGE_OPERATIONS` (and `RUN_DANGEROUS_ACTIONS` for production, as spec 117 requires on the screen) | yes | false / true / false | `mode` (coming-soon or production), `confirmed` (bool), `phrase` (production only) | `ModeChangeService::apply(ModeChangeRequest)` | the `ModeChangeResult` status (saved, unchanged, confirm, production_confirm, blocked, locked) and modes |
| `corex/feature-flags` | `MANAGE_OPERATIONS` | no | true / - / true | none | `FeatureFlags::all()` | `{flag: bool}` and, for `agent_changes`, where its value came from |

How `operations-mode-set` behaves:
- A first call without `confirmed` returns status `confirm` (or `production_confirm`), with what is needed. That
  is the mode's own two-step, as on the screen.
- No agent ability writes a flag (FR-006, DECISIONS #505).
