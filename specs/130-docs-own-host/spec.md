# Feature Specification: The docs site on its own host

**Feature Branch**: `docs/public-hosts` (the spec), `spec/130-docs-own-host` (slice 1)

**Created**: 2026-11-11

**Status**: Sliced 2026-11-12 (DECISIONS #622). Slice 1 built; the switch waits on the owner's three steps; slice 2 waits on the switch

**Input**: Owner direction, 2026-11-11: CoreX's public hosts on `mustafashaaban.com` (DECISIONS #620). Step 1
of that plan moves the documentation site from `https://mustafashaaban.github.io/corex/` to
`https://corex-docs.mustafashaaban.com/`.

## Why this spec exists

The docs site has been on GitHub's project-page address since spec 090. That address carries a `/corex`
segment every link has to keep, names GitHub rather than CoreX, and is not on the domain the owner now uses for
CoreX's other hosts. GitHub Pages stays the server: it already builds the site on every push to `main` and
costs the owner's server nothing.

### What was measured on 2026-11-11

- **All six hosts resolve**, through Cloudflare's proxy (the answers are Cloudflare's addresses, so DNS alone
  cannot show where a host ends up).
- **`corex-docs.mustafashaaban.com` reaches the owner's server**, which answers `404` with "No site is
  configured for this hostname." It does not reach GitHub Pages yet.
- **The repository's Pages site has no custom domain**: build type `workflow`, address
  `https://mustafashaaban.github.io/corex/`, HTTPS enforced.
