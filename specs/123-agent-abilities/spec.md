# Feature Specification: An agent works on a CoreX site, and installs a release, through MCP

**Feature Branch**: `spec/123-agent-abilities`

**Created**: 2026-10-25

**Status**: Clarified and planned. The owner answered five questions on 2026-10-25 (DECISIONS #500 to #505).
[plan.md](plan.md) was written the same day (DECISIONS #506 to #511), and it amended FR-001, FR-017, User
Story 4 and User Story 5 scenario 6 as noted there. [tasks.md](tasks.md) holds 49 tasks. Nothing is built.

**Input**: The owner's request of 2026-10-25, worked out with him in a planning session: Claude, or any agent
that speaks the Model Context Protocol (MCP), should be able to work on a CoreX site, and above all install a
finished release, so that he no longer uploads the release package by hand in the admin. The design below was
agreed in that session; the questions it left open are answered under "Clarifications".

## Why this spec exists

Installing a release today takes the owner through the admin screen of spec 107: he opens Releases, picks a
53 MB package from `C:\Users\pc\.muva\packages`, waits for the upload, reads what the package holds, types the
version to confirm, and watches the steps. Every part of that is already built and safe. What is missing is a
way for an agent to do it for him, under rules he sets, without the package passing through the agent.

CoreX chose its agent layer in DECISIONS #8: WordPress's own Abilities API plus the official MCP Adapter, and no
MCP server of its own. The deferred-spec closeout (DECISIONS #53) registered two read-only abilities. This spec builds
the rest of that layer on the same choice.

## What is already true (checked on 2026-10-25)

- **The two CoreX abilities are invisible to an agent.** An MCP connection to
  `https://mu-va.com/wp-json/mcp/mcp-adapter-default-server` lists only `core/get-site-info`,
  `core/get-user-info` and `core/get-environment-info`. Asking for `corex/site-info` answers "not found".
- **Why**: the MCP Adapter (v0.7.1, released 2026-10-16) keeps every ability private unless the ability says
  otherwise. Its rule, in `includes/Abilities/McpAbilityExposure.php` (`is_public`), is: the ability is public
  when `meta.mcp.public` is true; when `meta.mcp.public` is not set, it is public only when `meta.public` is
  true; a malformed `meta.mcp` is private. CoreX's `AbilitiesProvider` sets `meta.annotations.readonly` and
  `meta.show_in_rest` and neither of the two keys the adapter reads, so both abilities stay private.
- **The adapter's documented hints**: an ability's `meta.annotations` of `readonly`, `destructive` and
  `idempotent` reach the agent's client as MCP's read-only, destructive and idempotent hints. On WordPress 7.0
  an ability needs `meta.show_in_rest` to be reachable over REST; from 7.1, `meta.public` covers it.
- **The adapter is optional.** It is published both as a WordPress plugin and as a Composer package
  (`wordpress/mcp-adapter`). The Abilities API itself is part of WordPress 7.0.
- **The release routes accept only the admin screen.** `ReleaseRestGateway::answer()` refuses any request that
  does not carry a valid `X-WP-Nonce`. A nonce comes from a signed-in browser page, so a command on the owner's
  machine cannot call the upload routes today, even with an Application Password.
- **Who may install** is decided by `ReleaseRestGateway::mayManage()`: the person must hold
  `CorexAbility::MANAGE_RELEASES` (`corex_manage_releases`); on a multisite network they must also be a super
  administrator and the request must be on the main site. `corex_manage_releases` is one of the CoreX abilities
  of the role matrix that spec 117 makes every screen ask for. Those CoreX abilities are capabilities of a
  WordPress user and a different thing from the abilities of the WordPress Abilities API that this spec
  registers; to keep the two apart, this spec calls the second kind "agent abilities".
- **An installation holds visitors off** while folders are swapped (spec 107, FR-024). A request of the
  installation gets through that maintenance answer only with the installation's key (FR-024a). WordPress
  itself stops honouring a maintenance file ten minutes after it was written (DECISIONS #301).
- **CoreX knows no page-cache plugin by name.** Its cache status (spec 078) learns of a page cache only when a
  provider registers itself through the `corex_page_cache_provider` filter, and it clears none.
- **Release packages are about 53 MB.** The five newest Muva release packages
  (`corex-release-muva-0.47.0-*.zip`) in `C:\Users\pc\.muva\packages` on 2026-10-25 are 53,034,875 to
  53,038,496 bytes. Spec 107 accepts a package only under a name of the form `corex-release-<name>.zip`.

## Clarifications

### Session 2026-10-25

Asked of the owner in this session; each answer is the option recommended to him.

- Q: How does the MCP Adapter reach a CoreX site? → A: **It stays a separate plugin** that a site installs
  itself. CoreX does not bundle it, registers its agent abilities whether or not it is there, and never requires
  it (FR-003, DECISIONS #501).
- Q: How does a request signed with an Application Password get past the release routes' nonce check? → A:
  **On the three upload routes only** (how much is received, append a part, complete), only while agent changes
  are on, and only for an account that may manage releases. Every other release route keeps the nonce, because
  inspecting, installing and going back are agent abilities that call spec 107's services and never pass through
  those routes (FR-014, DECISIONS #502).
- Q: Which settings agent abilities are in the first version? → A: **Clearing the page cache** (only through a
  page-cache plugin CoreX detects, such as LiteSpeed Cache), **turning coming-soon on and off**, and **reading
  the feature flags**. No agent ability changes a flag (FR-020, DECISIONS #505).
- Q: What form does the command that sends a package take? → A: **A Node script in CoreX**, run on the owner's
  machine with no WordPress around it, that reads the Application Password from a file outside the repository
  (FR-013, FR-015, DECISIONS #503).
- Q: How is the agent's own account set up? → A: **CoreX ships a narrow "CoreX agent" role.** The owner creates
  the user in WordPress's Users screen, gives it that role, grants the role its CoreX abilities in spec 117's
  role matrix, and makes its Application Password himself. CoreX never creates a user or a password (FR-008,
  DECISIONS #504).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - An agent can see what CoreX offers it (Priority: P1)

The owner connects Claude to his site through MCP and asks what it can do there. Claude lists the CoreX agent
abilities beside WordPress's own, reads their descriptions and their inputs, and can run the read-only ones.

**Why this priority**: nothing else in this spec reaches an agent until CoreX's abilities are visible. It is
also the smallest change that proves the whole path, from the site to the agent, on a real host.

**Independent Test**: on a site with the MCP Adapter active, an agent that discovers abilities sees
`corex/site-info` and `corex/list-blocks`, gets their schemas, and runs both. With the adapter absent, the site
behaves exactly as before.

**Acceptance Scenarios**:

1. **Given** a CoreX site with the MCP Adapter active, **When** an agent signed in as a person who may read
   the site asks the default server for its abilities, **Then** the CoreX agent abilities that person may use
   are listed, each with its label, description and input and output schemas.
2. **Given** the same site, **When** the agent asks for the information of `corex/site-info`, **Then** it
   gets it, and running it returns the site's name, the CoreX version and the number of CoreX blocks.
3. **Given** a CoreX agent ability, **When** the agent's client reads its hints, **Then** a read-only ability
   says it is read-only, and any ability that changes the site says whether it is destructive.
4. **Given** a site without the MCP Adapter, **When** it loads, **Then** CoreX still registers its agent
   abilities with WordPress and nothing fails or warns.

---

### User Story 2 - The owner decides what an agent may do (Priority: P1)

Before any agent ability can change his site, the owner gives the agent its own WordPress account with the
"CoreX agent" role, allows that role only the CoreX abilities it needs in the role matrix, and makes it its own
Application Password that he can revoke at any time. Abilities that change the site stay off until he turns
them on.

**Why this priority**: an agent that can install a release can take a site down. The limits have to exist
before the first ability that changes anything.

**Independent Test**: with write abilities off, every agent ability that changes the site refuses, whoever
calls it. With them on, only an account that holds the right CoreX ability is let through, and revoking the
agent's Application Password ends its access at once.

**Acceptance Scenarios**:

1. **Given** a fresh install of this release, **When** an agent calls any agent ability that changes the
   site, **Then** it is refused with a reason that says agent changes are turned off, and nothing changes.
2. **Given** agent changes turned on, **When** the agent's account does not hold the CoreX ability an agent
   ability asks for, **Then** the call is refused, and the refusal is counted in the access-denied log as any
   other refusal is (spec 112).
3. **Given** the owner revokes the agent's Application Password, **When** the agent calls again, **Then**
   WordPress no longer signs it in and every agent ability refuses.
4. **Given** any call of a CoreX agent ability, allowed or refused, **When** the owner opens the activity log,
   **Then** he finds who called it, which ability, when, and what came of it, without any secret or any part
   of the package's bytes in the record.

---

### User Story 3 - The agent reads where the site stands (Priority: P1)

The owner asks Claude "what is running on the site, and did anything go wrong since the last release?". Claude
answers from the site itself: the installed release and its history, what a release overview shows, recent
log entries, and, only when the owner has allowed it, recent form submissions.

**Why this priority**: an agent has to read the site's state before it can be trusted to change it, and every
install decision starts from "what is installed now".

**Independent Test**: each read ability returns what the matching admin screen shows the same account, and
nothing more.

**Acceptance Scenarios**:

1. **Given** an account that may manage releases, **When** the agent asks for the release overview, **Then**
   it gets the installed version, the release history, any upload in progress and any leftovers, as the
   Releases screen shows them.
2. **Given** an account that may read logs, **When** the agent asks for recent log entries, **Then** it gets
   at most a bounded number of the newest entries, with the filters the logs screen offers.
3. **Given** an account that does not hold the CoreX ability for submissions, **When** the agent asks for
   submissions, **Then** it is refused, and personal data never leaves the site.
4. **Given** an account that holds it, **When** the agent asks for submissions, **Then** it gets a bounded
   page of them, and the call is recorded as an access to personal data.

---

### User Story 4 - The owner sends a package to the site without the browser (Priority: P1)

The owner, or Claude on his machine, runs one command with the path of a release package. The command sends it
to the site in parts, resumes where it stopped if the connection breaks, and ends by printing the package's
name and SHA-256. The agent installs the package by its name, and checks that the SHA-256 the site reports is
the one the command printed.

**Why this priority**: the package must never pass through an MCP call (a 53 MB package would have to go
through the model). Without this command an agent can read and install, but the owner still uploads by hand.

**Independent Test**: the command sends a 53 MB package to a test site; cutting the connection half-way and
running it again sends only the rest; the site then lists the package as received, under the same SHA-256 the
command printed.

**Acceptance Scenarios**:

1. **Given** a release package on the owner's machine and the agent account's Application Password, **When**
   the command runs, **Then** the package reaches the site through the same resumable upload the admin screen
   uses, and the command prints its SHA-256 and the site's answer.
2. **Given** an upload cut off part-way, **When** the command runs again with the same package, **Then** it
   asks the site how much it already has and sends only the rest.
3. **Given** a package the site already holds whole, **When** the command runs, **Then** it sends nothing and
   says so.
4. **Given** a request to the upload routes signed with neither a valid nonce nor an accepted Application
   Password, **When** it arrives, **Then** it is refused as today.

---

### User Story 5 - The agent installs the release (Priority: P1)

The owner tells Claude "install the package I just sent". Claude inspects it, shows him what the site would
become (version, client, what goes back to an older version), and asks him. When he agrees, Claude confirms,
runs the steps until the installation is finished, and reports. If the result is wrong, he tells Claude to go
back, and it does.

**Why this priority**: this is the reason for the spec.

**Independent Test**: on a test site, an agent installs a package received through User Story 4 from start to
finish using only agent abilities, then goes back to the release before, and the site's state after each matches
what the admin screen produces for the same package.

**Acceptance Scenarios**:

1. **Given** a received package, **When** the agent inspects it, **Then** it gets what the admin screen's
   inspection shows: version, client, whether it is older than the running release, and the plugins and themes
   it would put back to older versions.
2. **Given** an inspected package, **When** the agent prepares the installation, **Then** nothing on the site
   changes yet, and the answer says exactly what confirming will need (the version typed, the client's name
   where the site cannot say whose it is, and each acceptance spec 107 asks for).
3. **Given** a prepared installation, **When** the agent confirms it with what the preparation asked for,
   **Then** the installation starts; **When** it confirms with anything missing or different, **Then** it is
   refused and nothing starts.
4. **Given** a started installation, **When** the agent runs its steps one after another, **Then** each call
   reports progress, and the installation finishes, even though the agent's calls cannot carry the
   installation's key through the maintenance answer.
5. **Given** a finished installation, **When** the agent asks to go back, **Then** the release kept before is
   restored through spec 107's return, after a prepare and confirm pair of its own.
6. **Given** leftovers of an earlier installation, **When** the agent asks to remove them, **Then** they are
   removed after a prepare and confirm pair: preparing lists them, and removing goes ahead only while the
   leftovers are still the ones that were listed. (The screen removes them in one click; the pair is the
   agent's extra step.)
7. **Given** an installation already running, started from the screen or by another agent, **When** an agent
   tries to start one, **Then** it is refused (spec 107, FR-023).

---

### User Story 6 - The agent finishes the small jobs around a release (Priority: P3)

After a release, the owner asks Claude to clear the page cache, check which CoreX features are on, and take the
site out of coming-soon when he is ready to launch.

**Why this priority**: useful, and each job is small, but none of them is needed to stop uploading by hand.

**Independent Test**: each settings ability changes exactly what the matching admin control changes and is
refused under the same conditions.

**Acceptance Scenarios**:

1. **Given** a site with a page-cache plugin CoreX knows how to clear, **When** the agent asks to clear the
   page cache, **Then** it is cleared; **Given** none, **Then** the agent is told there is nothing CoreX can
   clear, and nothing fails.
2. **Given** an account that may manage operations, **When** the agent turns coming-soon on or off, **Then**
   the mode changes through the same service the Operations screen uses, and the change is recorded as any
   mode change is.
3. **Given** any account, **When** the agent asks for the feature flags, **Then** it gets their current state,
   read-only.

---

### Edge Cases

- **The MCP Adapter is missing, inactive, or older than the release that reads `meta.mcp.public`.** CoreX
  registers its agent abilities anyway and nothing breaks. No agent can reach them until an adapter is there,
  except the two older abilities, which stay on the Abilities REST routes (FR-001). CoreX does not ship a copy
  of the adapter to fill the gap.
- **An Application Password reaches a release route other than the three upload routes.** It is refused as
  today: only the screen's nonce opens those routes, and an agent reaches the same work through agent
  abilities.
- **The CoreX agent role is given to a person.** It holds nothing beyond what the role matrix allows it, so a
  person with it can do no more than the agent could.
- **An installation is started from the screen while an agent is mid-way through one**, or the reverse: only
  one runs (spec 107, FR-023); the second is refused with a reason both can read.
- **The agent loses its connection mid-installation.** The installation is left as spec 107 leaves a screen
  that closed: resumable from the screen or by the agent, and never half one release and half the other
  (FR-022).
- **The agent's calls cannot get through the maintenance answer**, because the agent's client cannot add the
  installation's key to them. The installation must not depend on a call made while visitors are held off; if
  it is left there, WordPress ends the maintenance answer by itself after ten minutes (DECISIONS #301), which
  is the outcome FR-018 exists to avoid.
- **The confirmation phrase is guessed or invented by the agent.** The site cannot tell the owner's consent
  from the agent's. The protections are: write abilities off by default, the destructive hint that makes the
  agent's client ask the owner before the call, the prepare and confirm pair, the journal, and the agent's own
  revocable account. The spec says this plainly rather than claiming more.
- **A multisite network.** Installing a release needs a super administrator on the main site
  (`mayManage()`). An agent account that is not a super administrator cannot install there; the spec does not
  widen that rule.
- **Two Application Passwords on the agent account.** Each is the agent; revoking one leaves the other. The
  journal names which one was used.
- **A package larger than the upload limit** (512 MB, `ReleaseUpload::LARGEST`) or damaged in transit: the
  command reports the site's refusal as the screen does.
- **The agent asks for more log entries or submissions than the bound.** It gets the bound and is told there is
  more.

## Requirements *(mandatory)*

### Functional Requirements

**Exposure**

- **FR-001**: Every CoreX agent ability MUST be explicitly public to MCP, and to MCP only. Public to MCP means
  what the MCP Adapter reads as public (see "What is already true"). The one exception: the two abilities
  registered before this spec, `corex/site-info` and `corex/list-blocks`, MUST stay reachable over the
  Abilities REST routes as they are today.
- **FR-002**: Every CoreX agent ability MUST say whether it is read-only, and every one that changes the site
  MUST say whether it is destructive and whether running it twice has the same effect as once.
- **FR-003**: CoreX MUST register its agent abilities whether or not the MCP Adapter is present, and MUST run
  unchanged without it (Principle IX). The adapter stays a separate plugin that a site installs; CoreX MUST NOT
  bundle it, load a copy of it, or install it.
- **FR-004**: CoreX MUST NOT add an MCP server of its own (DECISIONS #8). Every agent ability is reached through
  the adapter's default server.

**The agent's account and the switch**

- **FR-005**: Agent abilities that change the site MUST be off by default, behind one feature flag, and refuse
  while it is off, with a reason that names the flag. Read-only agent abilities are not behind it.
- **FR-006**: An agent ability MUST NOT be able to turn that flag on, or to change who holds which CoreX ability.
- **FR-007**: Every agent ability MUST ask for the CoreX ability (spec 117) that the matching admin screen or
  action asks for. Release agent abilities MUST ask what `ReleaseRestGateway::mayManage()` asks, including the
  multisite rule.
- **FR-008**: CoreX MUST register a "CoreX agent" role that holds WordPress's `read` capability and no CoreX
  ability of its own; the owner allows it CoreX abilities in spec 117's role matrix. CoreX MUST NOT create a
  user or an Application Password. A user with the role, allowed `corex_manage_releases`, MUST be able to
  install a release on a single site without being a site administrator. CoreX MUST document the setup: create
  the user, give it the role, allow its abilities, make its Application Password, and revoke it.

**Read**

- **FR-009**: A read agent ability MUST exist for each of: site info (existing), the release overview, the
  installed release, recent log entries (spec 112's logs and the activity log), and recent form submissions.
- **FR-010**: A read agent ability MUST return no more than what the matching admin screen shows the same
  account, and MUST bound how many entries one call returns.
- **FR-011**: The submissions agent ability MUST ask for the CoreX ability for submissions, and each call MUST be
  recorded as an access to personal data.

**Moving the package**

- **FR-012**: A package's bytes MUST NOT pass through any MCP call.
- **FR-013**: CoreX MUST provide a Node script, run on the owner's machine with no WordPress around it, that
  sends a package to a site through the existing resumable upload (keyed by the package's SHA-256), signed in
  with an Application Password, and prints the SHA-256 and the site's answer.
- **FR-014**: The three upload routes (how much of a package is received, append a part, complete) MUST accept
  a request signed in with an Application Password in place of the nonce, and only when all of these hold:
  WordPress signed the request in with an Application Password, agent changes are on, and the account passes
  `ReleaseRestGateway::mayManage()`. Every other release route MUST keep requiring the nonce. A request signed
  with neither MUST be refused as today.
- **FR-015**: The script MUST read the site's address, the account's name and its Application Password from a
  file outside the repository (for Muva, under `C:\Users\pc\.muva\`), and MUST NOT print the password, log it,
  or take it as a command-line argument where other programs can read it.

**Release**

- **FR-016**: Agent abilities MUST exist to inspect a received package, prepare an installation, confirm it, run
  its next step, finish it, give up an installation that has moved nothing, go back to the release kept before,
  and remove leftovers. Each MUST use the spec 107 service the matching route uses, so the screen and an agent
  can never disagree about what an installation did.
- **FR-017**: Every agent ability that changes what release is installed, or removes anything, MUST come as a
  prepare and confirm pair:
  - preparing changes nothing;
  - confirming asks for exactly what the screen's confirmation asks for;
  - where the screen asks nothing (removing leftovers), confirming MUST carry the fingerprint of what preparing
    listed, and MUST refuse if the leftovers have changed since.
- **FR-018**: An installation started by an agent MUST be able to finish through agent calls that cannot carry
  the installation's key, and MUST NOT leave visitors held off longer than an installation from the screen can.
- **FR-019**: One installation at a time, whoever started it (spec 107, FR-023).

**Settings**

- **FR-020**: The settings agent abilities in this release MUST be exactly three: clear the page cache, turn
  coming-soon on or off, and read the feature flags. Coming-soon MUST change only through the service the
  Operations screen uses. Clearing the page cache MUST work only through a page-cache plugin CoreX detects,
  never as a dependency (Principle IX), and MUST answer plainly when there is nothing CoreX can clear. CoreX
  detects none by name today, so this release MUST teach it LiteSpeed Cache, through the same page-cache
  provider seam its cache status already reads. (Muva's host has LiteSpeed Cache installed; the owner
  deactivated it there on 2026-10-25, so on Muva this ability answers that there is nothing to clear until he
  turns it back on.) Reading the flags MUST be read-only; no agent ability changes a flag.

**Journal**

- **FR-021**: Every call of a CoreX agent ability, allowed or refused, MUST add one entry to the activity log
  with who called it, which ability, which Application Password, when and the outcome. Refusals MUST also reach
  the access-denied log as spec 112 counts them.
- **FR-022**: A journal entry MUST NOT hold a password, a nonce, an installation key, personal data from a
  submission, or any of a package's bytes.

### Key Entities

- **Agent ability**: one ability CoreX registers with the WordPress Abilities API: its name under `corex/`,
  label, description, input and output schemas, hints, the CoreX ability it asks for, and whether it is behind
  the agent-changes switch.
- **CoreX agent role**: the WordPress role CoreX registers for agent accounts; `read` only, plus whatever the
  role matrix allows it.
- **Agent account**: the WordPress user an agent signs in as, created by the owner, holding the CoreX agent
  role and one or more Application Passwords.
- **Push settings file**: the file on the owner's machine, outside any repository, that tells the Node script
  which site to send to and as whom.
- **Agent-changes switch**: the feature flag that lets agent abilities change the site; off by default.
- **Package upload**: spec 107's resumable upload, keyed by SHA-256, now reachable by the command as well as the
  screen.
- **Installation**: spec 107's installation, its record, steps and journal; unchanged in shape, now also started
  by an agent.
- **Agent call record**: one activity-log entry per agent ability call.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: The owner installs a release on mu-va.com without opening the admin, by running one command and
  approving the installation in his conversation with the agent.
- **SC-002**: An agent connected to a CoreX site lists every CoreX agent ability its account may use, and none it
  may not.
- **SC-003**: Over a cut connection, sending a 53 MB package again transfers only what the site did not have.
- **SC-004**: With agent changes off, 100% of the agent abilities that change the site refuse, whatever account
  calls them.
- **SC-005**: For the same package, an installation by an agent and one from the screen leave the site in the
  same state, and both appear in the release history.
- **SC-006**: Every agent ability call can be found in the activity log by the owner, and none of the records
  holds a secret or personal data.
- **SC-007**: A CoreX site without the MCP Adapter passes the same test suites as before this release.

## Assumptions

- Packages are built on the owner's machine (`C:\Users\pc\.muva\packages` for Muva) and stay there; nothing
  publishes them anywhere a site could fetch them from.
- The agent's client asks the owner before it calls an ability marked destructive. CoreX cannot enforce that,
  and the spec does not rely on it alone (see Edge Cases).
- The site already runs WordPress 7.0 or later, so the Abilities API is present.
- Spec 117's role matrix is where the agent account receives its CoreX abilities. If spec 117 has not merged when
  this is planned, the plan names the order.
- The activity log of spec 114 and the access-denied log of spec 112 are where calls are recorded; this spec
  adds no log of its own.
- The two existing agent abilities keep WordPress's `edit_posts`: `corex/site-info` carries the CoreX
  version, which spec 111 keeps from anyone who could not already edit the site. An account with only the
  CoreX agent role reads the version through the release abilities instead (DECISIONS #508).
- The owner's machine has Node, as it already does for building packages.

## Not in this spec

- **The site pulls the package from a URL.** Rejected for now: the packages live only on the owner's machine,
  and a site that fetches code from an address it is given is a larger risk than one that receives an upload
  it already checks. It can return when packages are published somewhere a site can trust.
- **A CoreX MCP server.** Rejected by DECISIONS #8.
- **Sending the package through an MCP call**, as base64 or in parts. Rejected: every byte would pass through
  the model's context.
- **Agent abilities for content, forms, email or data models.** Later specs, on the same rules.
- **A screen for agent accounts.** The owner sets the account up in WordPress's own Users screen and spec 117's
  role matrix.
