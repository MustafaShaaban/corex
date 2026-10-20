# Research: Access and logs

**Spec**: [spec.md](spec.md) · **Date**: 2026-10-20 · Code read at `origin/main` `e8cc997c`.

Each decision names what was chosen, why, and what else was considered. Paths are shortened: `CFG` =
`plugins/corex-config/src`, `CORE` = `plugins/corex-core/src`.

## R1. One rule decides whether an ability can be requested

- **Decision**: a small `RequestableAbilities` policy in `CORE/Access/` answers `requestable(ability)` as "not locked
  and not critical", applied to every ability the request expands to (`CorexAbilityCatalog::expanded()`, which follows
  `implies`). `AccessService::assertTarget()` calls it, so the access-denied form and `POST corex/v1/access/requests`
  refuse the same way. The REST answer is 400 with a new `InvalidAccessInput` code `ability_not_requestable`, inside the
  existing 400 contract (`AccessController`, spec 079 refusal shape).
- **Rationale**: DECISIONS #343 puts request rules where the request is made, not in a caller. The catalogue already
  carries `locked` and the risk level; no catalogue change is needed.
- **Alternatives**: a list of requestable abilities kept in corex-config (drifts from the catalogue); checking in each
  caller (two places to keep equal).

## R2. The access-denied page on a page whose ability cannot be requested

- **Decision**: `AdminPage::requestState()` returns a new state when the section's ability is not requestable, and the
  denied body shows "An administrator grants access to this area through the role matrix." with no form
  (Clarifications, FR-008a). A new problem code in `AccessRequestSurfaceState` covers a direct POST of the form.
- **Rationale**: nobody types a reason that will be refused; the owner chose this on 2026-10-20.
- **Alternatives**: keep the form and refuse after sending (rejected by the owner).

## R3. Approval: preview, typed name, note and expiry

