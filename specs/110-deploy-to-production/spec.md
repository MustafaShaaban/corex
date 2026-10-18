# Feature Specification: A release is deployed to production from the local site's admin

**Feature Branch**: `spec/110-deploy-to-production`

**Created**: 2026-10-18

**Status**: Draft, waiting on the owner. Three questions are the owner's to answer (marked
`[NEEDS CLARIFICATION]` and gathered under "Questions for the owner"). Two of the four the request
asked are answered here as decisions made for the owner, each with its reason and the owner's to overrule.
Nothing is planned or built until the owner has reviewed this spec.

**Input**: The owner's request through the Perego session, 2026-10-18, canonical copy in the Perego
repository at `sites/perego/docs/corex-requests/2026-10-18-deploy-to-production.md`. The owner had
already decided one thing before it was written: **code only; content stays edited on the live site.**
The Perego session added two points the same day, from Perego's first real installation through CoreX
→ Releases on its UltaHost host: the finishing step that did not get through maintenance, and a
maintenance page in the site's own design (story 6).

**What the owner asked, in the owner's words**: "if i finished updates here local and i want to deploy this
updates to the production … why can't i do this from the website instead of waiting for you to do this
for me after i finish my development?"

## Why this spec exists

Spec 107 made the last step of a release, on the host, one upload and one confirmation on CoreX →
Releases. Every step before it still needs an agent and a terminal on the owner's PC. Read at
`17cb68ab` (v0.46.0) and in the Perego repository at `306eca61`:

- **The package is built by hand.** An agent runs the site's own packaging script
  (`sites/perego/deploy/package.mjs`, `sites/muva/deploy/package.mjs`) or CoreX's builder
  (`npm run build:dist -- --client=<site> --with-plugin=… --zip`). The builder needs `node`, `npm`,
  `composer` and `php`, and takes minutes. Perego's script still adds its six plugins after the
  builder instead of naming them to it, so its zip is not the one the builder measured; Perego's
  release of 2026-10-18 was built Muva's way, by hand.
- **The builder packages the working tree.** Perego's script exports the checked-out commit to a
  folder outside the repository and builds there, so uncommitted work cannot slip in, and lists what
  was left out. CoreX's builder does not: it reads the checkout as it is.
- **The package does not say which commit it is.** `corex-release.json` holds the version, the build
  time, the client, the requirements and what the package holds. Nothing in it ties the site's running
  code to a commit of its repository.
- **Production's Releases routes answer only its own screen.** Every route checks the `X-WP-Nonce`
  of a signed-in browser session (`ReleaseRestGateway::answer()`). A request from another install,
  signed with an Application Password, has no such nonce and is refused as `not_signed`. Nothing on
  production can be driven from elsewhere today, and that is a property spec 107 relied on.
- **The installation's last step does not get through maintenance, even with the page open.** The
  installation is stepped by the browser that started it. In Muva's rehearsal of 2026-10-14, on
  PHP's built-in server, the screen gave up while the site was in maintenance. On 2026-10-18
  Perego's first real installation through CoreX → Releases, on the UltaHost host with the owner
  watching and the page open, stopped at the finishing step. The screen said "The installation
  did not finish — The site did not answer after 6 tries", and the public site still answered
  503 "Briefly unavailable for scheduled maintenance" at 14:22 and 14:24. The finishing request
  carries the installation's key (`X-Corex-Release-Key`), and the maintenance file lets a request
  with that key through, so why it did not get through on that host is not yet known. It is a
  defect of the released spec 107 and is fixed on its own, before this spec is planned (see
  Assumptions). Either way, the owner is left to wait out WordPress's ten minutes and press
  **Finish the installation**.
- **The maintenance page does not look like the site.** WordPress serves it before any plugin or
  theme has loaded, so nothing of CoreX or the site's theme styles it. WordPress offers one way to
  change it: when `wp-content/maintenance.php` exists, WordPress includes that file instead of its
  own page. The owner asked why the page a visitor saw during Perego's installation was not the
  site's own.
- **Spec 107 decided that CoreX is not the site's backup** (its decision 4, FR-061, FR-062). The
  owner now asks for "no backup, no install". That decision is the owner's to reopen, and this spec asks them
  to (question 1).

