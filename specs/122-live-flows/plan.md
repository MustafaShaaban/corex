# Implementation Plan: Flows that stay live and route to people

**Branch**: `spec/122-live-flows` | **Date**: 2026-10-23 | **Spec**: [spec.md](spec.md)

## Summary

Six stories in four groups. The server half of each lives in corex-forms; the screens in corex-config's Forms & Flows
app; one small branch each in the visitor runtime (corex-core), the inbox access policy (corex-config) and Email
Studio's variable list (corex-email).

1. **Live versions** (US-FLW-01). The flow keeps a list of publications. `Flow::publish()` accepts a published flow
   whose draft moved on, records who and when, and stays published. The editor header becomes the design's: eyebrow,
   name, state chip, live line, and "Publish changes" or "Live version is up to date". A Versions tab lists
   publications and copies one into the draft. "Unpublish" asks first. The visitor form carries its version; the
   visitor service runs the submission against it when it was live in the last day, with the live version's
   protection.
2. **Routing to people** (US-FLW-03, US-FLW-04). A resolver turns each routing target into an assignee (user, team,
   role) through a `RoutingDirectory` port, with the fallback chain and its reasons. Teams get a store, a REST
   resource and a Teams view. The inbox stage stores the assignee. A routed-recipient service turns the assignee into
   addresses for the `routed_owner` recipient. A once-only upgrade reassigns old submissions. corex-forms answers
   corex-config's access filters with the actor's teams and whether they have assigned submissions.
3. **Extensions that run** (US-FLW-07). Filters feed the three registries; a new `actions` stage runs a flow's chosen
   actions after storage; email variables are declared to Email Studio and resolved into the mail context; the
   `extension` routing target resolves through a routing-target registry.
4. **Availability** (US-VIS-01, US-VIS-02). Flow-level availability (message, alternative, dates, cap), saved on its
   own; a status resolver computes the effective state from the clock and a response count; the block renders the
   notice; the submit route answers 409 `flow_closed`; capped flows count and store under a named lock.

## Technical Context

**Language/Version**: PHP 8.3; React through `@wordpress/element` for the Forms & Flows app (built by `wp-scripts`);
vanilla JS for the visitor runtime.
**Primary Dependencies**: WordPress 7.0 REST, block and options APIs; MySQL named locks.
**Storage**: the flow record's payload (publications, availability); one option for teams (`corex_flow_teams`); one
option for the upgrade record (`corex_flow_owner_upgrade`); submission post meta as today. No new table.
**Testing**: Pest unit (Brain Monkey) and integration (real WordPress), Jest, Playwright.
**Constraints**:
- no query on a request that does not render a flow or open the inbox;
- Muva's and Perego's code forms run none of the new code: they never reach the flow pipeline, the flow block or the
  `flow_closed` branch;
- every colour of the visitor notice comes from a palette token; logical properties only.

## Constitution Check

- **I. Theme is a skin.** Nothing in the theme changes. ✔
- **II/IV. Injected.** Every new service is bound in `FormsServiceProvider`; WordPress lookups live behind
  `RoutingDirectory` (`WpRoutingDirectory`), `TeamStore` (`WpTeamStore`) and `FlowResponseCounter`
  (`SubmissionRepository`), so the resolver, the status resolver and the services are tested without WordPress. ✔
- **III. Thin controllers.** `FlowController` and `TeamController` map input and call one service method. The 409 for
  a closed flow is a typed exception the controller maps. ✔
- **V. Tokens.** The notice's styles use `var(--wp--preset--*)` tokens; the admin uses the shell's tokens. ✔
- **VI. Assets load conditionally.** The notice's styles are in the flow blocks' own stylesheet, loaded with the
  block. The runtime is unchanged in when it loads. ✔
- **VII. Security.** New routes go through `FlowRestGateway` (capability, nonce, sanitizer). The page reference is
  signed with `wp_hash()` and checked with `hash_equals()`. Teams and availability inputs are sanitized and
  validated. Output escaped. The assigned-only inbox is narrower than today's, never wider: it shows only records
  `SubmissionAccessScope::allows()` passes. ✔
- **VIII. RTL.** Logical properties; the notice and the admin tabs are checked right to left. ✔
- **IX. No optional dependency.** WPML and Polylang are reached only through their public hooks. ✔
- **X. Spec first.** This spec and plan precede the code. ✔

## Research (decisions)

