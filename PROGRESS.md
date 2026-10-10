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

- **Latest published release: v0.44.0** — tag `v0.44.0`, reachable from `main`.
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

**Nothing is being built.** On 2026-10-10 no pull request and no issue is open, and v0.44.0 holds
everything on `main`. The owner asked for the framework to be left stable so the client sites
can take one release and be worked on without it; this section says where each unfinished spec
stops, so work resumes from here and not from a transcript.

**Nothing half-built can be reached.** The classes of spec 107's fourth slice are bound to no
route and no screen, and the Releases screen says it installs nothing yet.

| Spec | In v0.44.0 | Where it stops | Resume with |
|---|---|---|---|
| [103](specs/103-submissions-inbox-export/spec.md), the submissions inbox and its exports | every slice | three tasks: T045, T043b, and T074b (to whom a notification went, which the server does not record) | the tasks file |
| [104](specs/104-form-contract-and-protection/spec.md), a code-defined form's wording, markup and protection | all four slices | complete | nothing |
| [105](specs/105-submission-trash-restore-delete/spec.md), trash, restore and delete a submission | slices 1 to 4 of six | slices 5 and 6 have a heading in `tasks.md` and no tasks | `/speckit-tasks` for slice 5: anonymizing removes the files and the second copy of the answers |
| [106](specs/106-reply-email-experience/spec.md), replies sent as designed emails | the specification, and the line-break fix | not planned | `/speckit-plan` |
| [107](specs/107-release-from-admin/spec.md), a release installed from the admin | slices 1 to 3; of slice 4, T040 to T047 | T048 to T056, then slices 5 and 6 | T048, `ReleaseTableCopies` |
| [108](specs/108-loading-states/spec.md), the admin shows what is coming while it loads | all six slices | complete | nothing |

What to know before resuming each:

- **Spec 105, slice 5.** `WpSubmissionsReader::anonymizeForRetention()` removes a submission's
  answers and leaves its uploaded files on disk, where nothing can find them again. This is a
  defect a site has today and is under "Open, and not hidden".
  `SubmissionTrashService::forgetTiedData()` already removes a submission's files and its email
  copies, and has to be called before the answers that name the files are removed.
- **Spec 107, slice 4.** It is the first slice that moves a site's folders. Built and tested
  over real folders in a temp directory: unpacking and checking (`ReleaseStaging`), the swap by
  journaled renames (`ReleaseSwap`, `ReleaseJournal`), WordPress's maintenance answer with one
  keyed way through (`ReleaseMaintenance`), and the recovery must-use plugin
  (`ReleaseRecoveryPlugin`). Owed: a copy of each table before the database step
  (`ReleaseTableCopies`), the database step on the new code (`ReleaseFinisher`), the
  installation that ties the steps together with its routes (`ReleaseInstallation`), and the
  screen. Since DECISIONS #299 an add-on's table is created by the migration runner: read
  `Boot::providersForState()` and the schema registry before writing the finisher.
- **Specs 105 and 108 carry readings that are the owner's to overrule**, under each spec's
  Assumptions; so does spec 103 (opening a submission marks it read) and spec 104.