## The rule this spec does not bend

**Code only.** A deploy carries the site's code and nothing else. Production holds data that exists
only there: form submissions, sent emails, users, Application Passwords, and every edit made in its
admin. The local database, local uploads and local settings never leave the local install. A content
change that has to go with a release ships as a planned, tested, one-time step in the release's own
code, as Perego spec 045's `ExampleContentCleanup` drafts seeded example posts once on the server.

## User Scenarios & Testing *(mandatory)*

### User Story 1: The release is built from the local admin, and says what it is (Priority: P1)

The owner finishes work on the local site and the agent has committed it. In the local admin, under
CoreX → Releases, they choose **Deploy to production**, then **Build**. The build runs in the
background; the screen shows it progressing and can be closed and reopened. When it is done, the
screen states what was built: the CoreX version, the client, the commit, the plugins it carries, how
many files, its size, and the work in the checkout that is **not** in it because it is not committed.

**Why this priority**: It is useful alone. The zip it makes is the one production's Releases screen
already accepts, so the owner can upload it by hand on the day before stories 2 and 3 exist, and no
agent or terminal is needed for it.

**Independent Test**: On a development install with uncommitted changes, build from the admin; the
zip is produced from the commit only, production's Releases screen accepts it by hand, and the
statement lists the uncommitted files as left out.

**Acceptance Scenarios**:

1. **Given** a development install of a client site whose repository has a commit checked out,
   **When** the owner starts a build, **Then** a package is made from that commit, with the
   third-party plugins the site's repository names for its releases, without the owner typing a
   command anywhere.
2. **Given** a build in progress, **When** the owner closes the page and opens it again, **Then** the
   build is still running or finished, and the screen shows where it is.
3. **Given** a checkout with uncommitted or untracked files, **When** the build is finished, **Then**
   the statement names them as not in the package, and none of them is in it.
4. **Given** a finished build, **When** the statement is read, **Then** it holds the CoreX version,
   the client, the commit (short hash and its message), whether that commit is on the repository's
   remote, the branch, the plugins carried, the file count and the package's size.
5. **Given** a tool the build needs is missing from the PC (`git`, `node`, `npm`, `composer`, `php`),
   **When** the screen is opened, **Then** it names what is missing before the owner can start, and
   nothing is started.
6. **Given** a build that fails, **When** it stops, **Then** the screen says at which step and shows
   the builder's own message, and no half-made package is offered.
7. **Given** a build is running, **When** another one is asked for, **Then** it is refused until the
   first ends.

---

### User Story 2: Production is connected once, and can be disconnected (Priority: P1)

The owner creates an Application Password on production for an account allowed to install releases,
and gives the local screen production's address and that password. The screen checks it: that the
address is production's and answers over HTTPS, that the account may install releases there, that
production accepts a release sent from elsewhere, and which release production runs. When something
is wrong it says which, and what to change. Later the owner can disconnect it from either end.

**Why this priority**: Nothing can be sent without it, and it is where the security of the whole
feature is decided.

**Independent Test**: Connect a development install to a test production with a valid password, a
wrong one, an account without the ability, a host that drops the `Authorization` header, and an
`http://` address; each answer names the actual cause. Revoke the password on production; the local
screen reports the connection as no longer working.

**Acceptance Scenarios**:

1. **Given** a production address and a valid Application Password of an account that may install
   releases, **When** the owner connects, **Then** the screen shows production's site name, the
   release it runs, and the account it acts as.
2. **Given** a wrong password, **When** the owner connects, **Then** the screen says the password
   was refused.
3. **Given** a host that does not pass the `Authorization` header on to WordPress (Perego's UltaHost
   server does this), **When** the owner connects, **Then** the screen says that the server dropped
   the credentials before WordPress saw them, and shows the lines to add to production's
   configuration, rather than saying the password is wrong.
4. **Given** an account that may not install releases on production, **When** the owner connects,
   **Then** the screen says so and names the ability.
5. **Given** a production whose configuration does not allow releases to arrive from another install
   (see FR-024), **When** the owner connects, **Then** the screen says so and names the line.
6. **Given** an address that is not HTTPS, or whose certificate does not verify, or that is the local
   site itself, **When** the owner connects, **Then** it is refused.
