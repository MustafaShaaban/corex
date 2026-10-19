# Feature Specification: A CoreX site says it runs CoreX

**Feature Branch**: `spec/111-generator-tag`

**Created**: 2026-10-19

**Status**: Implemented

**Input**: Owner request, 2026-10-19 — "can I make CoreX appear" in Wappalyzer, the browser extension
that lists the technologies a website runs (it showed WordPress, MySQL, RankMath, React and others on
a CoreX site, and not CoreX). Asked whether the mark should carry the version, the owner answered
**name only**.

## Why this spec exists

A technology scanner recognises a platform by something the platform leaves in its pages. WordPress
leaves `<meta name="generator" content="WordPress 7.1.3">` in every page head, and that line is how
Wappalyzer reports "WordPress 7.1.3". CoreX leaves nothing of its own. Searched on 2026-10-19:

- No CoreX code prints a generator tag, an `X-Powered-By` header, or any other mark.
- The only code about generator tags removes one: the Coming soon page drops WordPress's
  (`ComingSoonHead`, spec 101), so that page does not say which WordPress it runs.
- The one thing a scanner could match today is the path of CoreX's own scripts and stylesheets
  (`/wp-content/plugins/corex-…/`). It is unreliable: a page that loads no CoreX asset has none
  (assets load only where their block is, Principle VI), and a site that bundles its assets or
  serves them from another address hides them.

Two things are needed: CoreX leaves a mark a scanner can rely on, and the scanners' lists have a rule
that reads it. The first is this framework's work. The second is a submission to the scanners'
maintainers, which this spec prepares and the owner sends.

## Clarifications

### Session 2026-10-19

- Q: Should the mark carry CoreX's version, as WordPress's does? → A: **Name only.** A version tells
  anyone looking for a vulnerable site exactly which release to try, which is the reason the Coming
  soon page already hides WordPress's. A name alone tells them only that the site is a CoreX site,
  which its asset paths already say.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — A scanner recognises a CoreX site (Priority: P1)

Someone opens a CoreX site with a technology scanner, or runs one against its address. The scanner
lists CoreX among the site's technologies, beside WordPress.

**Why this priority**: this is what the owner asked for. It makes every CoreX site public evidence
that CoreX is in use.

**Independent Test**: load any front-end page of a CoreX site and read its head: it carries one
generator mark naming CoreX, with no version. Run a scanner whose list holds the rule in
`contracts/detection-rule.md` against the page: it reports CoreX.

**Acceptance Scenarios**:

1. **Given** a site with CoreX active and any theme, **When** any front-end page is requested,
   **Then** the page head holds exactly one mark that names CoreX and no version.
