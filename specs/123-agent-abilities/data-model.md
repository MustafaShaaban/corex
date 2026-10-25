# Data model: an agent works on a CoreX site, and installs a release, through MCP

No table or column is added. Everything below is a value object in code, a WordPress role, an option, or a row
in an existing store.

## AgentAbilityDefinition (value object, corex-core)

| Field | Type | Rule |
|---|---|---|
| `name` | string | `corex/<slug>`, matching core's rule `^[a-z0-9-]+/[a-z0-9-]+$` |
| `label`, `description` | string | translatable; the description says what the call changes |
| `asks` | string | a `CorexAbility` constant, or `read` |
| `writes` | bool | true puts the ability behind `agent_changes` |
| `hints` | `{readonly, destructive, idempotent}` | each bool or null; mapped by the adapter to MCP hints |
| `inputSchema` | array | JSON Schema object, `additionalProperties: false` |
| `kept` | list of string | input fields the journal may keep (D6); never `corex_release_key` or `phrase` |
| `handler` | callable(array input, int actorId): AgentAnswer | container-built; calls one service method |
| `extraCheck` | callable(): bool, or null | release group: `ReleaseRestGateway::mayManage()` (D7) |

**Validation.** At registration, a definition whose `kept` names a field missing from its schema, or names a
secret field, is a programming error. The registrar throws in tests and skips the ability in production with
`_doing_it_wrong`.

## AgentAnswer (value object, corex-core)

- `ok(array $data)` serialises to `{ok:true, data}`.
- `refused(string $reason, string $message)` serialises to `{ok:false, reason, message}`. `reason` matches
  `^[a-z_]+$`.

## AgentAbility (extends `WP_Ability`, corex-core)

Each instance holds one definition. Per request it keeps a memo keyed on ability name and user ID, holding the
permission result, and a "refusal recorded" flag. The memo is not persisted. A call moves through these states:

```text
called
  -> permission: ask (once per request) -> refused -> journal(denied) and refusal (once) -> WP_Error
  -> switch (writes only): off -> journal(refused, agent_changes_off) -> AgentAnswer::refused
  -> handler -> AgentAnswer -> journal(success | refused <reason> | failure)
```

## Agent call event (row in the existing activity table)

| Column | Value |
|---|---|
| `area` | `agents` (new `ActivityEvent::AREA_AGENTS`) |
| `kind` | `agent.call` (calls) or `agent.release.uploaded` (completed upload signed with an Application Password) |
| `actor_*` | the WordPress user; kind `user` |
| `target_type` / `target_id` | `ability` / the ability's name (for uploads: `package` / its name) |
| `outcome` | `success`, `denied`, `failure`, or `warning` for a refused answer |
| `summary` | `{key: 'agent.call', args: {ability, reason?}}` |
| `context` | `app_password` (UUID) and the definition's `kept` fields; for uploads, size |
| `sensitivity` | `security`; `personal` for `corex/submissions-recent` |
| `retention_until` | now + 180 days |

## Access refusal (row in the existing `corex_access_audit_log` option)

Written through spec 117's `AbilityCheck` with kind `action` and section `agent:<ability name>`. It is counted as
spec 112 counts every refusal: one entry per person, kind and section per hour.

## CoreX agent role (WordPress role)

| Key | Value |
|---|---|
| slug | `corex_agent` |
| name | "CoreX agent" (translatable at display) |
| capabilities | `read => true` only |
| CoreX abilities | none of its own; allowed in spec 117's role matrix (`RoleAbilityStore`), for example `corex_manage_releases`, `corex_manage_operations`, `corex_manage_submissions` |
| ensured by | `AgentRole::ensure()` on `init`, when `corex_agent_role_version` is older than the code's version; per site on a network |

## Agent changes switch (existing flag store)

| Key | Value |
|---|---|
| flag | `features.agent_changes`, default `false` in `plugins/corex-core/config/features.php` |
| written by | the Agent access panel: `update_option( 'corex_features_agent_changes', '1' )` or `delete_option(...)`, through `AgentSwitchController` |
| overridden by | `FEATURES_AGENT_CHANGES` in the environment, which the panel reports as "set by the environment" |
| recorded | every change is one `agent.switch.changed` event (area `agents`, outcome `success`) |

## Push settings file (owner's machine, outside any repository)

`{site, user, application_password}`, as in [contracts/release-push.md](contracts/release-push.md).

## Relationships

```text
Agent account (WP user) --has role--> corex_agent --allowed in role matrix--> CoreX abilities
AgentAbility --asks--> CoreX ability --refusal--> access audit log
AgentAbility --every call--> agent.call event
release-prepare --mints--> installation key --carried by--> release-step / -finish / -cancel --opens--> maintenance gate
release-push.mjs --Application Password--> upload routes --complete--> package in incoming/ --inspect/prepare--> installation
```
