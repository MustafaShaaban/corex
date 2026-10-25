# Research: an agent works on a CoreX site, and installs a release, through MCP

**Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md) | **Date**: 2026-10-25

Every fact below was read on 2026-10-25 from CoreX `main` at `2b42435c` (v0.48.0), from WordPress core 7.1.3
(the local install) and 7.0.0 (`wordpress-develop` tag), from the MCP Adapter at tag v0.7.1, and from
LiteSpeed Cache's API page. Paths are relative to the CoreX repository unless they name the adapter or core.

## R1. How an MCP call reaches a CoreX ability

**Facts.**
- The adapter's default server exposes three tools. `mcp-adapter-execute-ability` runs the target ability's
  `execute()` (adapter `includes/Abilities/ExecuteAbilityAbility.php:105-152`).
- The target's permission callback runs about three times per call:
  - the adapter's pre-check (`Handlers/Tools/ToolsHandler.php:148`);
  - the outer ability's own check;
  - the target's `execute()`.
- A `WP_Error` from the target reaches the client as text only. Its code and data are dropped
  (`ExecuteAbilityAbility.php:135-140`, `ToolsHandler.php:234-245`).
- Discover lists abilities whose `meta.mcp.public` is true, or whose `meta.public` is true when
  `meta.mcp.public` is unset (`McpAbilityExposure.php:55-68`). It runs no permission callback.
- Get-ability-info returns the raw `meta`, annotations included.
- Under the default server, annotations reach the agent only through get-ability-info. Every call goes through
  one tool that the adapter itself marks destructive (`ExecuteAbilityAbility.php:82-86`).
- WordPress 7.0 has `wp_before_execute_ability` and `wp_after_execute_ability`, and neither fires on a refusal or
  an error. `wp_ability_invoked` and the result filters exist only from 7.1
  (`class-wp-ability.php:783, 809, 852, 875` in 7.1.3).
- `wp_register_ability()` accepts `ability_class`, a subclass of `WP_Ability`, in both 7.0.0 and 7.1.3.

**Decision.** Every CoreX agent ability is an instance of one CoreX subclass, `Corex\Abilities\AgentAbility`,
passed as `ability_class`. It overrides two methods:
- `check_permissions()`, which decides once per request and records a refusal once. This copes with the
  adapter's repeated checks.
- `execute()`, which journals the outcome once.

Refusals the agent must reason about (switch off, installation in the way, wrong phrase) are returned as the
release gateway's own answer, `{ok:false, reason, message}`, inside a successful result. That way the reason code
survives the adapter. Only a missing CoreX ability is a permission refusal.

**Alternatives.**
- *Core's 7.1 hooks*: they miss 7.0, which CoreX supports.
- *The adapter's observability handler*: it replaces the server's handler rather than adding one, and sees only
  the tool name, not the target ability (`Core/McpServer.php:189-195`).
- *The adapter's `mcp_adapter_tool_call_result` filter*: it ties the journal to the adapter. CoreX abilities are
  also reachable without it.
- *`WP_Error` for every refusal*: the reason code would be lost on the way to the agent.

## R2. Exposure: MCP only

**Decision.**
- New agent abilities set `meta.mcp.public = true`, leave `meta.public` unset, and set `show_in_rest = false`.
  The core REST run route (`wp-abilities/v1/abilities/{name}/run`, `run-controller.php:44-72`) would otherwise
  let plain REST calls drive an installation outside MCP, which DECISIONS #502 ruled out. (Since #512 the
  Releases routes themselves take an Application Password on a site that defines `COREX_ALLOW_RELEASE_PUSH`;
  agent abilities still stay off REST, so an agent always meets MCP's destructive hints.)
- The two abilities from DECISIONS #53 keep `show_in_rest = true` so that any REST caller keeps working, and
  gain `meta.mcp.public = true`. Spec FR-001 names that exception.

**Alternative.** *`meta.public`*: on 7.1 it also turns REST on (`class-wp-ability.php:370-371`).

## R3. The swap window: the installation key travels in the call's body

**Facts.**
- The swap runs inside the `step` request made at step `swap` (`ReleaseSteps::putInPlace`, ReleaseSteps.php:149-171).
  That request raises maintenance before it moves folders, so it is already past the gate.
- The next request, `finish`, arrives while `.maintenance` holds visitors off. It gets through only with the key.
- The gate looks for the key in three places, in this order (`ReleaseMaintenance::GATE`, ReleaseMaintenance.php:141-163):
  1. `$_SERVER['HTTP_X_COREX_RELEASE_KEY']`
  2. `getallheaders()`
  3. `$_GET['corex_release_key']`
- On a match the gate sets `$upgrading = 0.0`, and `COREX_RELEASE_KEY_FROM` records which source matched.
- Without the key, `finish` waits for WordPress's ten minutes (DECISIONS #301). On a network, `hold()` re-arms the
  file after each batch of 20 sites (`ReleaseCompletion.php:73`, `ReleaseFinisher.php:40`). That last point is
  inferred from the code, not measured.
- An MCP client cannot add a header or a query argument per call. It does send a JSON body, which carries the
  ability's input.

