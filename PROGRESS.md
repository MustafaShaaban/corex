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
- **The activity record.** `ActivityService::record()` cuts an actor's label, a target's
  label and a target's id to `ActivityEvent::LONGEST_TEXT`, 191 characters, which is also
  what `ActivityTable` makes the columns (DECISIONS #340; on `main` after v0.45.2, not
  released). A new caller may hand over a name of any length. `kind` (100) and
  `target_type` (64) are not cut: hand them text written in code.
- **Specs 105 and 108 carry readings that are the owner's to overrule**, under each spec's
  Assumptions; so does spec 103 (opening a submission marks it read) and spec 104.

No dependency pull request is open. **Node 22.22 is the floor** for building (DECISIONS #272); an
older Node 22 prints a warning and builds.

## Recently landed

**After v0.45.2, not released, 2026-10-13.** CI was run on Ubuntu 26.04 before GitHub moves
`ubuntu-latest` to it (2026-10-19 to 2026-11-19; a run lands on either image until then), and
the one job that failed there is changed (DECISIONS #364). Draft pull request #406, not to be
merged, named `ubuntu-26.04` on every job that runs on a pull request. Seven passed as they
are. The browser job stopped on `E: Unable to locate package php8.3-fpm`: the new archive has
`php8.5-fpm` only. Once setup-php has set up PHP 8.3 a PHP-FPM for it is running on both
images, so the job asks apt for the package only where no `php-fpm8.3` is on the path.
With that, the browser job gave 481 passed, 7 skipped and 6 passed on each image. `runs-on`
stays `ubuntu-latest`, for the reason WordPress stays at `latest` (#221). **Not tried on the
new image: `docs.yml`, which runs on a push to `main` only, and `azure-pipelines.yml`**,
which selects PHP 8.3 with a command that ends `|| true` and would build with the image's
PHP 8.5 where there is no PHP 8.3 to select (read in the file, not run).

**After v0.45.2, not released, 2026-10-13.** A CI job that setup-php left without WP-CLI
installs it and carries on (DECISIONS #354). Four jobs in three runs that day stopped on `wp: command
not found` before any test ran: setup-php asks for WP-CLI once, with no retry, and a tool it
could not fetch is a line in its log, so its step ended green. `ensure-wp-cli.sh`, beside the
provisioning action, now asks `wp` for its version after setup-php and, where there is none,
installs WP-CLI 2.12.0 from its release with the checksum checked. **A run that took the
fallback is green and carries a warning, "WP-CLI was installed by the fallback"**: count
those before deciding the fallback is not needed. The release and its checksum are written
in `action.yml` and nothing updates them; only a run with `wp-cli` taken out of setup-php's
`tools` proves a new pair. `tests/provision-wordpress-wp-cli.test.js` runs the script in
each state a runner can be in, with a stand-in for `curl`.

**After v0.45.2, not released, 2026-10-13: a security fix, and the reason for the next
release.** A form's file field is answered by the file the request carried, and by nothing
in its text (spec 081, FR-002a, Phase H; DECISIONS #353). What a request's body said under
a file field's name was kept as the answer when no file arrived, and an answer to a file
field is read as the number of a stored file. `FileFieldAnswers` is the one place that
rule is written, asked by `FormSubmissionService` and by the flow pipeline's
`ValidationStage`. The attachment store asks `is_uploaded_file()` before it reads a path.
A submission's files are removed with it by field, and not while another submission names
them. A site's listener asks `Corex\Security\Upload\FormUploads::cameThrough()`. The Data
import had the same mistake and reads the file part only.
**Owed:** the Muva and Perego sessions are told what to check in what they store, and
MUVA's `SendBriefEmails` changes to the call when it takes the release. Leads, not
built: a flow offers a file question and has no way to take a file; an answer already
stored that names somebody else's file is still shown by the inbox under both submissions;
the careers form unslashes its upload's temporary path, which on a Windows host is a path
with its backslashes removed (read, not run); the flow pipeline validates before it
protects, and validation passes on the schema's answers only, so the honeypot and the
challenge token may never reach the stage that reads them (read, not run).

**After v0.45.2, not released, 2026-10-13.** A stored submission of a form defined in code
raises the New submission notification (DECISIONS #352; the owner's report, relayed by the
Muva session). `FormSubmissionService` now dispatches `SubmissionProcessedEvent` once the
form's listeners have run, as the Forms & Flows path always did, and the producer names the
form through `FlowFilterOptions::nameOf()` and links to the inbox by `slug:<slug>`. The key
is `submission.new:<form slug>`, unchanged. `SendEmailListener` tells the event how its
email went, so a form that uses it gets the failed-email notification too; a site with a
listener of its own for the email calls `FormSubmittedEvent::notifiedAs()` to get it.
**A test that registers a form registers it with the container's `FormRegistry` and
leaves the container's binding alone:** `CodeFormDiscoveryTest`'s helper used to bind a new
registry, and every later test's form was unknown to the route. **A form defined in code
sends through `wp_mail()` whatever `MailDelivery` answers:** a test that submits one with
an email listener answers `pre_wp_mail` itself.

**After v0.45.2, not released, 2026-10-13.** No string of the `corex` domain is read before
`init` (DECISIONS #351). Since v0.45.1 every request made what answers a submission's file
link, and with it the mail stack and the email layout, which read the theme's settings and
the site's direction as it was made: WordPress logged "Translation loading for the `corex`
domain was triggered too early" on every request, and the contact notification was drawn
left to right on a right-to-left site. The link's handler is now made when the link is
followed, and `Corex\Email\Template\Layout` asks for its brand when it draws.
`tests/Integration/Foundation/BootTranslationsTest.php` loads WordPress in a process of
its own and names the file and line of anything read early. **Before making an object in
a provider's `boot()` to hook one action, hook the action with a closure that makes it**:
the object brings whatever its constructor needs, on every request. Three leads, not
built: `ConfigServiceProvider::boot()` still makes every admin screen, job handler and
`admin-post.php` controller on every request (195 shared entries by the end of
`plugins_loaded`, counted, not timed); an email's direction follows the language of
whoever is signed in and not the site's (measured for `Layout`, read for
`SiteEmailBrand`); and on a site without pretty permalinks the Submissions inbox said
"The submissions could not be loaded" (seen once in a browser, not traced).

**After v0.45.2, not released, 2026-10-13.** `GET corex/v1/access/requests`, the list of
open access requests, has the Access & Abilities screen's own check (`manage_options`),
as the decision route has; asking for access keeps its own (spec 079, FR-011a; DECISIONS
#346). `tests/Integration/Access/AccessRequestsReadTest.php` holds it through the REST
server, for each role. A lead, not built: a role given the `corex_manage_access` ability
and not `manage_options` is addressed by the notification of a new request and cannot open
the screen that decides it (read in the code, not walked in a browser); the list, the
decision route and the screen's menu entry would move to the ability together.

**After v0.45.2, not released, 2026-10-13.** A person who asks twice for the same access has
one request (spec 079, FR-008a; DECISIONS #343). `POST corex/v1/access/requests` stored a
request, an audit entry and a notification for every repeat; the form on a denied screen
did not. The question is asked where the request is stored
(`AccessRequestStore::createUnlessPending()`, under a lock named for the requester), so the
route, the form and two asks at the same moment get one answer.

**After v0.45.2, not released, 2026-10-13.** A member who forgot their password resets it on
the account page (spec 068, FR-158a to FR-158c; DECISIONS #341; the owner's decision on
three questions). The reset email is still WordPress's; its link is to the page the reset
was asked from, where the block shows a new-password form. Before, the link was to
`wp-login.php`, the member ended in `wp-admin`, and where the login is moved the email
carried the moved address. `addons/corex-profile/src/Reset/` holds it;
`tests/e2e/account-reset.spec.js` walks it in a browser, with a fixture that keeps the
email and sends nothing.

**After v0.45.2, not released, 2026-10-13.** A request the Access REST routes cannot read is
answered 400 and names the parameter (spec 079, FR-002a; DECISIONS #350). An empty reason,
an ability, area, effect or role CoreX does not have, or a confirmation that could not be
read, was a 500 and a fatal error in the PHP log: the service threw and the route caught
nothing. The service now throws `Corex\Access\InvalidAccessInput` for what a caller sent,
and `AccessController` answers that class alone; a refusal for how things stand is still
200 and `blocked`. `tests/Integration/Access/AccessRouteRefusalTest.php` holds it through
WordPress's REST server.
**After v0.45.2, not released, 2026-10-13.** The page that shows a new preview link (Coming
soon, spec 101) holds it in a field with a **Copy link** button, the owner's ask relayed from
the first client site (FR-011a, tasks T069 to T075, DECISIONS #327). The button is shown by a
script and copies without the Clipboard API where a site is not served over HTTPS; with
scripts off the link is copied from the field by hand. `StandalonePage::copyField()` is the
first standalone page part that carries a script.

**After v0.45.2, not released, 2026-10-13.** The Corex theme has a side gutter: its root
padding, from spacing preset 40, 16 pixels on a page (spec 068, FR-157a and FR-157b;
DECISIONS #344). A block set to full width or to wide in a page's content is now that wide
on a desktop too, and a `corex/form` is one column in a window 600 pixels wide or less.
`tests/e2e/theme-gutter.spec.js` reads every template and every registered pattern at 375
and 320 pixels in both directions: a template or a pattern added later is held by it, and
a template that prints a post's content has to print it full width
(`tests/Unit/Theme/ThemePageCoverageTest.php`). Client themes are themes of their own and
are not moved.

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
- **A failed email sent through `Corex\Mail\Mailer` is reported to the notification centre
  by nothing, unless whoever sent it says so.** Email Studio's failure notification hears
  only of what Email Studio's own service sent. A form's own email listener that sends
  while the request runs reports it with `FormSubmittedEvent::notifiedAs()`. One that
  sends afterwards cannot: Muva's two forms queue their emails and send them from WP-Cron
  (its session's reading), and CoreX's own mail queue answers `queued`. Nothing takes a
  submission id and a result after the event, so those failures raise no notification.
  Bookings and Careers send through the same mailer and were not looked at.
  DECISIONS #352.
- **Naming a form in a notification reads the whole form catalog, which counts every
  form's submissions.** Measured: about 4 ms with a handful of submissions, 62 to 73 ms with
  10,000, once per stored submission on a site with no persistent object cache
  (DECISIONS #352). The count is for the screens that show it; the catalog should count
  only when asked.
- **A site that already holds the same access request twice keeps both.** Each is listed in
  the queue and decided on its own. Nothing merges or removes them, and whether a client
  site holds one was not read.
- **One open request per person rests on a MySQL named lock, on one database server.** The
  lock is asked for on the connection WordPress is using (DECISIONS #343). On a site whose
  database plugin sends reads to replicas, two requests could ask two servers for it and
  both be told yes; two asks at the same moment could then both be stored, as they were
  before. Read, not run: no such site was at hand.
- **No test in CI posts two access requests at once.** What keeps the lock in place is an
  integration test that reads, when the row is written, that the lock is held, and
  afterwards that it was let go. The two-process run (36 pairs, one request each) was made
  by hand on 2026-10-13.

- **A client site's own notification email does not link to the submission yet.** CoreX's
  email names a form's file and links to the submission in the inbox (DECISIONS #319,
  v0.45.2). A site that writes its own emails, as MUVA does, is handed what it needs
  (`FormSubmittedEvent::submissionId()`, `Corex\Mail\SubmissionAddress`) and has to use it
  in its own listener: that is the site's change, not the framework's.
- **A change to a role nobody has is taken by the Access routes.** A role key is checked
  for its form only. Measured through the service on 2026-10-13: a change for
  `probe_no_such_role` was previewed as allowed, answered `completed`, and its grant was
  stored (DECISIONS #350). Nobody holds such a role, so nothing is granted to anybody; the
  row stays and the audit log says a role was changed.
- **A `role` in the body or the query of an Access role route is read before the one in
  the address.** `POST corex/v1/access/roles/editor/apply` with `"role": "author"` in its
  body changes `author`. It is how WordPress orders a request's parameters, and it needs
  `manage_options` either way. Read from the 400 a body's `role` of `!!!` is answered; a
  change landing on another role was not run.
- **`changes` that is not a map is read as no changes** by the Access role routes, and
  answered 200. Read, not run: applying no changes with a confirmation for no changes
  would write "role changed" to the audit log.
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
- **No kept test fills the registration form from a saved address.** The form's
  username is marked `username` since DECISIONS #347, and Chromium 153 then writes a
  saved address into Email and into Username. That a registration filled so is made, with
  a `+` in the address or with an address over 60 characters, was measured on 2026-10-13
  over the DevTools protocol in a Chromium with a profile of its own. What holds it since
  is the account rule's unit and integration tests and the two tests of the attribute. No
  browser test registers an account: CI's site has registration closed, and a registration
  writes two emails.
- **After a registration the browser does not offer to save the new password.** Measured
  in Chromium 153 on 2026-10-13, before and after DECISIONS #342: its password manager
  holds the address (or the username) and the new password while the form is filled in,
  and decides nothing when "Your account has been created" is written, because the filled
  form stays on the page. After a sign-in the page is loaded again and it offers. Not
  changed.
- **Chromium does not know `nickname`.** The profile's display name has that value
  (DECISIONS #342 has why it is not `name`). Chromium fills nothing into the field, as
  before, and its developer tools list the value as one it does not recognise;
  WordPress's own profile screen has the same on its nickname field. Not changed.
- **The account block's `autocomplete` values were measured in Chromium only.** Not in
  Firefox or Safari, not with a password manager that is not Chromium's own, and not in a
  Chrome signed in to an account, where a made-up password would be offered at
  registration. On the form a reset link leads to, Chromium reads a new password and its
  confirmation and no username (the login is in a hidden field), so which saved password
  it would offer to update was not seen.
- **A page that holds the account block twice fills one block's lists.** Read from
  `account.js`, not run: the session list and the recent-activity list are found with
  `document.querySelector`, so a second block's lists stay empty for a signed-in person.
  The forms of every block are answered, and since DECISIONS #337 no id is on such a page
  twice.
- **With scripts off the account block's forms do not work. Whether they should is the
  owner's decision, asked on 2026-10-13.** The block says so now, in a note above its
  forms that only a browser running no scripts shows, and that names no other place to
  sign in (DECISIONS #339, spec 068 FR-161d). A form sent anyway still goes to the page it
  is on, with what was typed in it, and is not answered. Making the forms work without
  scripts is a second way in for sign-in, registration and the rest, with a spec of its
  own; #339 weighs it and recommends not now.
- **A browser that runs scripts and did not get `account.js` is told nothing.** With the
  file blocked, or the request for it failed, a form goes to the page as it does with
  scripts off, and the note for scripts being off is not shown. Read, not run. Only forms
  that work without scripts close it (DECISIONS #339).
- **The account block's "Recent activity" lists two things, because two are recorded.** A
  row is a sentence and a date now, and a session is named by its browser and system
  (DECISIONS #348, spec 068 FR-161g). What the add-on records is a saved profile and
  signing out the other sessions. Signing in, a changed or reset password, a new account
  and **Sign out everywhere** write no activity event (read from
  `ProfileServiceProvider`), so the list is silent about the things a person most wants to
  see there. Recording them is a change to what the product keeps about a person, with a
  retention period, and is the owner's to ask for.
- **What a person did elsewhere in the product is not in the account block's list.** The
  add-on has a sentence for its own two kinds and lists no other (#348). A member who
  asked for access is the actor of `access.request.created` and is not shown it there;
  the Access add-on would have to word it. No way for another module to add a sentence
  was built: nothing asks for one yet.
- **A session's IP address is not shown in the account block. My reading, the owner's to
  overrule.** WordPress records the address the request came from, which behind a proxy
  or a CDN is the proxy's unless the host puts the visitor's back; an address means little
  to most people, and it is personal data on a page read over a shoulder (#348). It is
  still in the route's answer. A session's name is read from a few tokens of its user
  agent: Brave and Vivaldi read as Chrome, an iPad asking for desktop pages as a Mac.
- **On an Arabic site the account block's rows are English sentences in a right-to-left
  line.** Measured on 2026-10-13 with WordPress's Arabic pack: the month and the am/pm
  mark of a date are Arabic ("أكتوبر 13, 2026 at 1:33 م"), the sentence, "Signed in" and
  "at" are English, and an English sentence's full stop is drawn at its left. The layout
  is right; the words wait for the Arabic catalogue `PROJECT-STATUS.md` says is owed.
- **A list of the account block that does not load after a press is seen and not heard.**
  The sentence in the list's place ("Your sessions could not be loaded. Load this page
  again to see them.") is a paragraph and not a live region, so that it is not announced
  on every visit as the page opens (DECISIONS #345, spec 068 FR-161f). The answer to the
  press is announced; the list failing to refresh above it is not. There is no button to
  try again: the sentence says to load the page.
- **Whether WebKit still calls the account block's two lists lists was not measured.**
  They are drawn without bullets, and Safari with VoiceOver is known to stop announcing a
  list whose bullets are taken away; `role="list"` on the two `<ul>`s is the usual remedy
  and is what was added (#348). Chromium's tree was not the question, and no screen reader
  was listened to.
- **No screen reader was listened to on the account block.** That an answer is announced,
  and once, is read from where it is written (one polite status, in the page before its
  text changes; three of them in Chromium's accessibility tree on the signed-out page,
  none ignored) and from focus not being moved. Firefox's and WebKit's trees were not
  read, and the block's browser tests run in Chromium only.
- **Seven of the theme's eight spacing tokens are not the size theme.json gives them.**
  Measured on 2026-10-13 on WordPress 7.1.3: `--wp--preset--spacing--20` to `--80` are on
  the page as WordPress's own sizes (0.44, 0.67, 1, 1.5, 2.25, 3.38 and 5.06rem) and not
  as the theme's (0.5, 1, 1.5, 2, 3, 4 and 6rem); only `--10` is the theme's. theme.json is
  version 3, where a theme's sizes for the slugs WordPress defines are not used until it
  sets `settings.spacing.defaultSpacingSizes` to false. By the same rule the font sizes
  `small` and `medium` are WordPress's (13px, and 14 to 20px), and the size `2xl` is on
  the page as `--wp--preset--font-size--2-xl` while the CTA block's stylesheet asks for
  `--wp--preset--font-size--2xl`, which is nothing. So seven of the theme's spaces, and the
  blocks' that use them, are an eighth to a third smaller than their tokens say, and the
  gutter of DECISIONS #344 is 16 pixels where theme.json reads 24. Putting it right moves every space on every
  page of a site that runs the theme: the owner's to ask for, with the pages looked at
  before and after. Not changed.
- **A word longer than the window does not break in a heading.** Measured the same day at
  375 pixels: a search for a word of 24 letters leaves the search title 97 pixels past the
  window's edge. A post's title breaks such a word (WordPress's stylesheet for that block
  sets `word-break`), and its stylesheet for the search and archive title sets nothing of
  the kind. A heading in content was not measured. Not changed.
- **A form's message is one narrow column wide on a desktop.** Measured the same day at
  1280 pixels with the line empty: `.corex-form__status` is placed in the grid's second
  column, 38 pixels wide, beside the button. What a message looks like there was not
  looked at. In a window 600 pixels wide or less it has the row to itself since DECISIONS
  #344. Not changed.
- **A project's address is "Page not found" until the permalink rules are saved again.**
  Seen on 2026-10-13 in the browser job of pull request #388: on a newly provisioned site
  `/projects/<a project>/` served the 404 template. That site's rules were saved before
  its add-ons were switched on. Reproduced on a local install by saving the rules with no
  plugin loaded: 404, and found again after WordPress's Permalinks screen was opened. Read:
  `PortfolioServiceProvider` registers `corex_project` with the address `projects`, and
  nothing under `plugins/`, `addons/` or `packages/` calls `flush_rewrite_rules()`. Not
  measured: whether switching the add-on on at CoreX → Add-ons, or applying the portfolio
  kit, leaves a site in that state, and whether another add-on's post type has the same.
  `tests/e2e/theme-gutter.spec.js` opens the Permalinks screen before it reads a project,
  and says why. Not changed.
- **A theme made by `wp corex make:site` starts with no gutter.** Read from
  `packages/cli/stubs/site/theme-json.stub`, not run: it sets
  `useRootPaddingAwareAlignments` and no root padding, as the Corex theme did, and its
  index and front-page templates have no constrained block for a padding to reach. Its
  Coming soon template, and CoreX's own, have one and rely on the theme's root padding. A client theme's layout is the client's;
  whether the starting point should carry a gutter is the owner's to decide.
- **An account registered before DECISIONS #335 with no username is still named by its
  email address.** A new one is named by the part before the @. The old ones keep the
  address as display name and nickname, and in the author address, and a comment written
  under it keeps it whatever the account is renamed to: WordPress copies the name into the
  comment (measured 2026-10-13). Putting them right rewrites a site's accounts and comments
  and is the owner's to ask for. The add-on is off in Muva's decision record and on the
  Perego and Mizzey development installs; their live hosts were not read.
- **A record that cannot be written still fails the request that did the work.** Every
  caller of `ActivityService::record()` but the release installer lets the repository's
  exception out, after its work is done, so the person is answered 500 for something that
  happened. The one cause measured, a name longer than the table holds, is gone
  (DECISIONS #340). What is left was not seen: a database refusing a row it has room for.
  #340 holds a recommendation and no code: a member's own request (a profile saved,
  sessions ended) should not be failed by its record; an administrator's audited action
  should have its record written with the work, in one transaction, so that neither can be
  without the other, which wants a spec. The owner's to decide.
- **`POST corex/v1/access/requests` stores a second request for the same ability from the
  same person.** Measured on 2026-10-13 while measuring #340: two posts, two requests.
  `AccessService::pendingRequestFor()` is asked by the form in the admin and not by the
  route. Not looked into further, not changed.
- **The Profile form's Display name field has no `maxlength`.** A name WordPress would not
  keep is refused by the server with a reason (DECISIONS #340). The field was left alone
  because two open pull requests rewrite the block's markup (#381, #383), and because 250
  is the number only for a name with no `&` or `<` in it.
- **A login made for a long address can still be refused by something else.** An address
  of 61 to 100 characters with no username gets a login made from its first part
  (DECISIONS #338). A listener on `pre_user_login` or `illegal_user_logins`, or a second
  registration at the same moment for the same login, ends in WordPress's own message
  about a username the person did not type. Read, not run.
- **A new member who chose a password is emailed a link to set one.** The account gateway
  sends WordPress's own new-user email, which reads "To set your password, visit the
  following address:". Read on 2026-10-13 from what was handed to `wp_mail()`; what
  following the link does to the chosen password was not run. Not changed. Its link is
  WordPress's own, the one the reset email carried until DECISIONS #341; what this email
  says waits on the owner, and the two emails are meant to end up alike.
- **The reset email is WordPress's plain text, and a page with no mark gets WordPress's
  link.** Sending it in the standard design was put to the owner and left for the decision
  on the new member's email (DECISIONS #341). The link comes back to the account page only
  when the forgot-password form carried the page's mark: a page a cache stored before the
  release does not until it is stored again, and neither does the block in a template that
  is not one page or post.
- **A signed-in member can read the address login protection gives the login.** The
  account block's Sign out link and the admin bar's are WordPress's logout address, and a
  signed-in subscriber is served `/wp-admin/`. Measured on 2026-10-13. The owner accepted
  it (DECISIONS #341); changing it is a sign-out of the add-on's own and no admin bar for
  members, which is not specified.
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