| Topic | Decision | Alternatives |
|---|---|---|
| Version a visitor's submission runs against (#470) | The page's version when it was live in the last 24 hours, protection from the live version | Always the live version: a visitor's answers to a removed field would be refused. Any old version: an old page could bring back a weaker captcha. |
| Publication record (#470) | A list on the flow payload; a flow from before the release shows its live version with an unknown author | A field on the version record: versions are append-only. |
| Page owner source (#471) | A hidden `corex_page` field `{id}.{hmac}` | The referrer header: absent or forged; the page URL: needs a lookup and can be any page. |
| Teams (#472) | One option, keys from names, members by user ID, CRUD over REST under the flow gateway | A post type: more than a handful of rows is not expected; a taxonomy: wrong shape. |
| Assigned-only inbox (#473) | Team members and people with assigned submissions, through two filters corex-config already reads or gains | A new ability: belongs to US-ACC-06 (R4.7). |
| Upgrade (#474) | Runs on `init` behind an option, in pages of 200, records the count | A WP-CLI command: a shared host has none. |
| Routed owner (#475) | Token `routed_owner` in the recipient mapping; recipients worked out at routing and stored with it | Resolving in the email stage: the inbox's "Send again" would not see the same answer. |
| Extension hooks (#476) | Three filters read once on `init` (priority 20) into the registries; actions run in a stage after the timeline | `do_action` with the registry: binds callers to a class. |
| Availability (#477) | On the flow, outside versions; effective state computed; cap under `GET_LOCK` | A cron job flipping states: fails on a site whose cron does not run. |
| Owner text translation (#478) | `wpml_register_single_string` on save, `wpml_translate_single_string` on render | A CoreX translation driver: none exists. |
| Closed submissions (#479) | HTTP 409 `flow_closed` with `details.notice`; the runtime draws it from text, never HTML | 404: reads as a missing page. |

## Data model

- **Flow payload** gains `publications: list<{version:int, by:int, at:ATOM}>` and `availability: {opens_at:?ATOM,
  closes_at:?ATOM, max_responses:?int, closed_message:string, alternative:{type:none|link|email, label:string,
  url:string, email:string}}`. Both absent on old records, read as empty.
- **Version configuration** gains `actions: list<string>` (registered action keys). Like `protection`, it enters the
  checksum only when not empty, so no stored version's checksum moves.
- **Routing configuration** keeps `rules` and `fallback`; adds `fallback_recipient` (address or empty) and
  `recipient_limit` (1..100, default 20). A `field_value` target's config is `{value: field key, owners: list<{value,
  target:{type:user|team|role, value}}>}`. An `extension` target's `value` is the registered key.
- **Routing metadata** on a submission gains `assignee {type, key, label}`, `failures list<{target, reason}>` and
  `recipients {addresses, note}`.
- **Teams option** `corex_flow_teams`: `list<{key, name, members:list<int>}>`.
- **Upgrade option** `corex_flow_owner_upgrade`: `{ran_at, moved}`.

## Contracts

All under `corex/v1`, through `FlowRestGateway` (capability, nonce on writes):

- `POST /flows/{id}/publish` `{expected_version}`: draft → published, or published → published with a newer draft.
  409 `flow_conflict` with the "changed since you opened it" message.
- `POST /flows/{id}/versions/{n}/copy` `{expected_version, expected_checksum}`: the version's configuration becomes a
  new draft version. Answers like `PATCH /flows/{id}`.
- `PATCH /flows/{id}/availability` `{availability}`: saves availability; answers `{flow}`.
- `POST /flows/{id}/routing-preview` `{routing}`: `{lines: list<{label, assignee, recipients:list<{name,email}>,
  note}>}`.
- `GET|POST /teams`, `PATCH|DELETE /teams/{key}`, `GET /teams/people`.
- `GET /flows/extensions` gains `routing_targets: list<{key,label,extension?}>`, `roles`, and `flow_actions` as
  before.
- `GET /flows` rows gain `status {state, reason}` and `routing_owner`; `GET /flows/{id}` gains `publications`
  (with names), `availability`, `status`.
- Public `POST /flows/{id}/submit` accepts `corex_flow_version` and `corex_page`; answers 409
  `{code: flow_closed, message, details: {notice: {title, message, alternative: {type, label, url}}}}` when the flow
  is not open.
- Filters: `corex_flow_actions`, `corex_flow_email_variables`, `corex_flow_routing_targets` (arrays of
  `key => ['label' => string, 'callback' => callable]`); `corex_email_variable_schema` (corex-email);
  `corex_submission_assigned_access` (corex-config, bool).

## Project structure (files this release touches)

```text
plugins/corex-forms/src/Flow/        Flow, FlowService, FlowRepository, FlowController, FlowRest*, FlowConfiguration,
                                     FlowExtensionCatalog, FlowPublication, FlowAvailability, FlowAlternative,
                                     FlowStatus, FlowStatusResolver, FlowResponseCounter, FlowClosedException,
                                     FlowNotice, FlowNoticeText, FlowExtensionHooks
plugins/corex-forms/src/Routing/     Assignee, AssigneeResolver, RoutingDirectory, WpRoutingDirectory,
                                     RoutingTargetRegistry, RoutedRecipients, RoutingPreview, RoutingOwnerLabel,
                                     Teams/(Team, TeamStore, WpTeamStore, TeamService, TeamController),
                                     AssignedAccess, FlowOwnerUpgrade
plugins/corex-forms/src/Submission/  FlowVisitorSubmissionService, FlowSubmissionController, PageReference,
                                     FlowEmailAddressResolver, Stages/(RoutingStage, InboxStage, EmailStage,
                                     TimelineStage, ActionsStage), EmailVariableValues, SubmissionRepository
plugins/corex-forms/src/Block/       FlowBlockRenderer, blocks/flowBlockEditor.js, blocks/_flow.scss
plugins/corex-core/assets/js/corex-runtime.js   the flow_closed branch
plugins/corex-config/src/Forms/      FlowEditorPanel, useFlows, flowEditor, FlowList, formCatalog, index, StageRail,
                                     tabs/(RoutingTab, EmailsTab, SuccessTab, AvailabilityTab, VersionsTab),
                                     TeamsPanel, FormsFlowsScreen
plugins/corex-config/src/Submissions/ WpSubmissionAccessPolicy, SubmissionsInboxScreen
addons/corex-email/src/MailServiceProvider.php   the variable schema filter
```

## Verification

- Pest unit for each new value object and service; Pest integration for the pipeline, the block, the submit route,
  the cap race (two PHP processes), teams REST, the upgrade and the inbox scope.
- Jest for the reducer helpers, the target and recipient pickers and the availability form.
- Playwright: `flow-live-publish.spec.js` (publish while a second page reloads), `flow-closed.spec.js` (closed and
  scheduled notices, 375px, dark, RTL, the refused submit keeping answers), and the touched `forms-flow.spec.js`.
- Guards: clean-code-guard, wp-guard, test-guard, docs-guard.
