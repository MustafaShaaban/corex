# Feature Specification: A release is installed from the admin, on a host with no command line

**Feature Branch**: `spec/107-release-from-admin`

**Created**: 2026-10-08

**Status**: Decided and planned. Four questions the first drafts left to the owner were answered
by him on 2026-10-08 with "decide the best for me regarding the 4 questions and continue"; the
decisions made on that are under "Decided for the owner". [plan.md](./plan.md) and
[tasks.md](./tasks.md) follow them. Five more, raised by him on 2026-10-11 after the first
client site went public, are answered under "Clarifications".

**Built**: all six slices, 2026-10-08 to 2026-10-11 (DECISIONS #282, #293, #294, #296, #300,
#301, #304, #307, #310). Nothing of it has run on the shared host it was written for: the
guide says so where an operator reads it.

**Amended 2026-10-18** (DECISIONS #369): the first installation on a shared host was held in
WordPress's maintenance answer until its ten minutes ran out. FR-024a, FR-024b and the last
sentence of FR-025 are what was added; the clarification of that date says why. The second
installation there stopped at the swap with nothing moved, and the screen could neither give it
up nor say what was left on the host (DECISIONS #370): FR-022a and FR-022b were added.

**Input**: The owner's instruction of 2026-10-08, "continue with the muva and peregos asked", and
the outline that reached this spec from the session moving the first client site to shared
hosting. That session sent the outline in full after the first draft was written; this draft
follows it.

**What the owner asked for, close to his words** (as that session reports them): CoreX should
take the database backup and give "the full backup and restore behaviour", better than backup
plugins, because it is ours and understands the framework; it should make migrating a database
easier; if production has new form submissions a push must not remove them; WordPress posts and
post types migrate too.

**What was recommended to him in reply, and he accepted with "ok let's do it"**, three parts in
this order, each useful alone:

- **A. Install or update a release from the admin**: upload the shared-host package, check it
  against its own description, maintenance on, swap the release-owned folders, run the database
  migrations from the admin, keep the previous release to go back to. Never overwrite
  `wp-config.php`, `.htaccess` or uploads.
- **B. Data kinds and a safe push**: each kind of data flows one way. Built in development
  (pages, templates, patterns, form definitions, settings) is written by a push; born in
  production (submissions, leads, the mail log, subscribers, accounts) never is.
- **C. Pull production data down**, personal data optionally anonymised.

**This spec is A only.** B and C get their own specs.

**One thing in that reply was not his decision when it was made.** He asked for backup and
restore "better than backup plugins". The reply recommended the opposite for disaster recovery:
leave it to a backup plugin or the host, and have CoreX snapshot only its own tables before it
changes them. He did not object and was not asked to agree to it in those words. It is the
fourth of the decisions below.

## Why this spec exists

A client site now runs on shared hosting with no SSH and no command line. Read at `9b088117` and
from what that move reported:

- **A release reaches the host as a zip unpacked by hand.** The shared-host package is built from
  the client's repository (`npm run build:dist`), zipped, uploaded through the host's file
  manager, and unpacked over the running site. Nothing checks the zip before it replaces the
  site, nothing keeps what it replaced, and a half-finished unpack is a half-updated site.
- **A release's database changes happen when nobody is looking.** CoreX brings a site's schema
  up to date by itself, on the first admin page or scheduled run after the files change
  (`SchemaSelfHeal`), so a release that adds a table does complete on a host with no command
  line. But it is not a step of the release: until that first admin page or cron run, visitors
  are served new code on the old schema; nobody is shown what changed; and a change that fails
  is found later, by its symptoms. `wp corex migrate`, which does report, needs a shell. The
  hosting session reported this differently, as a release that "cannot be finished on the
  server". That is the reading of the command; the code says the schema follows on its own. It
  has not been watched happening on that host.
- **The in-admin updater CoreX has is not this.** It tells WordPress that one plugin,
  `corex-core`, has a newer version at a URL. A release is four plugins, thirteen add-ons, a
  theme, the command-line package and one shared `vendor/`, all at one version; replacing one of
  them leaves a site whose parts disagree. Nothing is published for it to find, and its endpoint
  has no field on the Settings screen. The deployment guide gives a site with a repository
  behind it the other route, merging the release, and keeps the in-admin updater for "a deployed
  WordPress with no repository behind it". A host that took a release on its own would also be
  ahead of its repository, and the next package built from that repository would take it back.
- **The package already describes itself.** It carries `corex-release.json`: the version, when it
  was built, the client, and the plugins and themes it holds. `npm run verify:dist` checks a
  package on the machine that built it. Nothing on the host reads either.

The gap is not "an updater". It is that the last step of a release, on the host, is done by hand
with no check and no way back.

## The host this is written for

One shared hosting account serves two client sites. From the hosting session, measured or read
there unless it says otherwise:

- No SSH, no terminal, no Git, and that is the host's policy on every shared plan. Cron exists,
  and the owner's rule is no new cron jobs: nothing here may depend on one.
- A 29.7MB zip of 7,340 entries was uploaded through the host's file manager and unpacked there.
  PHP's upload, memory and time limits were not read, so an upload through the admin cannot
  assume them.
- A burst of requests is answered with an HTML challenge page, status 200, for a short while.
  Anything that makes many requests in a row has to pace itself and read an unexpected HTML body
  as "wait and retry", not as an answer.
- The primary site's web root also holds the second site's folder. Anything that replaces or
  clears folders must leave what it does not own alone.
- `DISALLOW_FILE_MODS` is set there. The first draft said it was not, from the site as it stood
  before the move; the configuration the client's repository writes for this host
  (`wp-config.ultahost.php`) sets it with `DISALLOW_FILE_EDIT`, read on 2026-10-11. The first
  clarification below is what follows from that. Whether WP-Cron's loopback request works there
  is unverified.
- 300,000 files and 100GB for the account; the previous release kept for going back counts
  against the first.
- Sessions were refused file removal and DNS changes on this host, and the owner did those by
  hand. One upload and one button, by him, fits. Work in the file manager does not.

A bash script written for this site before the move does A's steps by hand order
(`sites/muva/deploy/server/apply-release.sh` in that client's repository). It is the reference
for the order of steps and for what is refused. It was not read for this draft.

## Clarifications

### Session 2026-10-11

The client site went public on 2026-10-11, updated by extracting a zip over the release before
it. Extracting removes nothing: four pattern files of the earlier theme stayed, WordPress read
them when the Site Editor opened, and the editor answered with a critical error until they were
deleted by hand. The owner asked why Releases is not what that site uses, and put five questions
this spec has to answer for such a client. He asked for them to be answered here, not put back
to him, so each answer is a decision made for him, with its reason, and his to overrule.

- Q: The site's configuration sets `DISALLOW_FILE_MODS`, and the Releases screen refuses to
  install while it is set. What does an operator do? → A: He adds one line to the site's
  configuration file, once: `define( 'COREX_ALLOW_RELEASE_INSTALL', true );`.
  `DISALLOW_FILE_MODS` stays as it is. With both, WordPress's own ways of changing code (plugin
  and theme installers, updaters, editors) stay off and a release can still be installed from
  the Releases screen. Not chosen: lifting `DISALLOW_FILE_MODS` for each installation, which is
  work in the host's file manager at every release and opens every other way of changing code
  while it is lifted; and CoreX passing over the setting by itself, because the setting exists
  to say that nobody signed in to the admin puts code on the site, and only whoever owns the
  configuration file may say otherwise (FR-018).
- Q: The screen expects the framework builder's `corex-release-*.zip`. The client builds its own
  zip from the shared-host `dist` and adds FluentSMTP. Which builder does a client use, and how
  is a third-party plugin shipped with a release carried? → A: The framework's builder, with
  `--client=<slug> --zip`: no client makes the zip itself. A third-party plugin is named to the
  builder (`--with-plugin=<folder>`, as often as there are plugins); the builder copies it in
  beside the others, lists it, and measures it, so the site checks and replaces it exactly as it
  does CoreX's own. A plugin the builder was not told about is not in the release: installing
  leaves the site's copy as it is (FR-026), which is what happens to a zip the client adds a
  plugin to after the builder has measured it (FR-028).
- Q: Must a file the new release no longer ships be gone after it is installed? → A: Yes, for
  every folder the release owns, and it is so by construction: a folder is replaced whole, by a
  rename, never written into. What the release before held and this one does not is in the kept
  previous release, not on the site. This is the fault of 2026-10-11, and a test is named for
  it. It does not reach outside the folders a release owns: the site's root, WordPress itself,
  uploads, translations, and a plugin or theme no release named are left as they are (FR-027).
- Q: Today a release's schema changes are applied by the first admin page or scheduled run
  (`SchemaSelfHeal`). How do the installation and its finishing step relate to that, and what
  does going back undo? → A: There is one thing that changes the schema, and three callers of
  it: the installation's finishing step, the automatic check, and `wp corex migrate`. During an
  installation only the finishing step can run it, because WordPress's maintenance answer turns
  every other request away; afterwards the automatic check finds nothing to do. If the finishing
  step never runs (the browser was closed), the maintenance answer ends by itself after ten
  minutes and the automatic check applies the changes as it does today; the screen, when next
  opened, says the installation was not finished and finishes it (FR-035). Going back puts the
  files back and reverses no change to the database: a table or column the release added stays,
  and the copy of CoreX's tables made before the installation is kept for a person to read from,
  never put back over live tables. The release gone back to then records its own schema version
  again on its first admin page, which removes nothing (FR-045).
- Q: The first installation on a host is still by hand. What is written for it? → A: A runbook
  of its own (tasks T071): what to put on the host and in which order, the line of
  configuration, and the check that the site knows which release and whose it is, so that every
  release after it is one upload on the Releases screen. It also says how a site that has been
  updated by extracting zips moves to the screen: nothing more than that line and a package from the
  builder, because its first installation from the screen replaces each folder the release owns
  whole and so clears what extracting left behind in them.

### Session 2026-10-18

The first installation from the screen on a shared host (Perego, a LiteSpeed host with no
command line, the page kept open) ended with "The site did not answer after 6 tries". The site
answered WordPress's own maintenance page for nine minutes, and afterwards it served the release
it had before. The same host hands PHP no `Authorization` header. A copy of a site made from the
builder's own package and served with the key's header dropped, as that host drops
`Authorization`, did the same: the release was put in place, and each of the six requests that
were to finish the installation got WordPress's 503 in 31 seconds. The screen gives up after
six tries; WordPress lets requests in again only after ten minutes.

- Q: How does a request of an installation get through the maintenance answer on a host that
  does not hand its header to PHP? → A: The key goes in the address as well as in the header,
  and the maintenance file looks for it in the header as PHP was given it, in the headers as
  the web server holds them, and in the address. It writes down which, so the record of the
  next installation says how it got through (FR-024a). Both go with every request: the screen
  cannot know beforehand which one a host lets through, and a request turned away costs ten
  minutes. The address puts the key in the host's access log, which only the site's owner
  reads on a shared host; the key opens this installation's maintenance answer and its
  recovery link, which was an address already, and a later installation makes a new one. Not
  chosen: reading the request's body, which the maintenance file would have to parse before
  WordPress has loaded anything.
- Q: What does the screen do when the site answers nothing it can read while a release is
  being installed? → A: It goes on asking, a request at least every fifteen seconds, for as
  long as WordPress's maintenance answer can last, and it says on the screen that it is
  waiting and why. WordPress lets every request in by itself ten minutes after the release
  was put in place, so an installation whose key never gets through still finishes, ten
  minutes late, without anybody pressing anything (FR-025).
- Q: What else can keep the request that puts a release in place from finishing? → A: Work
  with no end inside it. That request deleted the release kept from the installation before,
  file by file, after holding visitors off and before moving the first folder: Perego's release
  folders are 9,983 files and took 11.8 seconds to delete on a development machine's disk.
  A host that ends a request after its time limit would leave visitors held off and nothing
  moved, which is what Perego's site showed. The kept release is now set aside by one rename
  and deleted when the screen next opens, a few seconds at a time (FR-024b). It is the cause
  that fits Perego best: an installation earlier the same day, from the same screen, with
  nothing kept yet, finished without trouble, so on that host the key's header did reach PHP;
  this one, with a release kept, did not finish. It was not measured on the host.
- Q: What about the installation that brings this change? → A: It is run by the release it
  replaces, which still deletes the kept release while visitors are held off: on a shared host
  its contents are deleted by hand first. On a host that drops the header it can still wait
  out WordPress's ten minutes; then **CoreX → Releases** offers **Finish the installation**,
  which ends it in one request (measured on the copy). Every installation after it gets
  through at once.
- Q: An installation stopped at the swap, and the folders did not move. Can it be given up?
  → A: Yes, from the screen, as one before the swap is. Perego's second installation that day
  stopped there: visitors were held off, nothing was moved, and the screen offered only to go
  on with it. Whether a folder moved is read off the swap's journal: there is none of this
  installation's, or every rename in it is still to be made, or one that was being made when
  the request died is found by the folders not to have been. Giving it up lets visitors in,
  removes the recovery file, the unpacked release and the table copies it made, and records it
  as cancelled. Once any folder has moved it is refused, as before: the way out of that is going
  on, or going back (FR-022a). Not chosen: putting back the release kept from the installation
  before, which the swap may already have set aside and the screen begun to delete.
- Q: What does the screen say when it finds what an installation left and no installation in
  progress? → A: It names each thing and offers to remove it. Afterwards Perego's screen said
  "No installation is in progress" while the unpacked release, a partly deleted kept release
  and the recovery file were still on the host, and the owner removed them in the host's file
  manager. What is left is: an unpacked release in `staging/`; a recovery file whose key is
  neither an installation's nor the kept release's; and a `previous/` that holds folders with no
  journal saying what they are, which cannot be gone back to. Removing them changes nothing of
  the release the site runs. It is not done unasked when the screen opens: one of them is a
  must-use plugin, and the administrator is told what it is before it goes (FR-022b).

## User Scenarios & Testing *(mandatory)*

### User Story 1 — A release is put on the site from the admin (Priority: P1)

The site's owner has a package built from the site's repository. On the site, in CoreX, he opens
Releases, gives it the package, and reads what it is: the version it holds against the version
running, when it was built, for which client, and whether it is complete. He confirms. The site
shows that it is being updated, and then that it runs the new version. He did not open the
host's file manager and did not unpack anything.

**Why this priority**: It is the step that is done by hand today on the only host that has no
other way, and every other story is a property of this one.

**Independent Test**: On a site running one release, install a package of the next release from
the admin and confirm the site reports the new version, every CoreX screen loads, and the public
site is served.

**Acceptance Scenarios**:

1. **Given** a package of a newer release than the one running, **When** it is given to the
   Releases screen, **Then** the screen states its version, its build time, its client and the
   version it would replace, before anything on the site is changed.
2. **Given** that statement, **When** the owner confirms, **Then** the site's framework files are
   those of the package, and the site reports the package's version.
3. **Given** a package larger than the host accepts in one upload, **When** it is given to the
   screen, **Then** it still arrives whole, and the owner sees how much has arrived.
4. **Given** a package the owner placed on the server himself, **When** he opens the screen,
   **Then** he can choose it without uploading it again.
5. **Given** an installation in progress, **When** a visitor asks for a page, **Then** they get
   either the site as it was or a brief maintenance answer, never a page from a half-replaced
   site.
6. **Given** somebody who may not manage the site, **When** they ask for the screen or any of
   its actions, **Then** they are refused.

---

### User Story 2 — A package that is wrong is refused before it touches the site (Priority: P1)

The owner gives the screen a zip that is not a CoreX package, or is one built for another client,
or is incomplete, or is older than what is running, or was cut short in transit. The screen says
which, and the site is exactly as it was.

**Why this priority**: The step this replaces has no check at all. A release mechanism that can
install a broken package is worse than a careful person with a file manager.

**Independent Test**: Offer each kind of wrong package and confirm each is named for what is
wrong with it and that no file of the running site changed.

**Acceptance Scenarios**:

1. **Given** a zip with no release description in it, **When** it is offered, **Then** it is
   refused as not a CoreX package.
2. **Given** a package built for a different client than the site, **When** it is offered,
   **Then** it is refused, naming both.
3. **Given** a package whose contents do not match what it says it holds, or that fails the
   checks a package is built to pass, **When** it is offered, **Then** it is refused, naming what
   is missing or wrong.
4. **Given** a package of an older release than the one running, **When** it is offered,
   **Then** the owner is told it is older, and it is installed only if he says so again.
5. **Given** a package that needs a newer PHP or WordPress than the host runs, **When** it is
   offered, **Then** it is refused, naming the requirement and what the host has.
6. **Given** any refusal, **When** the owner looks at the site, **Then** nothing about it changed.

---

### User Story 3 — The release's database changes are a step of the release (Priority: P1)

A release adds a table. After the files are in place the site brings its database to what the
release expects, as part of the installation and before visitors are served by the new code,
shows what it changed, and only then says the release is installed. If a change fails, the site
says which, and is not left claiming a version its database does not have.

**Why this priority**: The changes are made today, but by whichever admin page or scheduled run
comes first, unannounced, and a failure is silent. A release is not installed until its database
is right, and the owner has to be able to see that it is.

**Independent Test**: Install a release that carries a pending database change and confirm the
change is present afterwards and listed on the screen; make one fail and confirm the screen says
so and offers the way back.

**Acceptance Scenarios**:

1. **Given** a release with database changes pending, **When** its files are in place, **Then**
   the changes are applied and listed, and the release is reported installed after them.
2. **Given** a release with none pending, **When** it is installed, **Then** the screen says no
   database change was needed.
3. **Given** a change that fails, **When** it fails, **Then** the screen names it, the site is
   not reported as running the new release, and the owner is offered the previous release.
4. **Given** a database too large to change in one request, **When** the changes run, **Then**
   they complete across as many requests as they need, and the owner sees them progress.

---

### User Story 4 — The release before is one action away (Priority: P2)

The new release is installed and something is wrong that no check caught. The owner opens
Releases and puts the previous release back. The files are the previous release's again. The
screen says plainly what going back does not undo.

**Why this priority**: It is what turns a release from a risk the owner takes into one he can
retreat from. It follows the first three because there has to be a release to go back from.

**Independent Test**: Install a release, go back, and confirm the site reports the earlier
version and serves as it did; confirm the screen's statement about the database is true of what
happened.

**Acceptance Scenarios**:

1. **Given** a release installed from the admin, **When** the owner asks for the previous one,
   **Then** the site's framework files are the previous release's and it reports that version.
2. **Given** the release being left made database changes, **When** the owner asks to go back,
   **Then** he is told, before he confirms, which changes are reversed and which are not.
3. **Given** the admin itself does not load after a release, **When** the owner cannot reach the
   screen, **Then** there is a way to put the previous release back that does not need it, and
   the screen told him what it is before he installed.
4. **Given** several releases have been installed, **When** the owner looks, **Then** he sees
   which is running and which one he can return to, and old ones do not accumulate without limit.

---

### User Story 5 — What happened is on record (Priority: P3)

Somebody asks when the site moved to a release and who did it. The Releases screen and the
activity log answer: each installation, refusal and return, with who, when, from what and to
what.

**Why this priority**: It is cheap once the rest exists and it is how a later problem is traced
to the release that caused it.

**Independent Test**: Install, refuse and go back once each, and read all three from the record.

**Acceptance Scenarios**:

1. **Given** an installation, a refusal or a return, **When** it has happened, **Then** the
   record holds who did it, when, the versions involved, and the outcome.
2. **Given** the record, **When** it is read, **Then** it holds no secret and no content of the
   package beyond its description.

---

### Edge Cases

- The request that is replacing files is cut off by the host: the site must come back as the
  release it was, or as the new one, and say which.
- The request that puts the release in place is cut off before its first folder moves: the
  screen offers to go on with it or to give it up (FR-022a).
- The installation's record is gone and what it left is still on the host: the screen names it
  and offers to remove it (FR-022b).
- The host forbids changing files from PHP, or the site's directories are not writable: the
  screen says so when it is opened, not after an upload.
- There is not enough disk space, or not enough of the host's file allowance, for the package,
  its unpacked copy and the previous release together: said before anything is replaced.
- Two people install at once: the second is told an installation is in progress.
- The package holds a file the site's owner changed on the host by hand: the package wins, and
  the screen said, before the owner confirmed, that changes made on the host are replaced.
- A release that removes a plugin or an add-on the site has active: the folder goes out with the
  previous release, WordPress finds the plugin missing and switches it off, and the screen named
  the folder before the owner confirmed (FR-028).
- A site that has been updated by extracting zips, with files in its folders no release holds
  any more: the first installation from the screen replaces each folder whole (FR-027).
- The browser is closed after the files are swapped and before the database step: the
  maintenance answer ends by itself, the automatic check applies the changes, and the screen
  finishes and records the installation when it is next opened (FR-035).
- The key of an installation is lost with the browser tab: nothing the screen does afterwards
  needs it. It is asked for only by WordPress's maintenance answer, which stops asking after ten
  minutes, and by the link that goes back without the admin, which the owner was given as a
  file before he confirmed.
- A multisite network: one set of files serves every site, and every site's database follows.
- The uploaded package itself: where it is kept, who can read it, and when it is removed.
- Caches that hold the old code after the files changed.
- The site is in Maintenance or Coming soon mode already when the installation starts.

## Requirements *(mandatory)*

### Functional Requirements

**Receiving a package**

- **FR-001**: An administrator MUST be able to give the site a release package from the admin,
  by uploading it or by choosing one already on the server.
- **FR-002**: An upload MUST succeed for a package larger than the host's single-upload limit,
  and MUST show its progress.
- **FR-003**: A received package MUST be kept where the public cannot fetch it, and MUST be
  removed when it is installed, refused, or left unused past a stated time.

**Checking it**

- **FR-010**: Before anything on the site changes, the screen MUST state the package's version,
  build time and client, and the version it would replace.
- **FR-011**: A zip that does not describe itself as a CoreX package MUST be refused.
- **FR-012**: A package for a client other than the site's MUST be refused.
- **FR-013**: A package whose contents do not match its own description, or that fails the checks
  a package is built to pass, MUST be refused, naming what is wrong.
- **FR-014**: A package the host cannot run (PHP, WordPress) MUST be refused, naming the
  requirement and what the host has.
- **FR-015**: An older release than the one running MUST need a second, explicit confirmation.
- **FR-016**: A refusal MUST leave every file and every database row of the site as it was.
- **FR-017**: What the host cannot do (write files, hold the package and a previous release)
  MUST be stated when the screen is opened.
- **FR-018**: On a site set not to change its own files, installing MUST be refused unless the
  site's configuration file says in so many words that releases may be installed from the
  admin. The screen MUST name that setting and say what it does and does not allow. The refusal
  is made where an installation starts, not only shown on the screen.

**Installing it**

- **FR-020**: Installing MUST replace the framework and the client's own code with the package's,
  as a whole: the site MUST never serve from a mixture of two releases.
- **FR-026**: What installing replaces is what the package says it holds: the plugins and themes
  it names, and the shared code they load. A plugin or theme on the site that the package does
  not name MUST be left as it is. WordPress itself is not replaced; when the package was built
  with a different WordPress than the site runs, the screen MUST say so before the owner
  confirms.
- **FR-021**: Installing MUST NOT change uploads, the database's content, the site's
  configuration file, its `.htaccess`, or anything the package does not hold. A folder in the
  web root that the release does not own, another site's among them, MUST be left exactly as it
  is.
- **FR-022**: An installation cut off part-way MUST leave the site as one release or the other,
  and the screen MUST say which when it is next opened.
- **FR-022a**: An installation that has moved no folder of the site MUST be able to be given up
  from the screen, including one that stopped at the swap before its first folder moved. Giving
  it up MUST let visitors in, remove its recovery file and what it unpacked, and be recorded.
  One that has moved a folder MUST NOT be given up: it is gone on with, or gone back from.
- **FR-022b**: When no installation is in progress and the screen finds what one left (an
  unpacked release, a recovery file no installation or kept release holds the key of, a kept
  release with no journal), it MUST name each with where it is and offer to remove them, and
  removing them MUST change nothing of the release the site runs.
- **FR-023**: Only one installation may run at a time.
- **FR-024**: While files are being replaced a visitor MUST get the site as it was or a
  maintenance answer.
- **FR-024a**: A request of the installation MUST get through that maintenance answer on a host
  that does not hand PHP the request's headers: the installation's key MUST be accepted from the
  request's header, from the headers as the web server holds them, and from its address, and the
  installation's record MUST say which one a finishing request's key came from.
- **FR-024b**: While visitors are held off, an installation MUST do no work whose length grows
  with the size of a release, other than moving folders. What it removes of the release kept
  before is set aside and removed afterwards, a limited time per request.

- **FR-027**: After an installation, a folder the release owns MUST hold exactly what the
  package holds for it. A file the release before shipped and this one does not MUST NOT remain
  in it. Nothing outside the folders a release owns is removed.
- **FR-028**: A plugin that is not CoreX's or the site's own MUST be able to travel in a
  package, named to the builder, and is then checked and replaced as every other folder of the
  release is. Before the owner confirms, the screen MUST name every folder the running release
  holds and this package does not, because installing moves each of those out of the site.

**The database**

- **FR-030**: After the files are in place, the release's pending database changes MUST be
  applied as part of the installation, before the site is served by the new release, and listed.
  They MUST NOT be left to the first admin page or scheduled run.
- **FR-031**: Database changes MUST complete on a host with a short request time limit, across
  requests if they must, showing progress. Neither they nor any other step may depend on a
  scheduled job.
- **FR-034**: Before a release changes CoreX's own tables, a copy of those tables as they were
  MUST be kept with the previous release. This is not a backup of the site.
- **FR-025**: Every step that makes more than one request (an upload in parts, an installation
  in stages) MUST pace itself, and MUST treat an answer that is not its own as a reason to wait
  and try again, not as success or as failure. While an installation is in hand it MUST go on
  trying for as long as WordPress's maintenance answer can last, and say on the screen that it
  is waiting.
- **FR-032**: A failed change MUST be named, MUST stop the release being reported as installed,
  and MUST leave the previous release available.
- **FR-033**: On a network, every site's database MUST be brought to the release.
- **FR-035**: The installation's database step and the schema's automatic check MUST be the
  same work, done once. An installation whose finishing step did not run MUST be reported as
  unfinished when the screen is next opened, and finished from there, whether or not the
  automatic check has applied the changes in the meantime.

**Going back**

- **FR-040**: The release that was replaced MUST be kept, and MUST be restorable from the admin
  in one confirmed action.
- **FR-041**: Before going back, the owner MUST be told which database changes are reversed and
  which are not. Going back MUST NOT replace live tables with the copy kept under FR-034.
- **FR-044**: The copy of CoreX's tables kept before a release MUST be removed after a stated
  time, and the screen MUST show that it exists and when it goes.
- **FR-042**: There MUST be a documented way to restore the previous release that does not need
  the admin to load, and the screen MUST state it before an installation is confirmed.
- **FR-043**: Kept releases MUST be bounded in number, and the screen MUST show which is running
  and which can be returned to.
- **FR-045**: Going back MUST leave every table and column as the release being left made it,
  and MUST say so. A return made through the link that needs no admin MUST be found and
  recorded by the screen when it is next opened.

**Who, and the record**

- **FR-050**: Every action here MUST be limited to those who may manage the site (on a network,
  the network), MUST require a fresh confirmation of intent, and MUST be refused to anyone else.
- **FR-051**: Each installation, refusal and return MUST be recorded: who, when, from which
  version to which, and the outcome.
- **FR-052**: Every string MUST be translatable; the screen MUST meet the admin's accessibility
  and right-to-left standards.

**What it must not become**

- **FR-060**: This MUST NOT fetch a release from the internet. The package is the one the
  site's repository built; a host that pulls a release on its own moves ahead of its repository,
  which the deployment guide already forbids.
- **FR-061**: This MUST NOT back up or restore the database's content or the uploads. That is
  not a release (decision 4).
- **FR-062**: The Releases screen MUST say that it does not back the site up, and that a backup
  plugin or the host's backups are what restore a broken site.

### Key Entities

- **Release package**: the zip built from a site's repository, with its description of itself:
  version, build time, client, and what it holds.
- **Installed release**: what the site is running, as the site itself records it, with how it
  got there.
- **Previous release**: the files the last installation replaced, kept so they can be put back.
- **Release record**: an installation, a refusal or a return: who, when, versions, outcome.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A release is installed on a host with no command line without the host's file
  manager being opened.
- **SC-002**: Every one of the wrong packages in story 2 is refused, and after each refusal the
  site is byte for byte what it was.
- **SC-003**: A release that changes the database is complete, changes included and shown,
  when the screen says it is installed, with nothing typed anywhere and no admin page visited to
  trigger them.
- **SC-004**: From deciding to go back to the previous release being served takes one screen and
  under two minutes.
- **SC-005**: An installation interrupted at any point leaves a site that loads and reports
  truthfully which release it is.
- **SC-006**: A package of the size the first client site ships (its own files and media
  included) installs on that host within the host's limits.

## Decided for the owner

The first drafts ended with four questions that were his. On 2026-10-08 he answered them all at
once: "decide the best for me regarding the 4 questions and continue". These are the decisions
made on that, each with why and what it costs. Any of them is his to reopen.

1. **It works on a site that already runs CoreX. The first installation stays a step done by
   hand.** A screen cannot be offered where CoreX is not running, so a first installation needs
   a separate installer plugin, added through WordPress's own plugin uploader. A site is moved
   once and updated for years; the first move also carries the site's content, which is not a
   release. One site has made that move and the second will make it the same way.
   *Cost:* the second site's first installation is unpacked through the host's file manager, as
   the first one's was. If a third site comes, the installer plugin is a small spec of its own.

2. **Going back puts the files back and leaves the data.** Before a release changes CoreX's own
   tables a copy of those tables is kept (FR-034). Going back restores the previous release's
   files, reverses the database changes that declare how to be reversed, and says plainly which
   were not. It never puts the copied tables back over the live ones by itself, because that
   would delete whatever was written since: a lead that arrived after the release. The copy is
   kept for a stated time so a person can take from it, and is then removed.
   *Cost:* after going back from a release whose database changes cannot be reversed, the
   previous release runs on the newer tables. A release whose changes are not safe that way has
   to say so in its own notes; the screen shows that before the owner confirms.
   This is the same rule the outline gives for B: data born in production is never overwritten.

3. **A site's own code is part of the package, always.** A package is built from the site's
   repository and holds the framework at the version that repository is on, with the site's
   plugin and theme. They are replaced together, so the site never runs its own code against a
   framework it was not built and tested with. A package built with no client (the framework
   alone) is accepted on a site that has no code of its own.
   *Cost:* a one-line change to the site's theme is a whole package. The package is one upload
   and one confirmation, which is what this spec exists to make cheap.

4. **CoreX is not the site's backup.** He asked for "the full backup and restore behaviour",
   better than backup plugins. What a backup is for is the day the site or the framework is
   broken, and a backup that lives inside the framework is unreachable on that day. So disaster
   recovery stays with a backup plugin or the host, which work when CoreX does not, and CoreX
   copies only its own tables before it changes them. What he asked for that a backup plugin
   cannot do, because it does not know what the data means, is the rest of the outline: a push
   that never overwrites submissions (B) and pulling production data down (C). Those are where
   "better than backup plugins" is built, and they are the next two specs.
   *Cost:* a site still needs a backup plugin or the host's backups, and CoreX says so on the
   Releases screen instead of implying it has the site covered.

## Assumptions

Written without asking; each is the owner's to overrule.

- **The package is the one `build:dist` makes, as a zip that says more about itself.** Planning
  found that `build:dist` fills a folder and nothing zips it, and that the package's description
  holds no requirement and no measure of its contents. The builder gains the zip and those
  facts (plan D1, D2). The check the builder makes by loading the package in a PHP process of
  its own cannot be made on a host with no shell; the request that finishes an installation
  stands in for it (plan D3).
- **Nothing is downloaded.** No update source, no manifest URL, no check for new versions. The
  notifier CoreX has (spec 034) is left as it is; whether it is kept, removed or made to say only
  "a newer release exists" is not decided here.
- **One previous release is kept**, not a history, because the host that needs this has a file
  allowance shared between two sites.
- **A package is uploaded in parts**, and may instead be one the owner put on the server, because
  the largest package proven on that host is under 30MB and the framework alone is now 33MB zipped
  with its PDF fonts pruned (the changelog's measurement), before a site's own files.
- **"Those who may manage the site"** is a CoreX ability of its own, granted to administrators,
  not `manage_options` alone.
- **A visitor during an installation gets WordPress's own maintenance answer**, not CoreX's
  Maintenance mode. WordPress's is read before any plugin loads, so it holds while plugins are
  being replaced; CoreX's answers after they have loaded (plan D7).
- **No database change is reversed today.** CoreX's schema changes declare no way back, so story
  4's "which changes are reversed and which are not" reads, for now, "none are" (plan D10).
  After going back, the release returned to runs on the newer tables. Its own automatic check
  then records its schema version again; that check adds what is missing and never removes a
  table or a column, though a column whose type the newer release changed may be changed back
  to the older type.
- **A package is not signed.** What stands between a site and a package somebody made to look
  like the client's is who may reach the screen: the ability, the typed confirmation, and, on a
  site set not to change its files, the line in its configuration that only somebody with the
  host's file access can write (FR-018). A signature the site could check is not built here.
- **The way back that does not need the admin** is a link he was given before he installed,
  not a command and not work in the host's file manager, which is the kind of step the move to
  this host showed does not fit.
- **The host's limits are found out, not assumed.** PHP's upload, memory and time limits there
  were not read, so the screen reads them and sizes its uploads and its stages to them.

## Out of Scope

- Data kinds and a push that never writes submissions or leads (the outline's B).
- Pulling production data down, anonymised or not (the outline's C).
- Publishing releases anywhere, and any change to the in-admin update notifier.
- Hosts that have a command line or a deployment pipeline: they keep what they have.
- Building the package on the host.