7. **Given** a connection, **When** the owner disconnects locally, **Then** the stored password is
   removed from the local install; **and when** the password is revoked on production, **Then** the
   next check on the local screen says the connection no longer works.

---

### User Story 3: The release is deployed, with production backed up first (Priority: P1)

The owner has a built package and a working connection. The screen shows the statement again, now
against production: the release production runs, the one that replaces it, the commits between the
two, the folders it adds and the folders it takes out. The owner types the version to confirm, as
production's own screen asks. Then, in order and shown step by step: production is backed up; the
package is sent; production checks it as it checks any package; production installs it; production
finishes it. The owner can close the page at any point and the deploy goes on.

**Why this priority**: It is what the owner asked for.

**Independent Test**: From a development install, deploy a package of the next release to a test
production; confirm a backup of production completed before any file of it changed, production runs
the new version, the recovery link is saved on the local install, and closing the local page during
the installation did not stop it.

**Acceptance Scenarios**:

1. **Given** a built package and a connection, **When** the owner opens the deploy, **Then** before
   anything is sent the screen states the release production runs, the release that would replace it,
   the commits between them when both are known, and every folder the new release adds or takes out.
2. **Given** that statement, **When** the owner confirms by typing the version, **Then** a full
   backup of production is taken and completes before the package is sent.
3. **Given** the backup could not be taken or did not complete, **When** the deploy reaches that step,
   **Then** nothing is sent and nothing on production changes, and the screen says why.
4. **Given** the backup completed, **When** the package is sent, **Then** it arrives whole, in parts
   sized to production's limits, and the screen shows how much has arrived.
5. **Given** the package arrived, **When** production inspects it, **Then** every refusal production's
   own screen would make (wrong client, damaged, older without a second confirmation, requirements
   the host lacks) is made, and shown on the local screen in production's words.
6. **Given** production accepted it, **When** it installs, **Then** the recovery link production
   issues for that installation is saved on the local install and shown, before the first file of
   production is moved.
7. **Given** an installation in progress, **When** the owner closes the local page, **Then** the
   installation goes on to its end and the screen, when reopened, shows where it stands.
8. **Given** production stops answering part-way (the PC sleeps, the network drops, the host
   challenges the requests), **When** it answers again, **Then** the deploy resumes where it was; and
   if it cannot resume, production is left as one release or the other, never in maintenance longer
   than WordPress's own limit, and the local screen says which release production runs.
9. **Given** a deploy is in progress, **When** anyone asks for another deploy, or opens production's
   own Releases screen to install, **Then** it is refused as busy.

---

### User Story 4: The owner is told what happened and how to go back (Priority: P2)

When the deploy ends, the local screen says what is now live and what to do if it is wrong.

**Why this priority**: Without it a deploy is a step taken on trust. It follows the deploy because
there has to be one to report.

**Independent Test**: Deploy once, successfully and once failing at the install step; read both
reports and confirm each states only what is true of production afterwards.

**Acceptance Scenarios**:

1. **Given** a deploy that succeeded, **When** it ends, **Then** the screen states the version and
   commit now live, the database changes production applied, the folders added and taken out, and
   the time it took.
2. **Given** any deploy that reached production, **When** it ends, **Then** the screen states how to
   go back: production's previous release (one action on production's Releases screen), the recovery
   link for when production's admin does not load, and the backup taken before it, with where it is
   and how it is restored.
3. **Given** a deploy that failed, **When** it ends, **Then** the screen says at which step, what
   production says it is running, and whether anything on production changed.
4. **Given** several deploys, **When** the owner looks, **Then** each is listed with who started it,
   when, from which commit, to which version, and the outcome; production's own record of the
   installation names the account the deploy acted as and the install it came from.

---

### User Story 5: The site's own packaging scripts stop being the way a release is made (Priority: P3)

Once a deploy from the admin has delivered a release to a client site, that site's repository does
not need a packaging script of its own. What the script knew (which third-party plugins travel with
a release) is written once in the site's repository, where the build reads it.

**Why this priority**: It removes the second way of making a package, which is how Perego's zip came
to differ from the one the builder measured. It depends on stories 1 to 3 having run on that site.

