# Feature Specification: A release is installed from the admin, on a host with no command line

**Feature Branch**: `spec/107-release-from-admin`

**Created**: 2026-10-08

**Status**: Draft. Not planned and not built. Three questions under "Open questions for the owner"
change its scope and are his to answer before a plan is written.

**Input**: The owner's instruction of 2026-10-08, "continue with the muva and peregos asked", and
the outline he agreed the same day with the session moving the first client site to shared
hosting. That outline, as it was recorded when it was relayed: (A) install or update a release
from the admin: upload the package, verify it, swap it in, migrate in PHP, keep the previous
release; (B) data kinds declared "built in development" or "born in production", so a push never
writes submissions or leads; (C) pull production data down, optionally anonymised; no general
backup plugin; in that order. **This spec is A only.** B and C get their own specs. The outline's
exact wording was asked for again while this was written and had not arrived; where this spec
says more than the recorded outline, it says so under Assumptions.

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
  is found later, by its symptoms. `wp corex migrate`, which does report, needs a shell.
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
- **FR-021**: Installing MUST NOT change uploads, the database's content, the site's
  configuration file, or anything the package does not hold.
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
  requests if they must, showing progress.
- **FR-032**: A failed change MUST be named, MUST stop the release being reported as installed,
  and MUST leave the previous release available.
- **FR-033**: On a network, every site's database MUST be brought to the release.

**Going back**

- **FR-040**: The release that was replaced MUST be kept, and MUST be restorable from the admin
  in one confirmed action.
- **FR-041**: Before going back, the owner MUST be told which database changes are reversed and
  which are not.
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
  not a release, and the outline rules out a general backup tool.

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

## Open questions for the owner

Each changes what is built. They are not answered by the recorded outline.

1. **Does this have to work on a site that has no CoreX yet?** "Install" in the outline can mean
   the first installation on a fresh WordPress. CoreX cannot offer a screen where CoreX is not
   running, so that needs a small installer of its own, added through WordPress's plugin
   uploader. This spec is written for a site that already runs CoreX and reads "install" as
   "install a release". If the first installation is wanted, it is a sixth story and a separate
   small plugin.
2. **How much of a failed release's database changes should going back undo?** Files can always
   be put back. A change that added a column can be reversed; one that changed or removed data
   cannot be, without a copy of the data, and the outline rules out a general backup. This spec
   is written to reverse what declares itself reversible and to say plainly what is not
   (FR-041). The alternative is to copy the affected tables before each release, which is a
   bounded backup the outline may or may not have meant to exclude.
3. **Is the client's own code part of the package, always?** A package built for a client
   (`build:dist -- --client=<name>`) holds the framework and that site's plugin and theme
   together, and this spec replaces them together (FR-020). If a site's code should be releasable on its own, between framework releases, that
   is a second kind of package.

## Assumptions

Written without asking; each is the owner's to overrule.

- **The package is the one `build:dist` already makes.** No new format. What is added is that
  the host reads the description the package already carries, and the checks `verify:dist` runs
  where the package was built are run again where it lands.
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
- **The way back that does not need the admin** is a file the owner renames or a link he was
  given, not a command.

## Out of Scope

- Data kinds and a push that never writes submissions or leads (the outline's B).
- Pulling production data down, anonymised or not (the outline's C).
- Publishing releases anywhere, and any change to the in-admin update notifier.
- Hosts that have a command line or a deployment pipeline: they keep what they have.
- Building the package on the host.