No dependency pull request is open. **Node 22.22 is the floor** for building (DECISIONS #272); an
older Node 22 prints a warning and builds.

## Recently landed

**v0.44.0, 2026-10-10.** [`CHANGELOG.md`](CHANGELOG.md) has the entry, and its Client impact is
long: read it before a client site takes the release. In short:

- **Submissions can be moved to a trash, restored and deleted for good** (spec 105, slices 1 to
  4), and the trash empties itself after a number of days the site sets.
- **Exports as PDF**, and the Data screen's export through the same dialog as the inbox's (spec
  103).
- **A form defined in code states its own wording, can be drawn with a site's markup, and is
  challenged by reCAPTCHA, Turnstile or hCaptcha** (spec 104).
- **The admin shows what is coming while it loads**, and every action marks the control that
  was pressed (spec 108).
- **CoreX → Releases** reads a release package given to a site and says what it is. It installs
  nothing yet (spec 107, slices 1 to 3).
- **Fixed, from the client sites:** a hidden login address was given away by three WordPress
  addresses; a file sent through a form answered 500; a checkbox group was stored empty and a
  choice field took any text; a reply lost its line breaks; the mail queue deferred
  nothing where Action Scheduler was absent; three add-ons ran `dbDelta()` on every request.
- **The last three open issues** (#306, #313, #323; DECISIONS #302). With them the role matrix
  on Access & Abilities can be saved for the first time.
- **An answer with several choices reads as its choices on the Data screen** (DECISIONS #303),
  where it was printed as JSON. A client site carried that as a patch to a framework file and
  can drop it.

Earlier releases are in [`CHANGELOG.md`](CHANGELOG.md), each with its decisions.

## Next

The next *feature* spec is an owner decision; [`ROADMAP.md`](ROADMAP.md) §17 lists the candidates in
order. **M3 (navigation and template parts) and M4 (the company-page contract)** are the substantive
product direction — everything else on that list is remediation or productization.

Roadmap presence does not authorize implementation (`ROADMAP.md` §16).

## Open, and not hidden

Each is stated with the file that records it in [`PROJECT-STATUS.md`](PROJECT-STATUS.md):

- **Anonymizing a submission leaves its uploaded files on disk.** The retention panel's
  "Anonymize" removes the answers, which are what name the files, and not the files. Nothing can
  find them afterwards. It is spec 105's fifth slice, not started; deleting a submission for good
  from the trash does remove its files.
- **A release cannot be installed from the admin yet.** CoreX → Releases receives a package and
  says what it is. A site on a host with no command line is still updated by hand, by unpacking
  the shared-host package over the old one (spec 107, from its fourth slice).
- **The role matrix's confirmation is built by the screen.** Applying a change sends back the
  hash of the preview that was read, with who and until when; the server checks the hash against
  the changes sent and the person asking. It is not a token the server issued (DECISIONS #302).
- **Spec Kit was not run end to end in a client repository.** Its state file is no longer
  tracked and its hook is off, so the two framework files it changed are out of the way, and its
  own path script takes a site's directory (#251, DECISIONS #279). Nobody has run `/speckit-specify`
  in Muva or Perego and then `npm run verify:framework`.
- **A hosting package's `vendor/` can be fetched from the web.** CoreX's own describing files are
  left out of a package now (DECISIONS #280). Third-party `README.md` and `composer.json` files
  and `vendor/composer/installed.json` are not, and the package ships no server rule, because
  the host owns its `.htaccess`. Nothing tells an operator to refuse requests into it.
- **From the audit of the first site on shared hosting, not done:** the REST index lists every
  `corex/v1` route to anybody (each answers 401 or 403); there is no setting for security
  headers. The coming-soon page's head is fixed; what its browser test asserts was run in CI
  and not locally, where the spec needs fixtures the whole suite sets up.
- **Turnstile and hCaptcha were not submitted through on a real site.** Their widget is placed
  and a tokenless submission is refused (DECISIONS #278). What was seen is each provider's own
  script rendering into a bare form on a local page, and Turnstile's token cycle there. Nobody
  has completed an hCaptcha challenge through CoreX, and neither was seen inside a CoreX form.
- **Four add-ons check a challenge token nothing produces**: bookings, careers, newsletter and
  profile each verify `captcha_token` on their own route, and no widget or script is placed for
  them. With a provider configured they refuse on a missing token. Read from the code while
  planning spec 104; out of that spec's scope.
- **A message sent from Email Studio cannot name its sender** (#150, the third of its three
  mailboxes). An operator's reply from the Submissions inbox, a routed email and a message
  composed in Email Studio are built in `EmailStudioSubmissionGateway`, `EmailRouteMessageFactory` and `EmailStudioController`
  with no sender, and go to the driver without passing `MailService`. They leave from
  `mail.from.address`. Read from the code on 2026-10-08, not sent (DECISIONS #264).
- **Trusted proxies have no field on the Security screen.** The screen's state carries the list and
  saves it back unchanged; nothing draws it. `wp corex security trusted-proxies` is the only way to
  set it (DECISIONS #250).
- Three browser specs are excluded from a fresh-install run — two block-editor specs and the flow
  builder. The list lives in `tests/e2e/playwright.config.js` with the evidence for each.
- **`npm run env:start` appears not to read `wp-env.json`.** The script is `wp-env start` with no
  `--config`, and `@wordpress/env` 11.16.0 looks for `.wp-env.json` (`lib/config/parse-config.js`).
  Read from the source, not run: no Docker run was made. If it holds, the wp-env setup guide
  starts a WordPress with none of CoreX mapped. `README.md` and
  `docs/internal/COREX-FRAMEWORK.md` also still say wp-env matches CI, which uses none.
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
- **One override holds a package above what its parents ask for**: `postcss-selector-parser` at
  7.1.6, in the root and in `docs-app`. `@wordpress/scripts` 36 did not retire it as expected:
  `cssnano` 6 still asks for 6.x. Three more hold `js-yaml`, `smol-toml` and `katex` at patched
  releases under `markdownlint-cli` (DECISIONS #272).
- **The Jest suite runs on packages npm marks unsupported**: `@wordpress/jest-preset-default` and
  `@wordpress/jest-console`. Upstream maintains that combination and promises nothing past it;
  moving 64 suites to Vitest is its own piece of work, not started (DECISIONS #272).
- `npm run lint:js` passes with warnings and no errors (58 on 2026-10-08), all from three JSDoc
  rules new in the toolchain.
- **PHP on the CI runner refused a correct argument until the run ended, and the cause is a lead,
  not a proof.** On 2026-10-08 (run 37746210110) `GET corex/v1/flows` answered 200 twenty-eight
  times and then 500 for the rest of the run. The log line: `FlowRestMapper::summary(): Argument
  #2 ($version) must be of type , Corex\Forms\Flow\FlowVersion given`. The parameter is declared
  `FlowVersion`. Eight `submissions-inbox` specs failed on it. Three failed the same way on
  2026-10-04 on #211, when the log was not kept, so that one is assumed and not shown to be the
  same fault. Nothing in CoreX can produce that message, and no test can reproduce it. The job's
  php-fpm was running the tracing JIT with a 256 MB buffer, which nothing in the workflow asks
  for; since #286 the job switches it off and checks. If the fault comes back with the JIT off,
  the JIT was not it: the job prints OPcache's status on every run (memory, string buffer,
  restarts, the JIT), and an ordinary run ends with the 8 MB string buffer 94% full, which is the
  next thing to look at. Re-running the job has cleared it each time (DECISIONS #228 and #268).
- **`max:N` and `min:N` decide what to measure from what the answer looks like.** `2025` in a text
  field is compared as a number. Left as it is by DECISIONS #241, which names the alternative
  (compare as a number only beside `numeric`) and why it is a behaviour change, not a fix.
- **Form rate limits count every visitor behind a proxy as one.** `SubmitController`,
  `FlowSubmissionController` and `FormChallengeContextFactory` read `REMOTE_ADDR` directly, so
  behind a reverse proxy or a CDN the per-client allowance is shared by the whole site. The
  resolver login protection uses cannot be reached from corex-forms as it stands (DECISIONS #242).
- **The dependency gate is still not a required check**, so a red result on `main` blocks nothing.
  Its weekly run on `main` failed every week from 2026-08-12 to 2026-09-30.
- **`client-site-layout` is not a required check either** (spec 102). Making it one is an owner
  setting in branch protection. It is also the only place a built `dist` package is started with
  WordPress and a database (DECISIONS #267); the Jest suite proves that the package loads its
  classes, on a fixture.
- **The Azure pipeline builds a package with no WordPress core in it.** The builder takes core from
  `wp/`, which is git-ignored, and `azure-pipelines.yml` has no step that downloads it. The build
  prints a warning and `verify:dist` does not ask for core. The pipeline's deploy stage is still a
  placeholder. The cPanel guide downloads core into `wp/` before building.
- **Nothing checks that a `dist` package holds the built admin and block bundles.** They are
  git-ignored build output, and the builder copies what is there. A package built without
  `npm run build` verifies: the one built for DECISIONS #267 was.
- **The fixed `dist` package was not uploaded to a shared host.** It was started on a development
  machine and in CI (DECISIONS #267). Whether a host's PHP, its file permissions and its web server
  serve it is the `shared-host` profile's blocker, as before.
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