**Independent Test**: For a site whose repository names its plugins, the package built from the
admin holds exactly those plugins, measured by the builder, and production accepts it.

**Acceptance Scenarios**:

1. **Given** a site's repository that names the third-party plugins its releases carry, **When** a
   build is started, **Then** those plugins are in the package and measured as CoreX's own are.
2. **Given** a site's repository that names none, **When** a build is started, **Then** the package
   carries none, and the statement says so.
3. **Given** a named plugin that is missing from the local install, **When** a build is started,
   **Then** the build refuses before it begins, naming it.

---

### User Story 6: A visitor during an installation sees the site's own page (Priority: P2)

While production is being updated, a visitor gets a short page in the site's colours, with its logo
and name, in the site's language (Arabic and English on a bilingual site, laid out right to left
where the language is). The page says the site is being updated and will be back in a few minutes.
It is the site's page, not WordPress's.

**Why this priority**: The owner asked for it after watching Perego's installation. It touches
every installation, however it was started, so it is not only part of a deploy, and it can be built
before the deploy is.

**Independent Test**: Start an installation on a site with its own colours, logo and two languages;
request the public site during it and confirm the page has the site's name, logo, colours and both
languages, answers with status 503 and a "retry after" time, and loads no plugin, theme or
external file. Remove the site's logo and confirm the page still renders with the name.

**Acceptance Scenarios**:

1. **Given** an installation in progress, **When** a visitor asks for any page, **Then** they get
   the site's own maintenance page with status 503 and a "retry after" time, not WordPress's.
2. **Given** a site whose design tokens set its colours and fonts, **When** the page is shown,
   **Then** it uses those colours, with text contrast that meets WCAG 2.2 AA.
3. **Given** a site in Arabic and English, **When** the page is shown, **Then** it says the same
   thing in both, each in its own direction.
4. **Given** the site has a logo, **When** the page is shown, **Then** the logo is on it; **and
   given** it has none, **Then** the site's name stands in its place.
5. **Given** a site that already has a `wp-content/maintenance.php` of its own, not written by
   CoreX, **When** an installation runs, **Then** that file is left as it is and used.
6. **Given** the installation ended, or went back, **When** a visitor asks for a page, **Then**
   the site answers normally. The page is shown only while WordPress is in maintenance.

---

### Edge Cases

- The checkout has a commit that is not on the repository's remote: production would run code that
  exists only on the owner's PC. The statement says so before confirming (see Assumptions).
- The checkout is on a branch other than the repository's default branch: the statement says so.
- The local install's CoreX version is older than production's: production's own rule for an older
  release applies, with its second confirmation.
- The package was built, then the owner committed again: the statement names the package's commit
  and says the checkout has moved since; the owner deploys what was built or builds again.
- Production's host answers a burst of requests with an HTML challenge page: the deploy treats any
  answer without the installer's mark as "wait and retry", as production's own screen does
  (spec 107 FR-025). A host whose bot protection never lets a non-browser client through is found
  at the connection check, not part-way through a deploy.
- Production is in Maintenance or Coming soon mode already: production's own rule applies.
- Production is a multisite network: not supported by a deploy (see Out of Scope); the connection
  check says so.
- The owner revokes the password on production while a deploy runs: the deploy stops at its next
  request, production is left as spec 107 leaves an interrupted installation, and the screen says
  the connection was refused.
- Two local installs are connected to the same production: production's one-installation-at-a-time
  rule refuses the second.
- The backup takes longer than the time a request may run on the host: it continues across requests,
  as the installation does.
- The local install is copied or exported (a database dump given to someone): the stored production
  password must not travel with it in a form that can be used (FR-022).

## Requirements *(mandatory)*

### Functional Requirements

**Where it is offered**

- **FR-001**: The deploy MUST be offered only on an install whose CoreX operations mode is
  development, that runs from a Git checkout of a site's repository, and whose PC has the tools the
  build needs. It MUST NOT be offered, and its routes MUST refuse, anywhere else, production included.
- **FR-002**: Every action of the deploy MUST be limited to those who may install releases
  (`corex_manage_releases`) on the local install, and the deploy itself MUST require the version
  typed, as production's own screen does.

**Building**

