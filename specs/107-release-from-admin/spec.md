# Feature Specification: A release is installed from the admin, on a host with no command line

**Feature Branch**: `spec/107-release-from-admin`

**Created**: 2026-10-08

**Status**: Decided and planned. Four questions the first drafts left to the owner were answered
by him on 2026-10-08 with "decide the best for me regarding the 4 questions and continue"; the
decisions made on that are under "Decided for the owner". [plan.md](./plan.md) and
[tasks.md](./tasks.md) follow them.

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
- On the production site `DISALLOW_FILE_MODS` is not set. Whether WP-Cron's loopback request
  works there is unverified.
- 300,000 files and 100GB for the account; the previous release kept for going back counts
  against the first.
- Sessions were refused file removal and DNS changes on this host, and the owner did those by
  hand. One upload and one button, by him, fits. Work in the file manager does not.

A bash script written for this site before the move does A's steps by hand order
(`sites/muva/deploy/server/apply-release.sh` in that client's repository). It is the reference
for the order of steps and for what is refused. It was not read for this draft.

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
- The host forbids changing files from PHP, or the site's directories are not writable: the
  screen says so when it is opened, not after an upload.
- There is not enough disk space, or not enough of the host's file allowance, for the package,
  its unpacked copy and the previous release together: said before anything is replaced.
- Two people install at once: the second is told an installation is in progress.
- The package holds a file the site's owner changed on the host by hand: the package wins, and
  the screen said, before the owner confirmed, that changes made on the host are replaced.
- A release that removes a plugin or an add-on the site has active.
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
- **FR-023**: Only one installation may run at a time.
- **FR-024**: While files are being replaced a visitor MUST get the site as it was or a
  maintenance answer.

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
  and try again, not as success or as failure.
- **FR-032**: A failed change MUST be named, MUST stop the release being reported as installed,
  and MUST leave the previous release available.
- **FR-033**: On a network, every site's database MUST be brought to the release.

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
