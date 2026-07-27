# Spec 075 — Implementation plan

Baseline `main` @ `d243b7f` (Spec 074). Branch `spec/075-blog-pro-functional-completion`.

## Architecture decisions

**A. Wire the existing client module; do not write a second one.**
`blogProState.js` already holds the reducer, the payload builders, and `blogEndpoint`, and they are
already tested. The defect is that nothing calls them. So the work is a client that *dispatches*, not
a new state layer. Any export that ends the spec without a caller is deleted along with its tests
(DoD item 6) — carrying tested-but-unreachable code is what produced this spec.

**B. The selected post is the screen's one piece of URL state.**
Everything on the screen is *about a post*. Making the selection explicit and linkable (`?post=<id>`)
fixes D1 at the root: once a panel has to name its subject, it can no longer imply a site-wide total.
The server keeps localizing the first page of posts for the initial paint; every change after that
goes through the REST routes, so there is one refetch path rather than a page reload.

**C. Labels are derived on the server, once.**
The six editorial states and the comment states are the service's vocabulary. Translating them in
JavaScript would put the mapping in a second place and let the two drift — the exact failure DECISIONS
#157 was written about. The server sends `{ key, label }` and the client renders the label. This also
keeps `_n()`/context available in PHP where the strings already live.

**D. Permission flags travel with the payload.**
Following DECISIONS #159: each item carries what this actor may do with it, derived from the same
capability the route's `permission_callback` enforces. The client hides; the server still refuses. No
client-side capability guessing.

**E. "No data" is a value, not a zero.**
`BlogAnalyticsService` returns counts. A count of zero cannot distinguish "nothing recorded" from
"recorded nothing", so the boundary must say which — an explicit `has_data` (or a null metric), decided
by whether any reading event exists for the post and period. FR-4 fails without it.

## Work order

Sequential; each step green before the next.

1. **Evidence + spec** — `evidence/before/`, `spec.md`, checklist. *(done)*
2. **075-A · Server payload** — label maps, permission flags, the `has_data` distinction, and the post
   list the selector needs. Pest unit first.
3. **075-B · Client foundation** — `BlogProApp` gains dispatch, a fetch layer over the existing routes,
   the notice region, and post selection with URL sync. Jest first.
4. **075-C · Editorial workflow** — allowed transitions, the transition form, `transitioned`.
5. **075-D · Moderation queue** — comment rendering, the four actions, `commentModerated`.
6. **075-E · Analytics + sharing** — the honest metric panel, top posts/chart or their removal,
   share controls, `shareRecorded`.
7. **075-F · Dead-export sweep** — grep `blogProState.js` exports for callers; delete what has none.
8. **Gate + browser acceptance + `evidence/after/`.**
9. **Docs, DECISIONS, CHANGELOG, PROGRESS.**

## Files

| Step | Files |
|---|---|
| A | `plugins/corex-config/src/Blog/{BlogProScreen,BlogProController,EditorialWorkflowService,CommentModerationService}.php`; new `Blog/BlogProLabels.php`; `tests/Unit/Blog/*` |
| B | `plugins/corex-config/src/Blog/{BlogProApp,blogProState}.js`; new `Blog/blogProClient.js`; `plugins/corex-config/assets/blog-pro.scss` + twin `.css`; `Blog/__tests__/blogPro.test.js` |
| C | `Blog/EditorialPanel.js`; `EditorialWorkflowService.php` (allowed-transition exposure only) |
| D | `Blog/ModerationPanel.js` |
| E | `Blog/{AnalyticsPanel,SharingPanel}.js`; `BlogAnalyticsService.php` (`has_data` only) |
| F | `Blog/blogProState.js`; `Blog/__tests__/blogPro.test.js` |

## Risks

| Risk | Mitigation |
|---|---|
| Scope creep into new analytics | §10 excludes it explicitly; present only what `BlogAnalyticsService` already returns |
| The comment queue is unbounded on a busy post | Paginate at the existing service's limit; never `posts_per_page => -1` |
| Deleting a "dead" export that a future caller wants | DoD item 6 is deliberate: unreachable code with passing tests is the defect. Deletion is recorded in DECISIONS so the intent survives |
| Changing the localized payload shape breaks the current Jest suite | The suite covers `blogProState.js`, which keeps its contract; the screen's payload is additive (labels, flags, `has_data`) |
| `has_data` requires a query per metric | Derive it once from the same aggregate the panel already fetches — no extra round trip |
| Editorial transitions touch published content | Only the service's own permitted transitions are offered, and the route's existing capability check is unchanged |

## Out of plan

No new REST route, service, or managed table. If a requirement appears to need one, stop and record it
rather than adding it — that is a spec change, not an implementation detail.