- **FR-010**: A build MUST be made from the commit checked out in the site's repository and from
  nothing else: uncommitted and untracked files MUST NOT be in it, whatever the state of the checkout.
- **FR-011**: A build MUST produce the same package `npm run build:dist -- --client=<site>
  --with-plugin=… --zip` produces for that commit, checked as `npm run verify:dist` checks it.
- **FR-012**: The third-party plugins a build carries MUST be the ones the site's repository names
  for its releases. The build MUST refuse before it begins when a named plugin is missing.
- **FR-013**: The package's description MUST record the commit it was built from, and whether the
  checkout had changes left out.
- **FR-014**: A build MUST run outside the web request that started it, MUST go on when the page is
  closed, and MUST report its progress and its outcome to the screen.
- **FR-015**: Only one build or deploy MAY run at a time on a local install.
- **FR-016**: Before anything is sent, the screen MUST state: CoreX version, client, commit (and
  whether it is on the remote), branch, plugins carried, file count, size, and the uncommitted work
  that is not in the package.
- **FR-017**: Built packages MUST be kept outside the local site's public reach, and MUST be bounded
  in number.

**Connecting to production**

- **FR-020**: Production MUST be addressed over HTTPS with its certificate verified. An address that
  is not HTTPS, or that is the local site itself, MUST be refused.
- **FR-021**: The deploy MUST authenticate on production as a production account holding
  `corex_manage_releases`, with an Application Password that production issued for it.
- **FR-022**: The password MUST be stored only on the local install, never in the site's repository,
  never in a package, never in a log or a record, and never shown again once saved. A copy of the
  local database alone MUST NOT be enough to use it.
