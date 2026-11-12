# Feature Specification: A private staging install that every push to main reaches

**Feature Branch**: `spec/131-staging-host`

**Created**: 2026-11-12

**Status**: Draft (DECISIONS #623). Waits on the owner's answers in "Questions for the owner" before slice 1.

**Input**: Owner direction, 2026-11-11 and 2026-11-12: "start using the new domains and deploy to it, auto
deploy and handle these things"; SSH access to his server may be used for staging, updates and demo, never for
client production sites. Step 2 of ROADMAP §13 "Public hosts" (DECISIONS #620).

## Why this spec exists

CoreX has no install of its own that is not a developer's machine or a client's site. Two things are owed to
one:

- **The watched installation from CoreX → Releases** (spec 107) on a real host, on v0.46.1 or later, has
  never been run anywhere but local installs. Nobody should meet its first real run on a client's site.
- **A release is checked before it is tagged** only by the test suites. Nothing installs the package a release
  ships onto a real host, through the routes a person uses, before Muva and Perego do.

Spec 123 already gives the tool: `scripts/release-push.mjs` (`npm run release:push`) builds the package with
`build:dist --zip` and installs it through spec 107's Releases routes, driving the screen's own client, signed in
with an Application Password. Staging is where that runs on every push to `main`.

### What was measured on 2026-11-12

- `corex-staging.mustafashaaban.com` resolves through Cloudflare's proxy and reaches the owner's server, which
  answers `404` "No site is configured for this hostname." Nothing is installed.
- The machine the agents run on has **no SSH configuration for the owner's server**: no `~/.ssh/config`, no
  host alias, and the server's address is hidden behind Cloudflare's proxy. The host, port, user and key have to
  come from the owner before any step that uses SSH.
- **The release routes refuse an Application Password over plain HTTP** (`ReleaseCaller`, `is_ssl()`, except on
  a `local` environment). Behind Cloudflare, the origin must see HTTPS: Cloudflare's SSL mode Full (strict) with
  an origin certificate, not Flexible.
- **An Application Password is sent as HTTP Basic `Authorization`**, the same header a web server's own password
  (`.htpasswd`) uses. A server password in front of WordPress would refuse the push.
- No GitHub workflow deploys anything over SSH, rsync or HTTP today, and none reads a deploy secret.
- This PC's internet provider answers some plain-HTTP requests itself (a `307` to its own page), so checks of
  HTTP behaviour are only trusted when run from CI.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - A push to main is installed on staging by itself (Priority: P1)

A change merged to `main` is built into a release package and installed on staging through the Releases
routes, with no one at a keyboard. The run says which release staging runs afterwards and fails when the
installation fails.

**Why this priority**: It is what the owner asked for ("auto deploy") and the reason staging exists: every
package is installed somewhere real before a client's site sees it.

**Independent Test**: Merge a change that bumps nothing but a visible string; within one CI run, staging's
CoreX → Releases shows "Running now" with the build of that commit, and the home page answers.

**Acceptance Scenarios**:

1. **Given** staging runs the previous build, **When** a commit lands on `main`, **Then** CI builds the package,
   installs it on staging through the Releases routes, and reports the release staging now runs.
2. **Given** the installation is refused or a step fails, **When** the run ends, **Then** it fails with the
   site's own words, and staging still runs the release it ran before (spec 107's journaled swap).
3. **Given** two commits land minutes apart, **When** both runs start, **Then** the second waits for the first;
   neither cancels an installation in flight.
4. **Given** the repository is a copy (a client repository, a fork), **When** a commit lands, **Then** nothing is
   sent anywhere (the same repository guard as the docs workflow, spec 102).

---

### User Story 2 - The watched installation from the admin runs here first (Priority: P1)

The owner, signed in to staging, installs a release from CoreX → Releases exactly as a client's administrator
would: sends the package, reads the statement, installs, and sees "Running now" change. This is spec 107's owed
check, recorded as passed or with what failed.

**Why this priority**: It is owed, and the push in story 1 needs a release on the site that already carries
`release-push` support, which only the screen installs the first time.

**Independent Test**: The owner's recorded run, with the release before and after, the time it took, and any
refusal word for word.

**Acceptance Scenarios**:

1. **Given** staging runs a release installed by hand, **When** the owner sends a newer package from the screen,
   **Then** it installs and "Running now" names the newer release.
2. **Given** the watched installation has not passed, **When** story 1's workflow runs, **Then** it does not
   install anything (it is off until this story passes).

---

### User Story 3 - Only the owner reaches staging (Priority: P1)

Anyone who is not the owner (or someone he lets in) gets no page of staging, not even WordPress's sign-in page.
CI reaches it with a credential of its own.

**Why this priority**: Staging runs unreleased code; its admin and sign-in page must not be on the open
internet.

**Independent Test**: From a browser with no session, open the home page, `/wp-login.php` and
`/wp-json/`: each is stopped before WordPress answers. CI's run in story 1 still installs.

**Acceptance Scenarios**:

1. **Given** a visitor with no access, **When** they open any address on staging, **Then** they are stopped
   before WordPress runs.
2. **Given** CI's own credential, **When** story 1's run sends the package, **Then** it reaches the Releases
   routes.

---

### User Story 4 - Staging stays out of search engines and sends no real mail (Priority: P2)

Nothing on staging is indexed, and a form sent or a reply written on staging never reaches a real person's
inbox.

**Why this priority**: The privacy of story 3 should already stop crawlers; this is the second lock, and the
mail rule stops a test on staging from writing to real people, as happened once on the development site.

**Independent Test**: Every response carries `X-Robots-Tag: noindex, nofollow`; `robots.txt` disallows all;
WordPress's "Discourage search engines" is on; a form sent on staging shows in CoreX's mail capture and in no
mailbox.

**Acceptance Scenarios**:

1. **Given** any page of staging, **When** it is fetched with access, **Then** the response says `noindex`.
2. **Given** a form sent on staging, **When** its emails are made, **Then** they are captured, not delivered.

### Edge Cases

- **Staging is down or unreachable** when a push lands: the run fails as unreachable (`release-push` exit 3),
  and the next push tries again. Nothing is queued.
- **The build on `main` has the same version as the running release**: a build between two releases keeps the
  release number. Whether spec 107 installs a package of the same version over itself, or refuses it as not
  newer, is measured in slice 3 before the workflow relies on either (`--accept-older` exists for older ones).
- **An installation is left standing** (a run cancelled mid-way): the next run fails with "an installation
  stands" (exit 2) instead of starting another; `--resume` is for a person to choose, not CI.
- **The origin certificate or Cloudflare's SSL mode changes** to Flexible: the release routes refuse the
  Application Password (`is_ssl()`); the run fails with that refusal. Full (strict) is part of the host's setup.
- **A secret is missing**: the job says which one and fails, rather than silently skipping, once the workflow
  is turned on; before that it does not run (a repository variable turns it on).
- **The owner's server is shared with his own site and the product site** (DECISIONS #620): staging gets its own
  vhost, database, database user and, where the server allows it, its own PHP-FPM pool, so a bad build on staging
  cannot write to another site's files.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: `https://corex-staging.mustafashaaban.com/` MUST serve a WordPress install of a CoreX release
  package (`build:dist --zip`, framework, no client), with its own database and files.
- **FR-002**: Every request MUST be stopped before WordPress unless it carries the owner's access or CI's own
  credential (story 3). The method MUST NOT use the `Authorization` header, which the Application Password needs.
- **FR-003**: Every response MUST carry `X-Robots-Tag: noindex, nofollow`; `robots.txt` MUST disallow all;
  `blog_public` MUST be `0`.
- **FR-004**: Outgoing mail MUST be captured by CoreX and not delivered (`mail.live_delivery` off, environment
  `staging`).
- **FR-005**: The origin MUST be reached over HTTPS from Cloudflare (Full (strict), an origin certificate), so the
  release routes accept the Application Password.
- **FR-006**: A push to `main` MUST build the release package and install it on staging with
  `scripts/release-push.mjs`, once story 2 has passed and a repository variable turns it on.
- **FR-007**: A failed installation MUST fail the CI run with the site's own words and leave the previous release
  running.
- **FR-008**: Runs MUST NOT overlap, and MUST NOT run outside `MustafaShaaban/corex`.
- **FR-009**: The CI account on staging MUST be a user that holds `corex_manage_releases` and nothing it does not
  need, with an Application Password used by nothing else.
- **FR-010**: No client's data, and no real person's personal data, MAY be put on staging.
- **FR-011**: Every step only the owner can take (SSH access, Cloudflare records and rules, GitHub secrets and
  variables) MUST be written in this spec's slices in the order they run, with the check after each.

### Key Entities

- **Staging install**: the site at the staging host, with the release it runs ("Running now").
- **CI account**: the staging user CI signs in as, and its Application Password.
- **Access credential**: what lets the owner, and separately CI, past the front door (FR-002).

## Success Criteria *(mandatory)*

- **SC-001**: A commit on `main` is running on staging within 20 minutes of the merge, with no one acting.
- **SC-002**: 0 of 3 sampled addresses (home, `/wp-login.php`, `/wp-json/`) answer a visitor without access with
  a WordPress page.
- **SC-003**: The watched installation (story 2) is recorded once, passed, before the workflow is turned on.
- **SC-004**: A deliberately failing installation fails its run and leaves staging on the release it ran.
- **SC-005**: A form sent on staging reaches 0 mailboxes.

## Questions for the owner

1. **SSH access**: the server's address (the origin behind Cloudflare), the SSH port, the user, and which key
   (the agents' machine has `~/.ssh/id_rsa` from 2022 and no config). What runs on the server (a control
   panel such as cPanel, Plesk or CloudPanel, or plain nginx/Apache), and where sites live? The agent adds a
   `corex-server` alias to `~/.ssh/config` from the answer.
2. **How staging is kept private** (FR-002). Recommended: **Cloudflare Access** on `corex-staging`, a one-time
   code to the owner's email for people and a service token for CI. It runs before the server is reached, costs
   nothing at this size, and leaves `Authorization` free. Not recommended: a server password (`.htpasswd`), which
   blocks the Application Password; WordPress's own sign-in only, which leaves the sign-in page public.

## Assumptions

- **The owner's server serves staging** (DECISIONS #620), behind Cloudflare's proxy, with PHP 8.3 and MySQL or
  MariaDB, as CoreX requires.
- **The agent sets the server up over SSH** once the owner gives access: vhost, database, origin certificate,
  first installation by hand (`docs/en/05-deployment/first-installation-by-hand.md`), headers. The owner does
  what is in his accounts: Cloudflare, GitHub settings and secrets.
- **Staging tracks `main`, not tags.** A tag's package is the same build as the `main` commit it points at, so
  staging has run it before it is released.
- **Muva and Perego are untouched.** Nothing here reaches a client's host.

## Slices

### Slice 1: the host (agent over SSH, owner in Cloudflare)

After the owner's answers. Owner: keep `corex-staging` proxied; SSL mode Full (strict); an origin certificate
(Cloudflare → SSL/TLS → Origin Server → Create certificate), given to the agent over SSH or placed by him;
Cloudflare Access application for `corex-staging.mustafashaaban.com` with his email and a service token named
`corex-ci`. Agent: vhost, database and user, PHP-FPM pool, certificate, `X-Robots-Tag` header, the first
installation by hand of the current release, `blog_public` 0, environment `staging`, live delivery off, the
CI user. Check: SC-002 from CI and from a browser; the home page answers with access.

### Slice 2: the watched installation from CoreX → Releases (owner, recorded)

The owner installs the next package from the screen, as in story 2; the agent writes down what happened. Check:
SC-003.

### Slice 3: CI installs each push (agent)

`.github/workflows/staging.yml`: on push to `main`, guarded to this repository, one run at a time; build the
package; `npm run release:push -- --package=<zip>` with `COREX_PUSH_SITE`, `COREX_PUSH_USER`,
`COREX_PUSH_APP_PASSWORD`; send Cloudflare Access's service-token headers (`release-push` gains a way to add
request headers from the environment, tested first); off until the repository variable `STAGING_DEPLOY` is
`on`. Measured first: the same-version edge case. Owner: the secrets `COREX_PUSH_USER`,
`COREX_PUSH_APP_PASSWORD`, `CF_ACCESS_CLIENT_ID`, `CF_ACCESS_CLIENT_SECRET`, the variable `COREX_PUSH_SITE`,
then `STAGING_DEPLOY` set to `on`, walked by a wizard. Check: SC-001, SC-004.