2. **Given** a site whose WordPress generator tag has been removed (by a security plugin or by the
   site's own code), **When** a front-end page is requested, **Then** CoreX's mark is still there.
3. **Given** a multisite network, **When** a page of any site in it is requested, **Then** that page
   carries the mark.
4. **Given** a front-end page, **When** its head is read, **Then** no CoreX version number appears
   in the mark, in any form.

---

### User Story 2 — A developer turns the mark off for one site (Priority: P2)

A client asks that their site not say what it runs. The developer adds one line of code to the
client's site, and the mark is gone from every page.

**Why this priority**: name only removes the security reason to hide the mark, but a client may
still want their stack private. It must be possible without editing CoreX.

**Independent Test**: add a callback to the documented filter that answers "off", reload a front-end
page, and read the head: no CoreX mark. Remove the callback: the mark is back.

**Acceptance Scenarios**:

1. **Given** a site whose code answers the documented filter with "off", **When** a front-end page
   is requested, **Then** no CoreX mark is printed.
2. **Given** that filter answers "on", or nothing answers it, **When** a front-end page is
   requested, **Then** the mark is printed.

---

### User Story 3 — The Coming soon page still says nothing about what it runs (Priority: P2)

A site is in Coming soon mode. A visitor, or a scanner, loads the page.

**Why this priority**: spec 101 decided that page names no platform. This feature must not undo that.

**Independent Test**: switch a site to Coming soon and load the home page signed out: no CoreX mark
and no WordPress generator tag. Load the real site as an administrator in the same mode: the mark is
there.

**Acceptance Scenarios**:

1. **Given** a site in Coming soon mode, **When** an anonymous visitor is served the Coming soon
   page, **Then** the page carries no CoreX mark.
2. **Given** a site in Coming soon mode, **When** an administrator or a holder of the preview link is
   served the real site, **Then** the real site's pages carry the mark as they would outside the
   mode.

---

### User Story 4 — The owner submits CoreX to the scanners (Priority: P3)

The owner sends the scanners' maintainers a rule that recognises CoreX, in the format each expects,
with evidence that it matches a real site.

**Why this priority**: without the rule no scanner shows CoreX, but the rule is only reliable once
the mark ships, so it comes last.

**Independent Test**: the rule in `contracts/detection-rule.md` matches a released CoreX site's
home page, and matches a plain WordPress site's home page not at all.

**Acceptance Scenarios**:

1. **Given** a CoreX site on a release that prints the mark, **When** the rule is tested against its
   home page, **Then** it reports CoreX.
2. **Given** a WordPress site without CoreX, **When** the rule is tested against its home page,
   **Then** it reports nothing.

---

### Edge Cases

- **A theme that is not the Corex theme.** The mark comes from the framework, not the theme, so
  any theme gets it, block or classic, as long as the theme prints the standard page head.
- **A theme that never prints the standard page head.** Then no mark, the same as WordPress's own
  tag. Not CoreX's to work around.
- **The admin, the login page, feeds, REST responses, embeds.** None carries the mark. Scanners read
  public pages; WordPress's tag in feeds already names WordPress.
- **The Maintenance page and the stand-alone Coming soon page.** Both are documents CoreX writes
  whole, with no standard page head. They carry no mark, as they carry no WordPress tag today.
- **The framework's page head runs twice in one request** (a misbehaving theme or plugin). The mark
  may then appear twice, as WordPress's does. Wiring it once per request is the framework's duty;
  calls to the page head itself are the theme's.
- **A page cache.** A cached page keeps the mark it was cached with. Turning the mark off takes
  effect on cached pages when the cache is cleared, as any head change does.
- **A core provider fails to boot.** The framework logs it and carries on (spec 100). If the one
  that prints the mark is the one that failed, the page has no mark until it is fixed; nothing else
  depends on the mark.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Every front-end page that prints the standard page head MUST carry exactly one CoreX
  generator mark, `<meta name="generator" content="CoreX">`.
- **FR-002**: The mark MUST NOT contain a version number, a build number, a release name, or any
  other value that tells one CoreX release from another.
- **FR-003**: The mark MUST be printed by the framework's core, independent of the active theme
  (Principle I: the theme holds no logic; Principle II: the plugin boots itself).
- **FR-004**: The mark MUST NOT depend on WordPress's own generator tag being printed. Removing
  WordPress's tag MUST NOT remove CoreX's.
- **FR-005**: A documented filter, `corex_generator_tag`, MUST decide whether the mark is printed.
  It defaults to printing. A callback answering false MUST suppress the mark on every page of that
  site. The filter MUST be read when the page head is printed, not at boot, so a callback added by
  a theme or a late-loading plugin is honoured.
- **FR-006**: The Coming soon page served to a visitor MUST NOT carry the mark (spec 101: that page
  names no platform). The real site served through Coming soon to an administrator or a preview-link
  holder MUST carry it.
- **FR-007**: The mark MUST NOT be printed on admin screens, the login page, feeds, REST or AJAX
  responses, or embeds.
- **FR-008**: The mark's value MUST be escaped for an HTML attribute at output, though it is a
  constant.
- **FR-009**: The repository MUST hold the proposed detection rule, in the format of the open
  scanner list most tools share (`enthec/webappanalyzer`, whose format Wappalyzer's list uses), with
  the steps for submitting it to that list and to Wappalyzer (`contracts/detection-rule.md`).
- **FR-010**: The detection rule MUST recognise a CoreX site by the mark first. It MAY also match
  CoreX's asset paths as a second signal for sites on releases before the mark. It MUST NOT match a
  WordPress site without CoreX.
- **FR-011**: The operator documentation MUST say that CoreX prints the mark, that it carries no
  version, why, and how to turn it off with one line of code.

### Out of scope

- **Sending the submission.** Publishing to a third party is the owner's to do or to approve; this
  spec prepares it (FR-009).
- **A setting in the admin to turn the mark off.** The filter is enough until a client asks for a
  switch. Adding one later does not change the filter's contract.
- **An `X-Powered-By` header or any other mark.** One mark, the one scanners and WordPress already
  use.
- **The version.** Decided by the owner (Clarifications).
- **Hiding WordPress's own tag** outside Coming soon. That is the site's choice and is unchanged.

### Key Entities

- **The generator mark**: a single line in the page head that names CoreX. It has no state and
  stores nothing.
- **The detection rule**: an entry in a scanner's technology list naming the signals that identify
  CoreX, the category it belongs to, that it implies WordPress, and CoreX's public website.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: On a CoreX site, 100% of front-end pages under test carry exactly one CoreX mark, and 0
  of them carry a CoreX version.
- **SC-002**: With the filter answering off, 0 front-end pages under test carry the mark.
- **SC-003**: The Coming soon page served to a visitor carries no mark; the real site served in the
  same mode to an administrator carries it on 100% of pages under test.
- **SC-004**: The detection rule matches a CoreX site's home page and does not match a plain
  WordPress site's home page.
- **SC-005**: After the rule is accepted into a scanner's list, that scanner lists CoreX on a CoreX
  site with no other step by the site's owner.

## Assumptions

- **"CoreX" is the product's public name**, the one the admin screens use, so it is what the mark
  says and what a scanner shows.
- **The mark is the standard generator meta tag**, the one WordPress, WooCommerce and Elementor use,
  because every scanner already reads it. A site may carry several; scanners match each by its
  content.
- **Scanners accept a technology with a public website and real use.** CoreX has a public repository
  and documentation site (`https://mustafashaaban.github.io/corex/`). Whether the number of public
  CoreX sites meets a maintainer's threshold is theirs to decide; the open list asks for ten or more
  (`contracts/detection-rule.md`).
- **Wappalyzer's own list is no longer public.** Its repository was made private in 2023 and new
  technologies are suggested through its website. The open list `enthec/webappanalyzer` continues
  the old format and takes pull requests. Both are checked again before submitting, since either may
  have changed (`contracts/detection-rule.md`).
- **The filter's name follows the framework's convention** (`corex_` prefix, as
  `corex_maintenance_bypass` and `corex_cdn_provider`).