- **FR-023**: The owner MUST be able to disconnect locally (the password is removed) and to revoke
  the password on production (WordPress's own Application Passwords list); the local screen MUST
  report a revoked password as a connection that no longer works.
- **FR-024**: Production MUST accept a release sent from another install only when production's
  configuration file says so in so many words, as `COREX_ALLOW_RELEASE_INSTALL` does for installing
  from its own screen. Without it, production's Releases routes MUST keep refusing every request
  that its own screen did not sign. [NEEDS CLARIFICATION: Does production need this second line in
  its configuration, or is an Application Password of an account holding the ability enough? See
  question 3.]
- **FR-025**: The connection check MUST tell apart, and name: a refused password, an account
  without the ability, a production that does not allow releases from elsewhere, a server that drops
  the `Authorization` header (with the lines to add to production's configuration), a host that
  blocks non-browser clients, and a production CoreX too old to receive a deploy.

**Backing production up**

- **FR-030**: Before the first part of a package is sent, a full backup of production's database
  and uploads MUST be taken and MUST have completed. No backup, no install.
  [NEEDS CLARIFICATION: Rely on the backup plugin a site has (UpdraftPlus on both client sites
  today), or ship a backup in CoreX? See question 1.]
- **FR-031**: A backup that cannot be started, fails, or does not complete within a stated time MUST
  stop the deploy with nothing sent, and MUST be named on the screen.
- **FR-032**: The report MUST say where the backup is and how it is restored.
- **FR-033**: The backup MUST NOT be brought down to the local install. It holds production's
  personal data (submissions, users), and the rule of this spec is that production's data stays on
  production.

**Sending and installing**

- **FR-040**: The package MUST be sent to production's Releases in parts, using the upload production
  already receives, sized to the limits production reports, and paced, treating any answer without
  the installer's mark as "wait and retry" (spec 107 FR-025).
- **FR-041**: Production MUST inspect, refuse, install, keep the previous release and finish exactly
  as it does for a package uploaded on its own screen. No check of spec 107 is skipped or weakened
  for a package that arrives this way.
- **FR-042**: The recovery link production issues for the installation MUST be saved on the local
  install and shown before production's first file is moved; it MUST be kept with the same care as
  the password (FR-022).
- **FR-043**: The installation MUST be driven by the local install, not by the page: closing the page
  MUST NOT stop it. A deploy interrupted by the PC or the network MUST resume when it can; when it
  cannot, production MUST be left as one release or the other and never held in maintenance past
  WordPress's own limit, and the local screen MUST say which release production runs.
- **FR-045**: A deploy MUST get through production's maintenance window by itself. Its finishing
  request MUST carry the installation's key, and it MUST keep trying for the whole of the window,
  not a fixed small number of tries. A deploy MUST NOT end with production in maintenance and
  the owner told to wait and press **Finish the installation**. If the window runs out before
  production answers, the screen MUST say what production runs once it answers, and MUST finish
  the installation from there with no step by the owner.
- **FR-044**: A deploy MUST be refused while production has an installation in progress, from any
  source.

**Reporting and the record**

- **FR-050**: When a deploy ends, the local screen MUST state the outcome as production reports it:
  the version and commit live, the database changes applied, the folders added and taken out, and
  how to go back (production's previous release, the recovery link, the backup).
- **FR-051**: Each build and each deploy MUST be recorded on the local install (who, when, commit,
  versions, outcome). Production's record of the installation MUST name the account it acted as
  and the install it came from. No record holds the password, the recovery key or package contents.
- **FR-052**: Every string MUST be translatable; the screen MUST meet the admin's accessibility and
  right-to-left standards, and show loading states as the rest of the admin does (spec 108).

**The maintenance page** (story 6; every installation, not only a deploy)

- **FR-070**: While WordPress holds a site in maintenance for an installation, a visitor MUST get the
  site's own maintenance page: its name, its logo when it has one, its colours and fonts, and its
  message in each of the site's languages, each in its own direction.
- **FR-071**: The page MUST work with nothing of WordPress, CoreX, a plugin or the theme loaded. It
  MUST NOT call a WordPress function, read the database or fetch an external file, and MUST answer
  with status 503 and a "retry after" time.
- **FR-072**: The page's colours and fonts MUST come from the site's design tokens as the site
  resolves them at runtime (`theme.json` and the site's brand overrides), taken when the
  installation begins, not fixed when a package is built.
- **FR-073**: A `wp-content/maintenance.php` CoreX did not write MUST be left as it is, and used.
  The one CoreX writes MUST say in it that CoreX wrote it, and MUST be replaced whole each time.
- **FR-074**: The page MUST meet WCAG 2.2 AA (contrast, language and direction marked, readable at
  phone width), and its words MUST be translatable where the site's other words are.

**What it must not become**

- **FR-060**: A deploy MUST NOT send, copy or overwrite production's database content, uploads,
  users, settings or configuration files. Code only.
- **FR-061**: Production MUST NOT fetch anything: the local install sends, production receives.
  Spec 107 FR-060 stands.
- **FR-062**: Nothing of the deploy MAY depend on a scheduled job on production.

### Key Entities

- **Build**: one run of the builder on the local install, from one commit: its progress, its
  outcome, and the package it made.
- **Package**: the zip production accepts (spec 107), now also naming its commit.
- **Production connection**: production's address, the account the deploy acts as, and its
  Application Password, stored on the local install only.
- **Deploy**: one attempt to put a package on production: its steps (backup, send, inspect,
  install, finish), where it stands, and its outcome.
- **Production backup**: the backup taken before a deploy, on production, with where it is and how
  it is restored.
- **Deploy record**: who, when, which commit, from which version to which, the outcome.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A finished, committed change on the local site reaches production with no terminal
  opened and no agent asked, in one build and one confirmation.
- **SC-002**: No deploy reaches the install step without a completed backup of production taken in
  the same deploy: zero exceptions across every test.
- **SC-003**: Every package production's own screen refuses is refused when it arrives by a deploy,
  and production is unchanged after each refusal.
- **SC-004**: Closing the local page at any step leaves production either finished on the new
  release or on the previous one, and never in maintenance for longer than WordPress's ten minutes.
- **SC-005**: After a deploy, production's database, uploads and users are exactly what they were
  before it, apart from the schema changes the release's own code makes.
- **SC-006**: A deploy of a release of the size Perego ships completes on Perego's host, measured
  there once before this feature is called done.
- **SC-007**: A copy of the local install's database, taken alone, does not let anyone sign in to
  production.
- **SC-008**: No deploy, and no installation started on production's own screen, ends with production
  in maintenance and the owner told to wait. Measured on Perego's host, page open and page closed.
- **SC-009**: A visitor during an installation on a client site sees that site's name, colours and
  languages on every request, and is never shown WordPress's own maintenance page.

## Decided for the owner

Two of the request's four questions are answered here, and one point of the Perego session's
addition of 2026-10-18; each with why and what it costs. Each is the owner's to reopen.

1. **The build and the deploy run in a background process on the PC, started by the local site
   (request question 2).** The local site starts one detached process that runs the builder in a
   clean export of the commit, then drives the deploy's requests to production. It writes its
   progress to a file in a folder the public cannot reach; the screen reads that file each time it
   asks. Because the process and not the page drives production, closing the page stops nothing,
   which is the fault Muva's rehearsal found. The development install is the owner's own PC running
   WAMP, so starting a process there is the developer's machine doing developer work.
   Not chosen: running the build inside web requests (minutes of work against a request time limit);
   WP-Cron (it runs only when a page is visited, and a stalled deploy would wait for one); a helper
   the owner starts in a terminal (the terminal is what this removes).
   *Cost:* the web server's user must find `git`, `node`, `npm`, `composer` and `php`. On WAMP the
   web server may not see the same PATH as the owner's terminal. The screen checks, and names what it
   cannot find (story 1, scenario 5), and the guide says how to point it at each tool.

2. **The production password is stored encrypted on the local install, and revoked on production
   (request question 3).** The Application Password is saved encrypted with a key that lives in the
   local install's configuration file, not in its database, so a database dump alone does not carry
   a usable password (FR-022, SC-007). It is never shown again; the screen shows the account's name,
   when it was saved, and when it was last used. **Disconnect** on the local screen deletes it.
   Revoking it on production is WordPress's own: Users → Profile → Application Passwords, which also
   shows when and from where it was last used. The guide recommends an account on production made
   for deploys, holding the release ability, so revoking it touches nothing else.
   *Cost:* that password can install code on production, which is the same as full control of it.
   It is as sensitive as production's administrator password, and the screen says so where it is
   entered.

3. **The maintenance page is drawn by the site when the installation begins, not built into the
   package.** The Perego session asked for a branded `maintenance.php` shipped with each release,
   coloured from the client's tokens. Writing it at build time would fix the site's colours into
   the package, which the constitution forbids: design tokens are runtime, and a rebrand is
   configuration (Principle V). A brand override changed on production after the build would not
   reach it. So on production, just before WordPress's maintenance begins, the site writes
   `wp-content/maintenance.php` with its tokens as they resolve at that moment. The file is
   self-contained: no WordPress function, no database, no external file (FR-071, FR-072). It is
   written for every installation, from production's own screen as well as from a deploy.
   *Cost:* a site whose CoreX cannot run at the start of an installation gets WordPress's own page,
   but such a site cannot start an installation anyway.

## Questions for the owner

These three change what is built and are the owner's. Each has a recommendation.

### Question 1: How is production backed up before a deploy? (FR-030; request question 1)

Spec 107 decided, for the owner, that CoreX is not the site's backup: a backup that lives inside the
framework is out of reach on the day the framework is broken, and a backup plugin or the host works
when CoreX does not (spec 107 decision 4, FR-061, FR-062). "No backup, no install" reopens that.

| Option | Answer | What it means |
|---|---|---|
| **A (recommended)** | CoreX asks the site's backup plugin to take a full backup and waits until the plugin records it complete. UpdraftPlus is supported first, behind an interface so another plugin can be added. A production with no supported plugin cannot be deployed to, and the connection check says so. | Decision 4 stands: restoring stays with the plugin, which works when CoreX does not and can store its backups off the server. CoreX depends on how UpdraftPlus starts and records a backup, which it does not publish as an interface; that has to be read from its code and tested against each version. UpdraftPlus continues a long backup through WordPress's scheduled events, which are run by visits; whether that works on UltaHost has to be measured before planning. |
| B | CoreX ships its own backup: production's database and uploads, written in parts across requests, kept on production outside public reach, the last few kept. | Works on any site, with no third-party plugin. Reverses decision 4, and a backup is only half of it: restoring a database and uploads from the admin, or without it, is a feature as large as this one, and a backup that cannot be restored is not one. |
| C | No automatic backup. Before the deploy the screen asks the owner to take one and confirm it, and checks the backup plugin's record for a backup newer than an hour. | Smallest. Not "no backup, no install" unless the check is strict, and it adds a step on production's admin to every deploy. |

### Question 2: Are the sites' own packaging scripts retired? (Story 5; request question 4)

Perego's `deploy/package.mjs` adds its six plugins after the builder measured the package, and adds
WordPress's Arabic language files. Muva's names its plugins to the builder (Muva DECISIONS #48).

**Recommended:** yes, each site's script is retired after the deploy has delivered one release to
that site, watched. Before that, the site's repository writes down the plugins its releases carry,
where the build reads them (FR-012), and Perego moves to naming its plugins as Muva does. The
language files are not a release's: spec 107 leaves translations on production as they are
(spec 107 FR-027), and production keeps its own. The change to each script is the client site's,
made by its own session, not by CoreX.

**Not chosen:** keeping the scripts beside the deploy. There would be two ways to make a package, and
they have already differed once.

### Question 3: Does production allow deploys from elsewhere with a line in its configuration? (FR-024)

Today production's Releases routes answer only its own screen, signed by a browser session. A deploy
needs them to answer a request signed with an Application Password. That opens a way to put code on
production that does not pass through its admin.

| Option | Answer | What it means |
|---|---|---|
| **A (recommended)** | Production accepts a deploy only when its configuration file says so: one more line, `define( 'COREX_ALLOW_REMOTE_RELEASE', true );`, beside `COREX_ALLOW_RELEASE_INSTALL`. | The rule spec 107 used for `DISALLOW_FILE_MODS`: only whoever owns the configuration file may open a new way to change code. A leaked password does nothing on a production that has not said yes. Each client site adds the line once, by hand, on the host, which on UltaHost is the file manager the owner already uses for the `Authorization` lines. |
| B | An Application Password of an account holding `corex_manage_releases` is enough. | No step on the host. Any such password, on any client site, can install code from anywhere, including one created for another purpose. |

## Assumptions

Written without asking; each is the owner's to overrule.

- **One production per local install.** A site has one production. Staging as a second target is
  not built.
- **The deploy acts on a single site.** Both client sites are single sites. A network is out of
  scope; the connection check says so.
- **A commit that is not on the remote is deployed only after a second confirmation.** Production
  would otherwise run code that exists only on the owner's PC, the "host ahead of its repository" that
  spec 107 warns about. Refusing outright would leave the owner, who has no terminal, waiting for an
  agent to push.
- **A branch other than the default branch is stated, not refused.** An agent may have left work
  on a feature branch that the owner means to ship; the statement makes it visible.
- **The plugins a release carries are written in the site's repository**, in the file the site
  already keeps for its CoreX baseline or one beside it, chosen at planning.
- **Production has CoreX v0.46.0 or later and receives a deploy only from a release that knows how.**
  The route changes production needs (FR-024, the account it acted as in the record) arrive by a
  release installed the way releases are installed today, before the first deploy.
- **The host's behaviour is measured, not assumed.** Whether UltaHost's bot protection lets a
  non-browser client through, whether WordPress's scheduled events run there, and how long a backup
  of Perego takes are measured on the host before the plan is final.
- **The finishing step's failure on UltaHost is fixed before this spec is planned, on its own.**
  It is a defect of v0.46.0 that strands any installation, not only a deploy. Its cause on that host
  (a header the host drops, or something else; the 503 seen on 2026-10-18 was WordPress's own
  answer from PHP, with no cache header, so a cache is ruled out) is found and fixed as a
  fix to spec 107, and released, before a deploy is built on top of it. FR-045 is what a deploy adds
  on top of that fix.
- **Production's own Releases screen is unchanged for a person using it.** Everything spec 107 does
  stays, including uploading a package by hand.

## Out of Scope

- Copying, pushing or merging any content, database rows, uploads, users or settings, in either
  direction (spec 107's outline B and C remain their own specs).
- Deploying to a multisite network.
- Deploying from production, from staging, or from a host with no Git checkout.
- Restoring a backup from CoreX (unless question 1 is answered B, which makes it a spec of its own).
- Building on the host, and fetching a release from the internet.
- Changing the site packaging scripts in the client repositories: each client site's own session
  does that.