- **Decision**: add `POST corex/v1/access/requests/{id}/preview`, answering the expanded abilities (label, risk), the
  requester's reason, any `AccessPolicy::preview()` blockers, whether a typed name is required, and a target hash over
  `{request id, abilities, expiry days, note}`. `POST .../decision` then requires, on approval, a non-empty `note`,
  `expiry_days` in {7, 30, 90}, and a `Confirmation` of operation kind `access.request.approve`. For a dangerous ability
  the server passes the ability's name as the required phrase to `Confirmation::verify()`. Both routes keep
  `manage_options` (DECISIONS #346). The dialog reuses `CorexDialog` as `AccessChangeDialog.js` does.
- **Rationale**: FR-002, FR-003; the role matrix's confirmation has no phrase (it is built by the client), so a typed
  phrase must be defined here and checked on the server. Calling `AccessPolicy::preview()` for approvals means any future
  policy rule reaches approval without a second change.
- **Alternatives**: a phrase built by the client (checks nothing); reusing `ProductionLaunchService`'s fixed phrase
  (the ability's name is what the approver should read).

## R4. Where a grant's note, expiry and revocation are kept

- **Decision**: new nullable columns on the `access_requests` table: `grant_expires_at`, `revoked_at`, `revoked_by`,
  `ended_reason` (`revoked` | `expired`) and `cancel_reason`; the foundation schema version goes from 3 to 4, which makes
  the migration runner add them (DECISIONS #299). An approved request is the grant. Rows approved before this release
  have no `grant_expires_at`, which is exactly FR-007's "no expiry".
- **Rationale**: no backfill and no data-migration step (the runner does DDL only); the store already holds approver,
  note and date.
- **Alternatives**: a separate `access_grants` table, one row per person and ability (per-ability revoke of an area
  grant, but needs a backfill the runner cannot run). Recorded as the path if per-ability revoke is ever wanted.

## R5. Revoke and expiry

- **Decision**: `AccessRequestStore` gains `approvedGrants()`, `endGrant()`, `expiredGrants(now)` and
  `cancelNotRequestable()`; `AccessUserDirectory` gains `revokeUserAbility()` and `holdsOwnAbility()` (reads the
  person's own caps, not the filtered set). Removing an ability first checks that no other live grant still gives it.
  A daily `AccessGrantScheduler`, copied from `RetentionScheduler` (`wp_schedule_event(..., 'daily', ...)` on `init`),
  ends expired grants and cancels pending requests that are no longer requestable (FR-008; there is no upgrade hook).
  Each end dispatches a new `AccessGrantEndedEvent`; a notification producer, like `AccessRequestNotificationProducer`,
  notifies the person and the approver (`NotificationRecipient::forUser()`), skipping a deleted approver.
- **Rationale**: FR-005 to FR-008; the event and producer pattern already exists, and activity recording follows
  `decideRequest()` with new kinds `access.grant.revoked`, `access.grant.expired`, `access.request.cancelled`.
- **Alternatives**: expiring on read only (no notification would ever fire for a grant nobody looks at).

## R6. The grants list

- **Decision**: a new `AccessGrantsPanel.js` beside `AccessRequestsPanel.js` on the Access screen, fed by
  `GET corex/v1/access/grants` and acting through `POST corex/v1/access/grants/{id}/revoke`, both `manage_options` as
  #346 keeps the request list. Each row: person, abilities, approver, date, note, expiry or "no expiry (granted before
  expiries existed)", and a state word when the ability is no longer in effect (removed by hand, or overridden by a
  role's Deny in the matrix).
- **Rationale**: FR-005, FR-007; nothing in CoreX lists per-person caps today.
- **Alternatives**: listing on the Users screen (outside CoreX's access model).

## R7. Counting refusals

- **Decision**: `AccessAuditLog` stores merged entries `{user, kind, section, first, last, count}`, merged when user,
  kind and section match and the refusal falls within an hour of `first` (a window anchored at the first refusal, so a
  window across midnight stays one entry). Section keys are normalised to the page slug. With a persistent object cache
  (`wp_using_ext_object_cache()`), refusals within the window are counted with `wp_cache_incr()` and written to the
  option once when the window closes (next refusal after it, a read of the audit tab, or the daily sweep). Without one,
  each refusal updates that person's entry in place. A per-person cap (20 entries) applies before the global cap, so one
  person cannot push others out. The 30-day prune stays (DECISIONS #114).
- **Rationale**: the owner's answer of 2026-10-20 (FR-010, SC-003 as amended).
- **Alternatives**: a counter file under uploads (more moving parts on shared hosts).

## R8. Closing public submissions in Maintenance

- **Decision**: corex-core gets a constant marker key (`Corex\Http\PublicSubmission::MARKER`) that public submission
  routes declare in their `register_rest_route()` arguments: forms submit, flow submit, newsletter subscribe, bookings
  request, careers apply and account register. Sign-in and password reset are not marked (Clarifications). corex-config
  adds one filter on `rest_request_before_callbacks` that answers 503 (`site_in_maintenance`, `Retry-After: 3600`, a
  translated message) when the matched handler carries the marker and the Maintenance decision says closed. That
  decision is extracted from `MaintenanceGuard::shouldBlock()` into one `MaintenanceDecision` both guards call, so the
  page guard and the REST guard cannot disagree about who passes (the bypass filter of DECISIONS #123 and signed-in
  administrators included). Coming soon is decided elsewhere (`ComingSoonDecision`) and is not touched.
- **Rationale**: the add-ons depend only on corex-core and do not use the middleware pipeline, so a middleware alias
  would reach two of six routes; a declared marker is the constitution's "routes declare their security", and a new
  public route opts in the same way. The filter runs before any handler, so nothing is stored, mailed or throttled.
- **Alternatives**: a list of route patterns in corex-config (misses new routes silently); a middleware (does not reach
  the add-ons).

## R9. The Maintenance panel's words

- **Decision**: the always-visible `ModeDisclosure::consequences()` lines for Maintenance say that pages and public form
  submissions are closed, that the admin, sign-in and password reset stay open, and that WordPress's own comment form
  follows the site's discussion settings. The collapsed reference line "The REST API, AJAX, cron, and wp-admin are never
  intercepted." becomes true again ("Submissions are refused; reading is not").
- **Rationale**: FR-018; the panel's words are the owner's only explanation.

## R10. Recovery

- **Decision**: one value object `LoginRecovery` (`active(): bool`) bound in the container where
  `COREX_LOGIN_UNGUARD` is read today, injected into `LoginRouteGuard` and `OperationsSecurityScreen`; the screen passes
  `recoveryActive` to the Security Center. The `Recovery` panel and the bookmark warning gain the no-command-line route;
  the Login protection section shows the "Recovery is on" notice; the "Default login endpoints" row reads recovery as
  "Reachable (recovery on)". Code lines are shown with a small React `CorexCopyField` matching the PHP
  `StandalonePage::copyField()` markup (left to right, copy button, fallback to selected text).
- **Rationale**: FR-020 to FR-023 without a second `defined()` check in the screen.

## R11. Blog Pro permissions

- **Decision**: one injected `BlogProPermissions` policy used by every Blog Pro route's `permission_callback` and by the
  screen: base permission `edit_posts` for the screen and every route; a post's figures, held comments and share
  controls need `edit_post` on that post; an editorial transition needs `edit_post`, and to Published or Scheduled also
  `publish_post`; approving, spamming, trashing and replying need `moderate_comments`; editing a comment needs
  `edit_comment` on it. The authors table needs `edit_others_posts`; without it the viewer sees only their own row. The
  screen lists only posts the viewer may edit and sets each control from the same policy (DECISIONS #159: hidden by the
  permission the route enforces). `POST blog/share-click` answers `recorded: false` and stores nothing (FR-027).
- **Rationale**: FR-024 to FR-028 as amended; `CapabilityMiddleware` takes no object id, so per-object checks go in the
  permission callbacks through one policy, not in controller bodies.
- **Alternatives**: `corex_manage_blog` as the base (stage 3's job, CX-ACC-01).

## R12. Security models on the Data screen

- **Decision**: `ManagedTable` gains an optional last constructor argument `?string $ownerAbility = null` (last and
  defaulted because a client site builds a `ManagedTable` itself): `corex_manage_access` for the two access tables,
  `corex_manage_operations` for sign-in attempts. `TableDataSource` exposes it through a small `RestrictedDataSource`
  interface; `DataSourceService::catalog()` leaves those sources out for an actor without the owner ability, and
  `authorize()` refuses with a new `DataAccessRestricted` exception that `DataRestGateway` maps to 403 (not by matching
  words in a message). Refusals are recorded through a corex-core `AccessRefusalRecorder` interface implemented by
  `AccessAuditLog` (kind `data`, section = the source key), so the Data layer does not depend on the Access classes.
  Export sets leave a restricted model out at request time with the reason and the model's label (the run-time
  "left out" mechanism already exists); an export made only of restricted models completes empty with the explanation.
  Downloading an older export that holds a restricted model re-checks the owner ability. Delete stays unavailable for
  these read-only sources.
- **Rationale**: FR-029 to FR-031; `DataSourceService` is the single choke point every read goes through.

## R13. Recording an email's recipients and resending

- **Decision**: `EmailStage` records the resolved recipients in each binding's outcome (`recipients` list), kept in the
  submission's email record, so they follow its retention, trash, permanent delete and personal-data export (FR-032).
  `SubmissionEmailService::resend()` sends to those recipients. For a binding without recorded recipients (sent before
  this release), it re-resolves the binding's recipient mapping from the flow version the submission used and sends only
  if every resolved address matches what that email was sent to (`EmailAttemptRepository::wasSentTo()`, the stored
  hash); otherwise Resend is not offered and the drawer says why (FR-032a, FR-033). The `SubmissionEmailGateway`
  interface method `resend(string $attemptId, string $recipient, array $context)` is unchanged; CoreX calls it once per
  recipient, and an optional `MultiRecipientResend` interface (checked with `instanceof`) lets CoreX's own gateway send
  one email to all of them. The drawer names each email (confirmation, team notification, failure notice), says why one
  cannot be resent, and the resend answer and the timeline carry who it went to.
- **Rationale**: the owner's answer of 2026-10-20; a client site implements the gateway, so its signature must not
  change.
- **Alternatives**: storing full addresses on the email attempt (reverses the attempt store's redaction).

## R14. Order of work

Story 3 (maintenance marker) and story 4 (recovery) touch nothing the others touch and can be built first. Story 1
(schema version 4, preview, grants) comes before story 6, whose refusals the audit records through story 2's merged
entries, so story 2 lands before story 6's recording. Story 5 and story 7 are independent.

## R15. DECISIONS

The decisions above are recorded in `DECISIONS.md` as they are implemented, starting at the next free number on `main`
when the first implementation commit is made (the last on this branch is #372; read `main` and open pull requests
first).