**Decision.** The gate gains a fourth source, the request body.
- It reads the body only when all of these hold:
  - the request is a `POST`;
  - `CONTENT_TYPE` contains `json`;
  - `CONTENT_LENGTH` is at most 65,536 bytes.
- It reads `php://input` and decodes the JSON with a depth of 16, then walks at most 64 nodes for a string under
  the key `corex_release_key`.
- `COREX_RELEASE_KEY_FROM` becomes `body`.
- The step, finish and cancel agent abilities take `corex_release_key` as input. Each also refuses a key whose
  SHA-256 differs from the installation's, so only the call that prepared an installation can drive it.
- `prepare` already returns the key (`ReleaseInstallation.php:93-99`).
- PHP lets `php://input` be read again, so WordPress and the adapter still see the body.

Spec 107's FR-024a gains the source. The amendment is in this branch.

**Alternatives.**
- *Swap and finish in one agent call*: one request then does the swap plus the database work. That is the
  request length spec 107 split up for shared hosts, and it still leaves a network's later batches stuck.
- *A loopback request carrying the header*: loopbacks are unreliable on shared hosts.
- *Lowering maintenance at the end of the swap request*: visitors would see the new code before its database
  step ran.
- *Letting the Node script finish the installation with the header*: it would need the nonce-free rule on every
  release route, which DECISIONS #502 refused. (#512 later allowed that rule behind `COREX_ALLOW_RELEASE_PUSH`,
  and the script now finishes installations it starts with the header; an agent still uses the body source.)

**Cost, said plainly.** The key passes through the agent's conversation. It opens the gate and the recovery link
for that one installation only, and a new installation mints a new one. The journal never stores it (FR-022).

## R4. Application Passwords on the upload routes

*Superseded 2026-10-25 by DECISIONS #512: every release route, behind `COREX_ALLOW_RELEASE_PUSH`; see `contracts/upload-and-gate.md` section 1.*

**Facts.**
- A request signed with an Application Password needs no nonce in core: `rest_application_password_check_errors`
  returns true and `rest_cookie_check_errors` returns early (`rest-api.php:1131-1142, 1267`).
- `rest_get_authenticated_app_password()` gives the password's UUID, or null (`rest-api.php:1231-1235`).
- Application Passwords need HTTPS unless the environment type is `local` (`user.php:5155-5157`).
- The `Authorization` header must reach PHP. WordPress's own `.htaccess` passes it (`class-wp-rewrite.php:1558`).
  The existing `muva` MCP connection proves it reaches PHP on mu-va.com.
- `ReleaseRestGateway::answer()` refuses any request without a nonce (`ReleaseRestGateway.php:57-58`).

**Decision.**
- `ReleasesController::route()` gains a flag for the three upload routes.
- `ReleaseRestGateway::answer()` accepts a nonce-less request on a flagged route only when all of these hold:
  - `rest_get_authenticated_app_password()` is not null;
  - the `agent_changes` feature is on;
  - `mayManage()` passes, as it does today through the route's permission callback.
- A completed upload signed with an Application Password writes one `agent.release.uploaded` event. The event
  holds the package name, its size and the password's UUID.

## R5. The upload part size reaches the script

**Facts.** The screen sends parts of `overview.part_bytes`, which is `max(256 KiB, min(4 MiB,
wp_max_upload_size() / 2))` (`ReleaseDesk.php:28-30, 157-160`). The overview route is not one of the three
upload routes.

**Decision.** The `received` answer gains `part_bytes`, worked out the same way. The field is additive, and the
screen ignores it. The script sends parts of that size, so a host's `post_max_size` is respected.

## R6. Where the agent's refusals and calls are recorded

**Facts.**
- `ActivityService::record()` writes one event (`ActivityService.php:29-66`). Area and kind are validated, and a
  key in `context` matching password, secret, token and similar words throws (`ActivityEvent.php:193, 217`).
- No area fits agent calls. Spec 117 (`origin/spec/117-abilities`, not merged) adds:
  - `Corex\Access\AbilityCheck::allows($ability, $section)`, which checks `current_user_can()` and fires
    `corex_access_refused` with kind `action`;
  - an `AccessAuditLog` listener for that action.
- Nothing prunes the activity log today (`ConfigServiceProvider.php:286-291`).

**Decision.**
- `ActivityEvent` gains `AREA_AGENTS = 'agents'`. `AgentCallJournal` writes one `agent.call` event per call. It
  holds:
  - the ability's name, outcome and reason code;
  - the Application Password's UUID;
  - only the input fields an ability names as safe to keep: package name, mode, and how many entries were asked
    for.
- Refusals for a missing CoreX ability go through spec 117's `AbilityCheck`, so they land in the access-denied
  log as kind `action`.
- Retention is 180 days, like `SecurityActivityRecorder`.
- The missing prune is older than this spec. It is listed under "Open" in the plan, not fixed here.

## R7. The agent role and the CoreX abilities each agent ability asks for

**Facts.**
- CoreX registers no role today.
- `AbilityCompatibility` applies the role matrix's effects to each of a user's roles
  (`AbilityCompatibility.php:53-65`).
