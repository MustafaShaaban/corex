# Feature Specification: The update channel on corex-updates.mustafashaaban.com

**Feature Branch**: `spec/132-updates-host`

**Created**: 2026-11-12

**Status**: Draft (DECISIONS #624). Not to be built before spec 131 (staging) has passed, and not before the
owner answers "Questions for the owner".

**Input**: ROADMAP §13 "Public hosts", step 4 (DECISIONS #620): "Static manifest, packages and SHA-256 for the
in-admin updater (`updates.endpoint`)". Owner direction of 2026-11-12 to deploy CoreX's hosts automatically.

## Why this spec exists

A CoreX site has two ways to learn of a newer framework, and neither has a source:

- **The in-admin updater** (spec 034, `Corex\Update\UpdateService`) reads a JSON manifest at
  `updates.endpoint` (`{version, package, url, requires, tested}`), and when the version is newer it hands
  `package` to WordPress's own plugin updater, which replaces the framework plugin's files. The endpoint ships
  empty, so the updater does nothing ("Corex never phones home unless you configure a source you control").
- **Releases** (spec 107) installs a whole-site package (`corex-release-<…>.zip` from `build:dist --zip`) that
  someone sends from CoreX → Releases or with `release:push`. No workflow publishes those packages anywhere;
  GitHub releases carry only `corex.pot`.

The updates host is where both are published, as static files a site can fetch.

### What was measured on 2026-11-12

- `corex-updates.mustafashaaban.com` resolves through Cloudflare's proxy to the owner's server, which answers
  `404` "No site is configured for this hostname."
- The updater's manifest has **no checksum and no signature field**, and it does not download anything itself.
  Spec 107 states its packages are not signed; spec 123 left "the site pulls the package from a URL" out.
- The updater replaces **one plugin** (the file and slug it was built with), while a CoreX release is several
  plugins, a theme and a CLI package that move together.

## The question this spec must settle first

**What the updater offers.** A one-plugin update of a framework whose plugins move together can leave a site
with `corex-core` at one version and `corex-config` at another. The options, for the owner:

1. **The manifest points at release packages, and the updater only tells** (recommended): the admin shows "CoreX
   x.y.z is available" with a link to its notes, and the administrator installs the release package from CoreX →
   Releases, or a developer with `release:push`. The site never installs from a URL on its own until packages are
   signed (ROADMAP §13 step 6).
2. The manifest offers a one-plugin package to WordPress's updater, as spec 034 built it, for the framework
   plugin only. Faster for the administrator; the mixed-version risk above stays.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - A site configured with the channel sees a new release (Priority: P1)

An administrator on a site whose `updates.endpoint` is the channel's manifest sees, within a day of a release,
that a newer CoreX exists, its version, and where its notes are.

**Independent Test**: Point a test site at the manifest, tag a release, and read the admin's update notice.

**Acceptance Scenarios**:

1. **Given** a site on v0.48.1 and a published v0.49.0, **When** WordPress checks for updates, **Then** the
   admin names v0.49.0 and links to its notes.
2. **Given** the channel is unreachable, **When** WordPress checks, **Then** nothing changes on the site (the
   updater's existing fail-safe).

---

### User Story 2 - A tag publishes itself (Priority: P1)

A release tag on `MustafaShaaban/corex` publishes, with no one acting: the release package, its SHA-256, and the
manifest naming it, as static files on the updates host.

**Independent Test**: Tag a release; fetch the manifest, the package and its `.sha256`; the hash of the package
matches.

**Acceptance Scenarios**:

1. **Given** a release tag, **When** its workflow runs, **Then** the package and its `.sha256` are uploaded first
   and the manifest last, so a site never reads a manifest naming a file that is not there yet.
2. **Given** the upload fails, **When** the run ends, **Then** it fails and the manifest still names the previous
   release.

---

### User Story 3 - Anyone can check a package by hand (Priority: P2)

A developer who downloads a package can check it against its published SHA-256 before sending it to a site.

**Acceptance Scenarios**:

1. **Given** a package and its `.sha256`, **When** the developer runs `sha256sum -c`, **Then** it passes.

### Edge Cases

- **A release is withdrawn**: the manifest is pointed back at the previous release; the package stays for sites
  that already have it.
- **A client build** (`--client=<name>`) is not published here: client packages hold client code and go only to
  that client (spec 107, 123).
- **The channel is proxied by Cloudflare**: the manifest must not be cached past a release; it is served with a
  short cache lifetime, packages with a long one (their names carry the version and build time).
- **Pre-releases**: a tag that is a pre-release does not move the manifest.

## Requirements *(mandatory)*

- **FR-001**: The updates host MUST serve static files only, over HTTPS; no PHP runs there.
- **FR-002**: A release tag MUST publish the framework's release package, its SHA-256 and a manifest in the
  updater's format, package and hash before manifest.
- **FR-003**: The manifest MUST name the version, the package's address, the release notes' address, and the
  WordPress and PHP versions it requires; it MAY add `sha256` beside them.
- **FR-004**: What the updater does with the manifest MUST follow the owner's answer above.
- **FR-005**: The host MUST NOT publish client packages.
- **FR-006**: Upload MUST be over SSH with a key that can write only to the updates host's files.
- **FR-007**: Nothing here signs packages; signing is ROADMAP §13 step 6.

## Success Criteria *(mandatory)*

- **SC-001**: A tag is published, manifest included, within 30 minutes of being pushed.
- **SC-002**: The published package's SHA-256 matches its `.sha256` file for 1 of 1 sampled releases.
- **SC-003**: A site pointed at the channel shows the new version on its next update check.

## Questions for the owner

1. What the updater offers (the two options above). Recommended: option 1.
2. The same SSH access as spec 131, and whether the updates host may use a separate system user and key (FR-006).

## Assumptions

- The owner's server serves the host (DECISIONS #620), as a static vhost.
- Spec 131 (staging) is done first, so every published package has been installed on staging before it is tagged.

## Slices

1. **The host** (agent over SSH): a static vhost, a deploy user and key that write only there, the headers of the
   cache edge case. Owner: the Cloudflare record stays proxied; the secrets `UPDATES_SSH_KEY`,
   `UPDATES_SSH_HOST`, `UPDATES_KNOWN_HOSTS`, walked by a wizard. Check: an empty manifest is served over HTTPS.
2. **A tag publishes itself**: a workflow on release tags builds the framework package, writes the `.sha256` and
   the manifest, uploads in that order. Check: SC-001, SC-002.
3. **The updater follows the owner's answer** (FR-004), test first, in `plugins/corex-core/src/Update/`. Check:
   SC-003 on staging.
