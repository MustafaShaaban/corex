# Corex — Progress

**This is a resume point, not a history.** It holds the current baseline, what is in flight, and what
to do next. A session that ends does not append here — it *replaces* what is here.

Where the history went, and why you can stop looking for it in this file:

| Question | Answered in |
|---|---|
| What changed in a release? | [`CHANGELOG.md`](CHANGELOG.md) |
| Why was it built that way? | [`DECISIONS.md`](DECISIONS.md) |
| What was the agreed contract? | [`specs/`](specs/) — one directory per feature, written before its code |
| What happened on a given day? | `git log` |
| What works today? | [`PROJECT-STATUS.md`](PROJECT-STATUS.md) |
| What is planned? | [`ROADMAP.md`](ROADMAP.md) |

Until spec 098 this file was 4,589 lines of stacked `RESUME HERE` blocks, forty sessions deep. Only
the top of it was ever read, and everything beneath that top was already recorded in one of the six
sources above — usually better, and always somewhere a reader could find it. (DECISIONS #216)

---

## Baseline

- **Latest published release: v0.43.1** — tag `v0.43.1`, reachable from `main`.
- **`main` is green** on all six required checks, verified against **WordPress 7.1**.

**`main` can go red without a commit, and that is the design.** CI provisions WordPress with
`--version=latest`, so a result here depends on two inputs and only one of them is in git. Core went
7.0.4 → 7.1 on 2026-08-31 and took two browser specs with it while `main` sat untouched and
green-looking; the breakage was finally read off an unrelated dependency PR three weeks later. There
is a nightly run on `main` now so the discovery happens where it belongs (DECISIONS #221). A red
nightly means current WordPress broke us — read it like a red pull request.

**CI is the authority for the integration and browser suites.** A long-lived development install
accumulates rows a freshly provisioned one does not, which is why five integration tests failed
locally and passed in CI until 2026-10-04 (#224, under "Recently landed"). Check a claim against a CI run, not
against your machine. `composer test`
additionally segfaults at shutdown on Windows/PHP 8.3 ZTS — it does so on an unmodified tree too.

## In flight

No feature spec is in flight. Spec 101 (coming-soon mode) and spec 102 (update-safe client sites)
are both in v0.43.0, which makes it the first release a client repository can be created from with
the mode in it and with a way to take every later release by merging.

One dependency pull request is held: #240, `@wordpress/scripts` 34 → 36, a toolchain major that
wants its own verified pass. It would retire both overrides listed under "Open, and not hidden".
Three routine Dependabot pull requests are open beside it (#241, #242, #243).

## Recently landed

On `main` since v0.43.1, and not in a release yet:

- **A form no longer rewrites an email address before judging it** (DECISIONS #245). An `email`
  field went through `sanitize_email()` ahead of validation, so `sal,ma@example.com` was stored as
  `salma@example.com` and a malformed address on a required field was answered with "required".
  `EmailAnswer`, in corex-core's `Support` namespace, hands the rules what was typed; the form and
  flow controllers both use it. One existing test had asserted the defect and is corrected.

Released in v0.43.1, a patch release. [`CHANGELOG.md`](CHANGELOG.md) has the full entry, with what
changes for a client site; the decisions are #239 to #244.

- **A full integration run no longer leaves transients on the install, and the flow tests delete
  only what they created** (#236, tests only, DECISIONS #240). Two things were left open by #231. A
  run left five transients: rate-limit counters from `FlowControllerTest`, `FlowLifecycleTest` and
  `SubmitLifecycleTest`, and a migration preview from `DataManagementControllerTest`. Those tests
  now put back every transient they write — one that was absent is removed, one that was there gets
  its value and expiry back. And the two flow tests cleaned up by comparing the newest 500 flows,
  submissions and Email Studio posts before and after, which deleted whatever another process
  created during the test; they now record the posts they insert. Measured on the development
  install with the same before-and-after snapshot as #231, a rate-limit counter seeded first: 377 of
  377 pass twice in a row, the seeded counter is unchanged, and the only difference is
  `_transient_doing_cron`, the lock WordPress itself takes when a scheduled event is due on any
  load.
- **The browser specs remove the access requests they file** (#227, tests only).
  `security-access.spec.js` filed one as the administrator on every run and never decided it;
  `access-request.spec.js` left three as `corex-requester`, denied. Both now delete them in
  `afterEach` through WP-CLI — the first by the id it is told, the second by requester and by
  being newer than the newest request that existed before the file ran. Measured on the local
  install: 219 rows before and after each spec. Without WP-CLI the specs still run, the rows stay,
  and stderr says so.
- **A retention action the form does not offer is refused, not a fatal error** (#237, DECISIONS
  #239). The handler passed any posted action to the retention service and did not catch its
  exception. It now checks the action first and the screen answers with an error notice. One unit
  test drives the real handler with such an action; one integration test pins the notice.
- **The "No default admin account" hardening check can pass.** It compared `username_exists()`
  with `null`; WordPress answers `false`, so every site was warned and sent a readiness blocker,
  whatever its accounts were called. Found on the first client site built from v0.43.0. Two
  integration tests ask WordPress itself, where before only the engine downstream of the fact was
  tested. The blocker's title changes with it: a check's label states the condition that passes,
  so "<label> is not ready for production" read backwards; it is "Readiness check not met:
  <label>" now.
- **`max_length` and `min_length` count characters** (DECISIONS #241). They were the same classes
  as `max` and `min`, which compare an all-digit answer as a number, so a phone number was "too
  long" for a 32-character field and the stock contact form refused a message of `2025`. Two new
  rules on the server, the same two in the form runtime, and the contact form moved onto them.
  `max` and `min` keep both meanings; whether they should is under "Open, and not hidden".
- **Login protection reads `X-Forwarded-For` from the trusted end** (DECISIONS #242). With
  trusted-proxy mode on, the resolver took the leftmost untrusted address, which is the part of the
  header a client writes: a visitor behind a trusted proxy chose the address its failures were
  counted against. It takes the nearest hop no trusted proxy vouches for. Three unit cases pin it.
- **A form other than `contact` is no longer emailed through the contact template** (DECISIONS
  #243). The default listener named `contact-notification` for every form; with CoreX Mail active
  the template replaced the generated body, so other forms' fields never reached the email.
- **The dependency gate passes again** (DECISIONS #244). Thirteen advisories were published
  against a tree that had not moved and the gate failed on every pull request from 2026-10-06.
  Six are closed by updates and two by overrides; five have no release to take and are bounded,
  four on `simple-git` and one on `sprintf-js`. The two `extract-zip` exceptions are gone with the
  package. All 169 built assets are byte-identical to the ones built before the pass.

Released in v0.43.0:

- **Spec 101 — coming soon is an operations mode** (#210, DECISIONS #238). While it is on, a
  signed-out visitor gets the coming-soon page at the home URL with a 200 and a temporary redirect
  to it from everywhere else; anybody who can edit posts gets the real site, and is told on every
  page that visitors do not; a preview link shows the real site to somebody without an account
  and is removed when the site leaves the mode; `wp corex mode get|set` reads and changes the mode
  by the screen's own rules; and `make:site` gives every new client theme its own
  `templates/coming-soon.html`. The whole behaviour is one pure table,
  `ComingSoonDecision::for()`, and a browser project of its own proves it from outside.
  `specs/101-coming-soon-mode/tasks.md` records each task and what it found. The guide is
  *Coming soon mode*, in English and Arabic.
- **Submission retention reaches every record again** (#228, DECISIONS #232). A run used to be
  handed the newest 500 private submissions past the window whatever had been done to them, so
  once 500 were anonymized or archived it re-handled those and never reached the rest. A run now
  skips what its action has nothing left to do for and takes the oldest first. Three decisions
  come with it: a submission is due while it is past the window and not anonymized, so the count
  on the Submissions screen no longer includes anonymized ones; an archived submission stays due
  and can still be anonymized or trashed; an anonymized one is finished and retention does not
  archive or trash it. Six integration tests in `tests/Integration/Retention/` pin it. On the
  development install the 30-day selection went from 500 already-anonymized ids to the 7
  submissions that still hold their data.
- **The retention form says which action it ran** (#229, DECISIONS #233). Its confirmation box and
  its result notice said "trash" for Archive and Anonymize as well. The box now asks to confirm
  the selected action and says anonymizing cannot be undone; the prune handler sends the action
  back with the count, and the notice says archived, moved to trash or anonymized. Five
  integration tests and two unit tests pin it.
- **WP-CLI no longer logs "translation loading … triggered too early" on every request** (#232,
  DECISIONS #235). `CliServiceProvider` and `MediaServiceProvider` built their command definitions
  while booting on `plugins_loaded`, and a definition translates its help text. Both now register
  on `cli_init`, the hook WP-CLI fires on `init`. The help text stays translatable and every
  synopsis is unchanged. Spec 101's `modeCommandDefinition()` is covered without an edit. Two unit
  tests pin it, one per provider.
- **A retention run refused for want of confirmation is a warning, not a success** (#235,
  DECISIONS #237). Applying retention without ticking the confirmation box runs nothing; the
  notice saying so was drawn with the success tick. Two integration tests pin the tone of each
  retention notice.
- **Spec 102 — a client site the framework can be updated underneath** (#211, DECISIONS #230). The
  framework prescribed `sites/<client>/` and its own checks rejected it. A client repository now
  passes them untouched: one ownership file says which paths are the client's, hygiene, the
  linters, Jest and prettier read it, `npm run verify:framework` proves the framework files match
  the recorded release, and `make:site` generates the baseline record, the update checklist and the
  client's own CI. `client-site-layout` in CI generates a real site and runs every check against
  it. The update procedure is documented in English and Arabic.
  `specs/102-update-safe-client-sites/tasks.md` records each task, including the ones the work
  changed. [`CHANGELOG.md`](CHANGELOG.md) lists it under 0.43.0.
- **The integration suite no longer rewrites the operations mode of the install it runs against**
  (#221, tests only). `OptionalDashboardWidgetsTest` saved `OperationsModeStore::current()` and
  handed it back to `set()`: on an install that had declared nothing, that declared it
  `production`; on a declared one, every run pushed rows into a history capped at twenty. It now
  snapshots and restores `corex_operations_mode` and `corex_operations_mode_log`, as the Operations
  tests do. `GuideExtensionTest` and `AccessControllerTest` delete the subscriber they create, and
  the second no longer borrows the first subscriber the install has — approving its request
  granted that account a real ability.
- **The integration suite passes on a long-lived development install, and no longer leaves
  submissions or audit events on it** (#224, tests only, DECISIONS #231). Five tests failed there
  because they counted rows on the whole install: flow 90 held 23 submissions, and 131 export
  events sat under the actor id the activity fixtures use. Each test now mints a submitter of its
  own and searches for it, and the activity queries are scoped to the window the fixtures are
  seeded into. Four files stopped leaving rows: `ProductDataPrivacyTest` (a backdated submission
  its cleanup could not see, and for every export a job row, a scheduled event and an audit
  event), `SubmitLifecycleTest` (a submission and a logged email), `DataManagementControllerTest`
  and `ProductMutationSecurityTest` (audit events). The retention test in `ProductDataPrivacyTest`
  also anonymized the install's own submissions older than thirty days on every run, and left the
  retention window at 30 whenever it failed; it now acts on its own two records and restores the
  option. Measured on the install the failures were reported from: 359 of 359 pass, twice in a
  row. What the suite still changes there is listed under "Open, and not hidden".
- **The Access tests remove what filing a request writes, and put the editor role back** (#223,
  #225, tests only). The request rows, their audit events and the notifications to the
  administrators outlived the subscriber the tests delete. `AccessControllerTest` also granted the
  editor role `corex_manage_forms` through the real controller and left it granted; it now
  restores the grant row it found.
- **The integration suite no longer empties the notification tables of the install it runs against,
  and no longer leaves rows on it** (#231, tests only, DECISIONS #234).
  `NotificationControllerTest`, `WpNotificationRepositoryTest` and `NotificationPerformanceTest`
  began every test with an unqualified `DELETE FROM` on the notification and read-state tables, so
  each run removed every notification the developer had and what each user had read. They now act as
  actors nothing else on the install addresses — an account the controller test creates with no
  role, and two user ids no account has — and delete only rows under a dedup-key prefix of their
  own. The controller test had also been rewriting the administrator's notification preferences.
  Stopping the delete uncovered what it had been hiding, fixed in the same change:
  `FlowControllerTest` and `FlowLifecycleTest` each left a notification about their flow, and
  `CommandCenterWidgetTest` renders the widget, which evaluates readiness for real and records the
  install's own blockers; it now puts those rows back as they were. The rows the suite left are
  cleaned up by naming them: eight logged emails (`CallRequestDataPathTest`,
  `ApplicationDataPathTest`, `MailLifecycleTest`, `SubscriptionLifecycleTest`), two reading events
  (`BlogProControllerTest`), and two ids on `corex_kit_seeded_pages`, which came from
  `SetupConflictTest`. `DataManagementControllerTest` records the runs it creates instead of
  comparing the newest 500 before and after. One more was found by checksum, not by count:
  `LoginProtectionEnforcementTest` emptied the login-attempt table and deleted the login policy
  without putting it back, and `LoginRecoveryTest` left a lockout row and released every active
  lockout on the install; both now act on rows from their own documentation-range address. Measured
  on the development install with a before-and-after snapshot of post ids, every prefixed table's
  row count and checksum, option hashes, cron events, users and user meta: 377 of 377 pass, and
  across three consecutive runs the only differences were five transients, which #236 has since
  stopped leaving.

**v0.42.1**, a patch release. [`CHANGELOG.md`](CHANGELOG.md) has the full entry; the decisions are
#224 to #229.

- **`composer install --no-dev` no longer drops seven WP-CLI commands** (#202, issue #201). A
  production install is a `--no-dev` install, and it could not run `wp corex migrate`.
- **Two dependency advisory passes** (#203, #212), the first of which cleared a critical `astro`
  finding in the docs site. The gate passes with three bounded exceptions.
- **The dependency gate runs on every pull request** (#198).
- **`@wordpress/components` 38 → 41** (#208), with three routine Dependabot bumps (#205, #206, #207)
  and the lockfile put back in the order npm writes it (#216). The build does not bundle that
  library, so the bump was verified by building at both versions and comparing the output
  (DECISIONS #229).
- **The linters and Jest no longer walk `wp-ms/` or session worktrees** (#213, #214).
- **The browser-test sign-in helper no longer loses the password to WordPress's focus timer**
  (#217), and the browser job keeps the server's logs when it fails (#219). Two of three recent
  browser failures were that one bug; the third is under "Open, and not hidden" (DECISIONS #228).

## Next

The next *feature* spec is an owner decision; [`ROADMAP.md`](ROADMAP.md) §17 lists the candidates in
order. **M3 (navigation and template parts) and M4 (the company-page contract)** are the substantive
product direction — everything else on that list is remediation or productization.

Roadmap presence does not authorize implementation (`ROADMAP.md` §16).

## Open, and not hidden

Each is stated with the file that records it in [`PROJECT-STATUS.md`](PROJECT-STATUS.md):

- Three browser specs are excluded from a fresh-install run — two block-editor specs and the flow
  builder. The list lives in `tests/e2e/playwright.config.js` with the evidence for each.
- **The hidden `/wp-admin/` 404 is distinguishable from a real one by its inline styles.** It carries
  block styles for CoreX blocks that are not on the page; a real 404 does not. Structurally
  unreachable from here — `wp_should_load_separate_core_block_assets()` returns false on `is_admin()`
  before its own filter runs. Measured in DECISIONS #222.
- Six bounded dependency exceptions, each with a named upstream trigger (DECISIONS #227, #244).
  Four are on `simple-git` and its argument parser, two of them rated critical: it runs when a
  developer starts wp-env, the fix is in a major `@wordpress/env` cannot use yet, and what it is
  given comes from `wp-env.json` in this repository. They are reviewed on 2026-11-30. One is
  `sprintf-js`, installed and not reached. One is `braces` (GHSA-vfj7-8cjw-p6xm), which runs in the
  linter and the build on patterns that come only from tool defaults. None has a release this tree
  can take.
- **Two overrides hold packages above what their parents ask for**: `lighthouse` at 13 and
  `postcss-selector-parser` at 7.1.6, in the root and in `docs-app`. Both go when
  `@wordpress/scripts` 36 is taken, a toolchain major that has not been attempted
  (Dependabot's #240 proposes it).
- **Something behind `GET corex/v1/flows` threw on 2026-10-04, and nobody knows what.** Three
  `submissions-inbox` specs in a row got "Request could not be processed." on #211, with nothing
  else running, straight after a seed that had succeeded. Every exception the flow code raises on
  purpose is answered with a 409 or a 422, so this was one it does not expect. The message went to a
  log CI did not keep. It keeps it now, in a file the job names and proves with a probe line on
  every run. `seedSubmission` reports the server's answer instead of a `TypeError`, so the next
  occurrence names itself (DECISIONS #228).
- **`max:N` and `min:N` decide what to measure from what the answer looks like.** `2025` in a text
  field is compared as a number. Left as it is by DECISIONS #241, which names the alternative
  (compare as a number only beside `numeric`) and why it is a behaviour change, not a fix.
- **Five add-on handlers still clean an email address and then accept it**: newsletter subscribe,
  bookings, careers applications, profile registration and the Guides support request each call
  `sanitize_email()` on what was posted. The forms engine was fixed (DECISIONS #245); these were
  read, not changed.
- **Form rate limits count every visitor behind a proxy as one.** `SubmitController`,
  `FlowSubmissionController` and `FormChallengeContextFactory` read `REMOTE_ADDR` directly, so
  behind a reverse proxy or a CDN the per-client allowance is shared by the whole site. The
  resolver login protection uses cannot be reached from corex-forms as it stands (DECISIONS #242).
- **The dependency gate is still not a required check**, so a red result on `main` blocks nothing.
  Its weekly run on `main` failed every week from 2026-08-12 to 2026-09-30.
- **`client-site-layout` is not a required check either** (spec 102). Making it one is an owner
  setting in branch protection.
- Nothing enforces that `docs/ar/` mirrors `docs/en/`; five pages had no Arabic counterpart from
  spec 087 until 2026-09-04.
- Arabic typography is proved for layout, not for type.
- Development installs predating spec 091 may hold leaked fixture users. Since #221 the
  suite no longer adds `guides-subscriber-*` or `corex-access-requester` accounts; the ones already
  there are not removed by anything.
- `ResetExecutorTest` leaves `show_on_front` at `posts` and `page_on_front` at 0, whatever the
  install had. Read from the test on 2026-10-04, not measured: a before-and-after snapshot shows
  no difference on an install already at those values (DECISIONS #235).
- What earlier runs left on an existing install is not removed by anything: submissions the
  retention test anonymized cannot be restored, and neither can notifications or read state the
  notification tests deleted. `corex_retention_submissions_days` may read 30 on an install whose
  owner never set it, `corex_role_ability_grants` may hold an `editor` / `corex_manage_forms` row
  nobody granted, and the first administrator's notification preferences may have the jobs
  category switched off. The Access request rows (the browser specs' among them), audit events,
  logged emails, reading events about deleted posts and stale ids on `corex_kit_seeded_pages` from
  earlier runs stay too.
- **Deleting a user leaves that user's access requests in place**, pointing at nobody. Nothing in
  CoreX listens for a user being deleted. Whether it should remove or anonymize those requests is
  a product question nobody has decided; the tests clean up after themselves either way.

No security items.

## Before you edit anything

1. [`.specify/memory/constitution.md`](.specify/memory/constitution.md) — the non-negotiable rules.
   They override everything, including this file.
2. [`AGENTS.md`](AGENTS.md) — the precedence order, the Role Gate, and the required handoff format.
3. The active spec in [`specs/`](specs/) for whatever you are about to touch.