- `MANAGE_RELEASES` and `MANAGE_OPERATIONS` are critical. `MANAGE_ADMIN` implies every ability
  (`CorexAbilityCatalog.php:98-243`).
- On `main` most screens still ask for `manage_options`. Spec 117 moves Operations & Security to
  `MANAGE_OPERATIONS`, and a mode change to production to `RUN_DANGEROUS_ACTIONS` as well.

**Decision.**
- `AgentRole` makes sure a `corex_agent` role exists ("CoreX agent", `read` only), checked against an option
  that holds the role's version. On a network it runs per site.
- Each agent ability asks for one CoreX ability:

  | Agent abilities | CoreX ability |
  |---|---|
  | Releases (overview, inspect, install, return, leftovers) | `MANAGE_RELEASES`, with `mayManage()`'s network rule |
  | Activity log, mode, page cache, flags | `MANAGE_OPERATIONS` |
  | Submissions | `MANAGE_SUBMISSIONS` |
  | `corex/site-info`, `corex/list-blocks` | `edit_posts`, as before: site-info carries the CoreX version, which spec 111 keeps from subscribers |
  | Installed release | `MANAGE_RELEASES`, for the same reason |

  The owner allows the role two or three CoreX abilities, never `MANAGE_ADMIN`.

## R8. Turning agent changes on

**Facts.** Flags default in `plugins/corex-core/config/features.php`. They are read through env (`FEATURES_<FLAG>`)
and the option `corex_features_<flag>`. Only `AddonActivator` writes a flag today, and no screen toggles one.

**Decision.**
- New flag `agent_changes`, default false.
- The Operations & Security screen gains an "Agent access" panel. The panel holds:
  - the switch;
  - whether the MCP Adapter is active;
  - how many users have the CoreX agent role, with a link to them;
  - the last ten `agent.call` events.
- Only a site administrator can change the switch, through `AdminGuard`. On a network the switch is per site.
- An env value wins over the option, as for every flag, and the panel says so when it does.

**Alternative.** *Env or option only*: a shared host without a command line could not turn it on.

## R9. Clearing the page cache

**Facts.**
- `corex_page_cache_provider` returns `array{name, active}|null` with no way to purge
  (`CacheStatusReport.php:223-240`).
- `CacheManager::clear(CacheScope::Page)` always answers "unsupported" (`CacheManager.php:66-69`).
- LiteSpeed Cache documents `do_action( 'litespeed_purge_all', string|false $reason )`, and says the hook may be
  called whether or not the plugin is active (docs.litespeedtech.com/lscache/lscwp/api/).

**Decision.**
- A provider may add `purge` (a callable) to its array, and `CacheManager` uses it for the page scope.
- CoreX ships `LiteSpeedPageCache`, which registers itself only when `has_action( 'litespeed_purge_all' )` is
  true. That is how CoreX tells that a listener is there. Its purge calls the action with the reason
  `CoreX: agent request`.
- When nothing is registered, the agent is told there is nothing CoreX can clear.

## R10. Going back and removing leftovers, as a pair

**Facts.**
- `ReleaseReturn::goBack($phrase)` is one call that checks the typed version (`ReleaseReturn.php:96-128`).
- `ReleaseLeftovers::remove()` takes no confirmation at all (`ReleaseLeftovers.php:87-109`).

**Decision.**
- `corex/release-return-prepare` changes nothing and returns what going back does and the phrase to type.
  `corex/release-return` calls `goBack()` with the phrase, exactly as the screen does.
- `corex/release-leftovers-prepare` lists the leftovers with a fingerprint: the SHA-256 of their sorted
  `what:where` lines. `corex/release-leftovers-remove` refuses with `leftovers_changed` unless the fingerprint
  still matches.
- The spec's US5 scenario 6 and FR-017 are amended to say this, because the screen asks nothing here.

## R11. The Node script

*Amended 2026-10-25 by DECISIONS #513: the script also builds, installs and checks; see `contracts/release-push.md`.*

**Decision.**
- `scripts/release-push.mjs` follows `scripts/release-content-hash.mjs`: pure functions are exported for Jest,
  and a thin entry point does the I/O. It:
  - reads a JSON settings file (`--settings <path>`, or the `COREX_PUSH_SETTINGS` environment variable) holding
    `site`, `user` and `application_password`;
  - refuses `http://` except for `localhost`, `.localhost`, `.test` and `.local` hosts;
  - refuses a file name that does not match spec 107's package name rule;
  - hashes the file as a stream;
  - asks `received` for its count and `part_bytes`;
  - appends from that offset, restarting from the `received` value a 409 answer carries;
  - retries a network error three times with growing waits;
  - completes, then prints the package name and its SHA-256.
- The password reaches only the `Authorization` header.

**Alternative.** *A WP-CLI command*: rejected by the owner (DECISIONS #503).

## R12. Order against spec 117

Spec 117 is not merged. Phase 1 (the visibility fix) needs nothing from it and can ship first. Phases 2 to 6 use
`AbilityCheck`, the `action` refusal kind and the Operations ability move, so they are built once spec 117 is on
`main`. If it is not merged by then, the plan stops and asks the owner rather than copying its helpers.
