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

- **Latest published release: v0.45.2** — tag `v0.45.2`, reachable from `main`.
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

**Nothing is in flight** (2026-10-11). v0.45.2 holds everything on `main`, and no pull
request is open. It is v0.45.1 and two changes after it: a form's notification email names
the file it was sent with and links to the submission in the inbox, and does not attach it
(spec 081, Phase G; DECISIONS #319; the owner's decision); and the inbox's search finds a
submission of a form defined in code by the sender its row shows (spec 103, T110 to T113;
DECISIONS #318). The owner asked for the framework to be left stable so the client sites can
take one release and be worked on without it; this section says where each unfinished spec
stops, so work resumes from here and not from a transcript.

**Spec 107 is built and released, and has not run on the host it is for.** The Releases screen
installs and goes back, and the operator's guide and the first-installation runbook are written
(`docs/en/05-deployment/releases-from-the-admin.md`, `first-installation-by-hand.md`). What is
owed is not code: the first installation on a shared host, of a release that changes nothing
important, by somebody who can watch it; then `last_verified` on those two pages.

| Spec | In v0.45.0 | Where it stops | Resume with |
|---|---|---|---|
| [103](specs/103-submissions-inbox-export/spec.md), the submissions inbox and its exports | every slice | three tasks: T045, T043b, and T074b (to whom a notification went, which the server does not record) | the tasks file |
| [104](specs/104-form-contract-and-protection/spec.md), a code-defined form's wording, markup and protection | all four slices | complete | nothing |
| [105](specs/105-submission-trash-restore-delete/spec.md), trash, restore and delete a submission | slices 1 to 4 of six | slices 5 and 6 have a heading in `tasks.md` and no tasks | `/speckit-tasks` for slice 5: anonymizing removes the files and the second copy of the answers |
| [106](specs/106-reply-email-experience/spec.md), replies sent as designed emails | slices 1 to 4 of seven. Slice 1: the reply box says when delivery is off and where to switch it on, the pane says what happened to a reply, and the reply is kept on the submission (DECISIONS #305). Slice 2: a reply leaves in the standard design with a plain-text version (#308). Slice 4, built before 3 because the client sites waited for it: a site gives its emails its own colours, logo and footer, or its own template (#311). Slice 3: the reply is written in an editor with bold, italic, lists and links, and Preview shows the email the server will send (#313); a reply that was begun is kept as that person's draft until it is sent or discarded (#320) | slice 11, the email saying what it is, which waits on the owner's answer; then the rest of 5 (the reply's sender). Slice 5's first half is built: a reply that did not go can be sent again (#328). Slice 10 is built: a reply is sent in the conversation the person already has, or as a new email (#325). Slice 8 is built: the header is a band of the card and a logo of any shape is held to 180 by 48 (#322). Slice 9 is built: a reply starts with "Re: " and the form's name (#323). Stories 8 to 11 came from the owner's reading of live replies on 2026-10-12 (#321) | story 11's answer |
| [107](specs/107-release-from-admin/spec.md), a release installed from the admin | all six slices (DECISIONS #307, #310 for the last three) | built | a first installation on a shared host, watched |
| [108](specs/108-loading-states/spec.md), the admin shows what is coming while it loads | all six slices | complete | nothing |
| [109](specs/109-data-bulk-transfer/spec.md), several data models exported, imported and moved together | slices 1 and 2 of six: personal data marked and asked about by one rule (DECISIONS #309); several models in one export, with a history and a 30-day expiry (#312) | slices 3 to 6 have tasks and no code | T050, slice 3: an import knows a row that is already there, and takes several files |

What to know before resuming each:

- **Spec 105, slice 5.** `WpSubmissionsReader::anonymizeForRetention()` removes a submission's
  answers and leaves its uploaded files on disk, where nothing can find them again. This is a
  defect a site has today and is under "Open, and not hidden".
  `SubmissionTrashService::forgetTiedData()` already removes a submission's files and its email
  copies, and has to be called before the answers that name the files are removed.
- **Spec 109.** From the owner's report of 2026-10-11 on the first client site: Import, Export
  and Migrations on CoreX → Data each take one model at a time. He answered its five questions
  "as recommended" (DECISIONS #306): "migrate" meant moving records between installs, so
  several table changes together are not built; pages, templates and settings get a spec of
  their own. Slice 1 changes who is offered personal columns on the Data screen, and slice 2
  removes Data export files older than 30 days on the first daily clean-up: both are in
  v0.45.0's Client impact, and a client site has to be told of both when it is told of the
  release. Slice 5 splits `Releases/ReleaseUpload.php`, a file of spec 107, which is finished;
  what the Releases side relies on is written into task T080. The line a person without the
  right is shown was drawn in Jest and not walked in a browser, because no fixture account
  manages data without being an administrator.
- **Email Studio's store.** `WpEmailStudioStore` slashes a record's name and its content
  before handing them to WordPress, so a backslash is read back (DECISIONS #329; on `main`
  after v0.45.2, not released). A store written beside it has to do the same: WordPress
  unslashes a post's fields and a meta value on their way in, whoever calls it.
- **Spec 106, a reply's subject.** It is kept as typed on the reply, preview and draft routes
  and on Email Studio's **Test send** and **Resend**, by one rule, `Corex\Mail\SubjectLine`
  (DECISIONS #326; on `main` after v0.45.2, not released). A subject can now hold `<`, `>`
  and `&` together: whatever prints one escapes it there. Read #326's list of where a subject
  is printed before adding a place that prints one. An Email Studio template's subject, and a
  value merged into it, are read by the same rule (DECISIONS #330; on `main` after v0.45.2,
  not released): nothing is stripped, and a line break in a merged value is a space.
- **Specs 105 and 108 carry readings that are the owner's to overrule**, under each spec's
  Assumptions; so does spec 103 (opening a submission marks it read) and spec 104.

No dependency pull request is open. **Node 22.22 is the floor** for building (DECISIONS #272); an
older Node 22 prints a warning and builds.

## Recently landed

**After v0.45.2, not released, 2026-10-13.** The page that shows a new preview link (Coming
soon, spec 101) holds it in a field with a **Copy link** button, the owner's ask relayed from
the first client site (FR-011a, tasks T069 to T075, DECISIONS #327). The button is shown by a
script and copies without the Clipboard API where a site is not served over HTTPS; with
scripts off the link is copied from the field by hand. `StandalonePage::copyField()` is the
first standalone page part that carries a script.

**v0.45.2, 2026-10-11.** Two changes for a form defined in code. Its notification email names
the file it was sent with and links to the submission in the inbox, where the file is opened;
nothing is attached (DECISIONS #319). A site that writes its own emails is handed the stored
submission's id and the inbox's address to do the same. And the inbox's search finds a
submission by the sender its row shows (DECISIONS #318); a search does more work than it did.

**v0.45.1, 2026-10-11.** A submission that carried a file showed `224` under "Answers".
The pane shows the file's name as a link to an address of the submission's own, which gives
the file to whoever the inbox lets read that submission; an export names the file
(DECISIONS #317). `tests/e2e/submission-file.spec.js` is the first test that uploads a file.

**v0.45.0, 2026-10-11.** [`CHANGELOG.md`](CHANGELOG.md) has the entry. Read its Client impact
before a client site takes the release. In short:

- **A release is installed from the admin, and gone back from** (spec 107, slices 4 to 6), with
  an operator's guide and a runbook for the first installation by hand. Not yet run on a shared
  host.
- **A reply from the Submissions inbox is a designed email** (spec 106, slices 1 to 4): written
  in an editor, previewed as it will arrive, sent with a plain-text version, in the site's own
  colours or template, and kept on its submission. The box says beforehand when a reply will
  not be delivered.
- **Several data models are exported in one go**, with a history that says what became of each
  file, and **a column that holds personal data leaves the site only with a person who holds
  "Export personal data"** (spec 109, slices 1 and 2). Managed tables were exported with no
  right asked until then.
- **The `corex/modal` and `corex/drawer` blocks**, six fixes (DECISIONS #315, #316): not closed
  by a press begun inside; inside the window; title and close button in view while the body
  scrolls; a name for a dialog with no title; in the page with scripts off; a drawer's entrance
  from its own edge on a page read right to left. `tests/e2e/block-dialogs.spec.js` is the
  first browser test these blocks have.
- **In the admin:** a `CorexDialog` is not closed by a press that began inside it, which lost a
  note being written in a submission's pane; a secondary button has one border, CoreX's.

Earlier releases are in [`CHANGELOG.md`](CHANGELOG.md), each with its decisions.

## Next

The next *feature* spec is an owner decision; [`ROADMAP.md`](ROADMAP.md) §17 lists the candidates in
order. **M3 (navigation and template parts) and M4 (the company-page contract)** are the substantive
product direction — everything else on that list is remediation or productization.

Roadmap presence does not authorize implementation (`ROADMAP.md` §16).

## Open, and not hidden

Each is stated with the file that records it in [`PROJECT-STATUS.md`](PROJECT-STATUS.md):

- **A client site's own notification email does not link to the submission yet.** CoreX's
  email names a form's file and links to the submission in the inbox (DECISIONS #319,
  v0.45.2). A site that writes its own emails, as MUVA does, is handed what it needs
  (`FormSubmittedEvent::submissionId()`, `Corex\Mail\SubmissionAddress`) and has to use it
  in its own listener: that is the site's change, not the framework's.
- **The notification email's link was not followed from a mailbox.** What it holds was read in
  tests, and the address is the one the inbox already opens a submission from. Nobody has
  received the email and pressed the link, signed in and signed out.
- **CoreX → Data → Form submissions prints that number too**, in its record view and in its
  own export. The Submissions inbox and its export name the file. Read from
  `SubmissionsSource`, not looked at in a browser.
- **An email made from an Email Studio template leaves without a plain-text version.** A
  template has a Plain Text tab, written by hand or made from the HTML, and the health check
  refuses one written by hand and left empty. Nothing that sends reads it: the route and the
  email sent again from a submission build their message from the subject and the HTML
  (`EmailRouteMessageFactory::prepare()`, `EmailStudioSubmissionGateway::resend()`). Measured
  on 2026-10-13 through a route on a site that delivers: the mail driver was handed no
  plain-text version, with a manual one saved on the template. A reply has one. The version
  nobody sends also loses what follows a `<` in a merged value ("under <500 EGP" is "under"
  there). Found while reading for DECISIONS #330, not changed.
- **What Email Studio stored before its store kept a backslash is still without it.** The
  store slashes what it hands to WordPress now (DECISIONS #329), and nothing repairs the
  records already there: a template, a layout or a partial saved earlier with a backslash
  in it has to be opened and saved again. Whether a client site holds one was not read.
- **A name in Email Studio is cleaned as markup for a person who may not post markup.** A
  template's, a layout's and a partial's name is a post's title, and WordPress cleans a
  title for whoever lacks `unfiltered_html`. Measured on 2026-10-13 in a request nobody is
  signed in for: `Tom & Jerry` is kept as `Tom &amp; Jerry` and `Price <500 EGP` as
  `Price &lt;500 EGP`, and the screens print a name as text. An administrator of a single
  site is not affected. By WordPress's rule an administrator of a site in a network is, and
  so is one on a site that sets `DISALLOW_UNFILTERED_HTML`: read, not measured. The content
  of a record is not a title and is kept as written. Not changed.
- **The inbox's list is slow on a site with many thousands of submissions.** Measured on
  2026-10-11 with 10,000 of them: the statement that lists the inbox with no search took
  0.6 s, and 1.2 s on a second seeding; without the clause that leaves out submissions marked
  as tests it took 18 ms. A search is quicker than no search. Whether a client site holds
  anything near that number was not read. Measured for DECISIONS #318, not changed.

- **An account that chose a password with a quote or a backslash through the account
  add-on, and whose owner has not signed in there since DECISIONS #334, is still refused at
  `wp-login.php`.** The add-on now hashes a password WordPress's way and moves such an
  account when its owner signs in on the account block; nothing moves the others, and
  nothing lists them. Moving one signs its owner out of their other browsers, once
  (measured, #334).
- **The account block's message is out of the window when the button is in it.** Measured
  in a browser on 2026-10-13: the block's one status line is above its forms, and after
  **Create account** was pressed it was 149 pixels above a window 768 high, and 377 above
  one of 375 by 812. It is announced to a screen reader (`role="status"`); somebody
  looking at the button sees nothing happen. So for every message of the block, a refused
  sign-in among them. Not changed.
- **An account registered before DECISIONS #335 with no username is still named by its
  email address.** A new one is named by the part before the @. The old ones keep the
  address as display name and nickname, and in the author address, and a comment written
  under it keeps it whatever the account is renamed to: WordPress copies the name into the
  comment (measured 2026-10-13). Putting them right rewrites a site's accounts and comments
  and is the owner's to ask for. The add-on is off in Muva's decision record and on the
  Perego and Mizzey development installs; their live hosts were not read.
- **A display name of more than 191 characters breaks the Profile form.** Measured on
  2026-10-13 through the route: a name of 192 to 250 characters is saved and the request
  then throws, because the activity record holds a name in 191 and
  `WpActivityRepository::append()` raises when its insert is refused; a name of 251 is not
  stored at all, while a first name sent with it is. Seen while measuring DECISIONS #338.
  Not changed.
- **A login made for a long address can still be refused by something else.** An address
  of 61 to 100 characters with no username gets a login made from its first part
  (DECISIONS #338). A listener on `pre_user_login` or `illegal_user_logins`, or a second
  registration at the same moment for the same login, ends in WordPress's own message
  about a username the person did not type. Read, not run.
- **A new member who chose a password is emailed a link to set one.** The account gateway
  sends WordPress's own new-user email, which reads "To set your password, visit the
  following address:". Read on 2026-10-13 from what was handed to `wp_mail()`; what
  following the link does to the chosen password was not run. Not changed.
- **A login made from an address is altered by WordPress, and that is left.** With no
  username typed, `name+shop@example.com` gets the login `nameshop@example.com`; the person
  signs in with the address, as measured (DECISIONS #333). An account registered before
  #333 under a username WordPress changed keeps the changed one; nothing lists those
  accounts.
- **What was stored before DECISIONS #331 and #332 is still without its backslashes**: a
  form's answers, a flow, a note, a comment, an editorial note, a name, an imported row, a
  kit's page, and on a Windows host the record of a WebP file. Nothing repairs it. A flow whose field has a `pattern:` rule with a
  backslash in it does not check what was written until it is written again and published.
  Whether a client site holds such a flow was not read.
- **With ACF active, a field's value goes through `update_field()`, not measured.**
  `FieldResolver` picks `AcfFieldDriver` when ACF is there, for a submission's answers too.
  ACF is not in this repository, so whether it drops a backslash as WordPress's own meta
  write did was not run, and the driver is not changed.
- **Anonymizing a submission leaves its uploaded files on disk.** The retention panel's
  "Anonymize" removes the answers, which are what name the files, and not the files. Nothing can
  find them afterwards. It is spec 105's fifth slice, not started; deleting a submission for good
  from the trash does remove its files.
- **Installing a release from the admin has not been run on a shared host.** It is built and
  tested over real folders and on a WordPress of its own (spec 107). The first installation on
  the kind of host it is for has not been made, and a site reaches the release that carries it
  by hand, by unpacking the shared-host package over the old one.
- **A dialog block whose script did not run shows a button that does nothing.** With scripting
  off a `corex/modal` or `corex/drawer` is in the page. With scripting on and the block's view
  script blocked or not loaded, its trigger is shown and what it holds is out of reach. Three
  ways to cover it are weighed in DECISIONS #316 and none was built.
- **No screen reader was listened to on the dialog blocks.** What one is told is read from
  where focus is and from the dialog's name, in Playwright's reading and in Chromium's own
  accessibility tree. Firefox's and WebKit's own trees were not read.
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