- **The `github.io` address is written in seven places** that this spec changes: `DocsUrl`'s fallback
  (`plugins/corex-config/src/Docs/DocsUrl.php`), the two tests that assert it (`DocsUrlTest`,
  `AddonsDocsLinkTest`), `docs-app/astro.config.mjs` (the build's site and base, and its comment),
  `README.md`, `docs/en/05-deployment/ci-cd.md`, and spec 111's `website` value
  (`specs/111-generator-tag/spec.md`, `contracts/detection-rule.md`). Specs 090 and 092 and earlier
  DECISIONS entries record what was true when they were written and are not rewritten.

### The switch has an order

A site built for the subpath (`/corex`) breaks when served from a domain's root, and one built for the root
breaks on the subpath: every link, asset and search index URL points at the wrong place. So the build's
address and the Pages custom domain change together, and the framework's fallback link changes only once the
new address serves the site. Released sites keep their `github.io` links, which GitHub redirects once the
custom domain is set.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - A reader opens the docs at the CoreX address (Priority: P1)

Someone types or follows `https://corex-docs.mustafashaaban.com/` and gets the documentation site: the home
page, every guide, the class reference and search, over HTTPS, with no `/corex` in any address.

**Why this priority**: It is the whole point of the move, and nothing else in this spec is useful without it.

**Independent Test**: Open the home page, three guides from different sections, one class reference page and a
search result at the new address; each loads with its styles, and every link on them stays on the new host.

**Acceptance Scenarios**:

1. **Given** the switch is done, **When** a reader opens `https://corex-docs.mustafashaaban.com/guides/media/`,
   **Then** the Image optimization guide loads with its styles and its sidebar links point at
   `https://corex-docs.mustafashaaban.com/...`.
2. **Given** the switch is done, **When** a reader searches for "Submissions", **Then** results appear and
   each opens a page on the new host.
3. **Given** the switch is done, **When** a reader opens the address over plain HTTP, **Then** they are sent
   to HTTPS.

---

### User Story 2 - Old links keep working (Priority: P1)

A link to `https://mustafashaaban.github.io/corex/...` (in a released CoreX admin, the README of an old
release, a bookmark, a search engine) still reaches the same page, now on the new host.

**Why this priority**: Every site on v0.41.0 to v0.48.1 carries these links in its admin (`DocsUrl`'s fallback,
since spec 092), and those sites will not all update soon. Breaking them would break the "Documentation" links on client sites.

**Independent Test**: Open five old addresses (the home page, a guide, a nested guide, a reference page, a page
with an anchor) and confirm each lands on the same page at the new host.

**Acceptance Scenarios**:

1. **Given** the switch is done, **When** a reader opens `https://mustafashaaban.github.io/corex/guides/media/`,
   **Then** they arrive at `https://corex-docs.mustafashaaban.com/guides/media/`.
2. **Given** a site still on v0.48.1, **When** an administrator clicks a "Documentation" link in the CoreX
   admin, **Then** the page opens, by the redirect.

---

### User Story 3 - The admin links to the new address (Priority: P2)

On a site with a release that carries this spec, every CoreX admin "Documentation" link and every add-on docs
link points straight at `https://corex-docs.mustafashaaban.com/`, with no redirect in between, unless the site
names its own docs host (`docs.base_url`), which still wins.

**Why this priority**: The redirect already keeps the links working (story 2); this removes the extra hop and
the GitHub name from new releases.

**Independent Test**: On a site with no docs host configured, read the address of a docs link in the
admin; on a site with `docs.base_url` set, read it again.

**Acceptance Scenarios**:

1. **Given** no `docs.base_url`, **When** the admin renders the Image optimization docs link, **Then** its
   address is `https://corex-docs.mustafashaaban.com/guides/media/`.
2. **Given** `docs.base_url` is `http://docs.corex.local`, **When** the same link renders, **Then** its address
   is `http://docs.corex.local/guides/media/`, as before.

---

### User Story 4 - Everything that names the docs names the new address (Priority: P3)

The README, the CI/CD guide, the build's own comments and the detection rule CoreX will submit to scanners
(spec 111) all give the new address.

**Why this priority**: Wrong but redirected addresses still work; this keeps the record honest.

**Independent Test**: Search the repository for the `github.io` address; it appears only in records of what was
true before (specs 090 and 092, earlier DECISIONS entries, the changelog) and in this spec.

**Acceptance Scenarios**:

1. **Given** this spec is merged, **When** someone searches the repository for `mustafashaaban.github.io`,
   **Then** no current instruction, test, setting or rule contains it.

### Edge Cases

- **The custom domain is set while the build is still for the subpath**: the site loads without styles for the
  minutes until the next build finishes. The switch runs the build straight after setting the domain, and the
  check in story 1 runs only after that build.
- **GitHub cannot issue its certificate**: while `corex-docs` is proxied by Cloudflare, GitHub's check for the
  domain and its certificate request may fail. The record is DNS only until GitHub shows the certificate as
  issued; whether to proxy it afterwards is decided then, with Cloudflare's SSL mode on Full (strict).
- **Someone else claims the subdomain on GitHub**: a CNAME to `github.io` with no verified domain can be taken
  over by another GitHub account. The owner verifies `mustafashaaban.com` for his GitHub account before the
  CNAME is pointed at GitHub.
- **A site names its own docs host**: unchanged (story 3, scenario 2).
- **The build is run somewhere else** (a fork, a local WAMP build): the address still comes from the two build
  settings, which default to the new host and root, and the local vhost instructions keep working with their
  own values.
- **The redirect ever stops** (the custom domain removed, the repository renamed): released sites' links break.
  The custom domain stays set for as long as any supported release carries `github.io` links.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The docs site MUST be served at `https://corex-docs.mustafashaaban.com/`, from the domain's
  root, by the existing docs workflow on GitHub Pages.
- **FR-002**: Every link, asset and search index address the build produces MUST be on the new host, with no
  `/corex` segment.
- **FR-003**: Addresses under `https://mustafashaaban.github.io/corex/` MUST reach the same page on the new host.
- **FR-004**: With no docs host configured, the framework's docs links MUST resolve to
  `https://corex-docs.mustafashaaban.com/<path>/`.
- **FR-005**: A docs host the site configures (`docs.base_url` or its filter) MUST keep winning over the
  default.
- **FR-006**: The new address MUST be served over HTTPS, and plain HTTP MUST be sent to HTTPS.
- **FR-007**: Before the CNAME points at GitHub, `mustafashaaban.com` MUST be a verified domain on the owner's
  GitHub account.
- **FR-008**: The README, the CI/CD guide, the build configuration's comments and spec 111's detection rule
  MUST give the new address; records of earlier states (specs 090, 092, earlier DECISIONS, the changelog)
  MUST NOT be rewritten.
- **FR-009**: The framework's fallback (FR-004) MUST NOT reach a release before the new host serves the site
  (story 1 passes).
- **FR-010**: The steps the owner takes outside the repository (DNS, GitHub domain verification, the Pages
  custom domain) MUST be written down in this spec's tasks in the order they run, with the check after each.

### Key Entities

- **Docs host**: the address the documentation site is served from; one default (the new host) and an
  optional per-site override.
- **Build address**: the site address and base path the docs build is made for; must match the docs host.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: `https://corex-docs.mustafashaaban.com/` and every page reached from its sidebar load with their
  styles; a link checker over the built site finds 0 links that leave the host for a docs page.
- **SC-002**: 5 of 5 sampled `github.io/corex/` addresses (home, guide, nested guide, reference page, anchor)
  land on the same page at the new host.
- **SC-003**: The docs site is unreachable for no more than 10 minutes during the switch.
- **SC-004**: `DocsUrl` resolves `/guides/media/` to `https://corex-docs.mustafashaaban.com/guides/media/`
  when no base is configured, and to the configured base when one is.
- **SC-005**: A search of the repository for `mustafashaaban.github.io` finds only the records listed in FR-008
  and this spec.

## Slices (DECISIONS #622)

The switch's hazard (§ "The switch has an order") is a build made for one address and served from another.
Slice 1 removes it: the published build reads its address from GitHub Pages itself, so the build follows the
custom domain the moment it is set, and the order of the owner's steps can no longer break the site.

### Slice 1: the build follows Pages' address, and the published site is checked (built 2026-11-12)

- `.github/workflows/docs.yml` reads the Pages address (`actions/configure-pages`: origin and base path) and
  builds for it; after every deploy it runs `scripts/check-docs-host.mjs` against the published address, and,
  once that address is not the project page, checks that each page read is reached from `github.io/corex/`.
- `docs-app/docs-address.mjs` is the one rule for the build address, read by `astro.config.mjs` and by
  `tests/docs-links.test.js`. An empty base path is the domain root (Pages' answer for a custom domain), not
  "unset". The defaults stay the project page until slice 2.
- Covers FR-001, FR-002, FR-006 (checked), SC-001, SC-002 (checked on every deploy). Changes nothing a reader
  sees while Pages has no custom domain. Safe to merge before the switch.
- Tests: `tests/docs-address.test.js`, `tests/check-docs-host.test.js`; the existing link test, run on a build
  made for the root and on one made for `/corex`.

### The switch: the owner's steps, in order, each with its check (FR-007, FR-010)

`specs/130-docs-own-host/switch.sh` walks through them and runs each check; run it from the repository root
with `gh` signed in. In order:

1. **Verify `mustafashaaban.com` on GitHub** (account Settings → Pages → Verified domains → Add a domain), add
   the TXT record GitHub shows in Cloudflare, click Verify. Check: the
   `_github-pages-challenge-MustafaShaaban.mustafashaaban.com` TXT record answers, and GitHub says verified.
2. **Point `corex-docs` at GitHub Pages** in Cloudflare: a CNAME to `mustafashaaban.github.io`, **DNS only**
   (grey cloud). Check: the public answer for `corex-docs.mustafashaaban.com` is that CNAME.
3. **Set the custom domain** on the repository's Pages site (repository Settings → Pages → Custom domain), then
   rebuild at once (`gh workflow run docs.yml`), wait for the certificate, and enforce HTTPS. Check: the Pages
   API names the domain, the certificate is `approved`, `https_enforced` is true.
4. **Check the moved site**: the rebuild's last step runs the site check with `--moved-from`. Check: the run
   passes. SC-003 (at most ten minutes unreachable) is met when steps 3 and 4 run back to back, as the script
   does; the certificate may take longer, during which the new address answers with a certificate error.

Whether to turn Cloudflare's proxy on for `corex-docs` afterwards is left for then (edge case "GitHub cannot
issue its certificate"); nothing in this spec needs it.

### Slice 2: the admin and the records name the new address (after the switch)

- `DocsUrl`'s fallback becomes `https://corex-docs.mustafashaaban.com`, with `DocsUrlTest` and
  `AddonsDocsLinkTest`; `docs-address.mjs`'s defaults become the new host and the root; the README, the CI/CD
  guide and spec 111's detection rule give the new address (FR-004, FR-005, FR-008, SC-004, SC-005).
- **Starts only after step 4 passes** (FR-009), and edits `plugins/corex-config/`, so it is claimed with
  whoever holds `plugins/` at the time.

## Assumptions

- **GitHub Pages stays the server.** It already builds the site, needs nothing from the owner's server, and
  stays up when that server does not (DECISIONS #620).
- **The owner changes DNS and GitHub's settings himself**, from Cloudflare and the repository's settings. The
  agent writes the steps and checks the result; it does not hold those accounts.
- **GitHub redirects a project page's `github.io` addresses to its custom domain**, path included. The tasks
  check this on the real site before anything relies on it (SC-002).
- **The Arabic docs (`docs/ar`) are not part of the published site** today, so the move does not touch them.
- **The other hosts are out of scope**: staging, the product site, updates and the demo are later specs
  (ROADMAP §13, "Public hosts").
