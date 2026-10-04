# Feature Specification: Coming soon is a mode, not a maintenance page

**Feature Branch**: `spec/101-coming-soon-mode`

**Created**: 2026-10-04

**Status**: Draft

**Input**: Owner request — a client site launches with a coming-soon page while the real site is
built behind it, and the site needs a function to turn that page on and off. Confirmed with the
owner: administrators and holders of a secret preview link see the real site; the page is an
indexable launch page at the home URL, and every other URL redirects to it.

## Why this spec exists

Spec 063 listed coming-soon among the Operations Mode states the Overview had to be truthful about
— it appears four times in that spec and three in its tasks. Spec 065 then shipped mode switching
with four modes, and coming-soon was not one of them. Nothing recorded the drop. What the
repository holds today is:

- `OperationsMode::all()` returns development, staging, production and maintenance.
- `tests/Unit/Operations/OperationsModeTest.php` asserts that `coming-soon` is **not** a valid mode.
- `theme/patterns/maintenance.php` describes itself as "a centered maintenance/coming-soon notice",
  which is the only place the two are treated as the same thing.

They are not the same thing, and Maintenance mode cannot stand in for it:

**Maintenance says "temporarily broken", and a launch page is neither.** `MaintenanceGuard` answers
every anonymous front-end request with a 503 and `Retry-After: 3600`. That is correct for an outage
measured in minutes. A coming-soon page stays up for weeks, and a 503 held that long tells a search
engine the site is failing — the opposite of introducing a brand before launch.

**The maintenance page is CoreX's, and a launch page is the client's.** The 503 body is a fixed
card built by `StandalonePage`. A client cannot design it, an editor cannot change it, and it cannot
hold a form.

**Only an administrator can see past it.** That is the right lockout rule for maintenance. Before a
launch, the people who most need to see the unfinished site are the client's own stakeholders, who
have no WordPress account and should not be given an administrator one to look at a page.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — An operator turns the coming-soon page on and off (Priority: P1)

The operator opens Operations & Security, chooses Coming soon, acknowledges that visitors are
affected, and applies it. To launch, they choose Production and go through the launch confirmation
that already exists.

**Why this priority**: this is the function the owner asked for. Everything else describes what the
switch does.

**Independent Test**: on a site in Development, switch to Coming soon and load the home page in a
signed-out browser — the coming-soon page is served. Switch to any other mode and reload — the real
site is served. The mode history shows both changes with the operator's name.

**Acceptance Scenarios**:

1. **Given** a site in any other mode, **When** the operator selects Coming soon and ticks the
   acknowledgement, **Then** the mode is saved, recorded in the history, and anonymous visitors
   receive the coming-soon page from the next request.
2. **Given** Coming soon is selected, **When** the operator submits without the acknowledgement,
   **Then** nothing changes and the form returns with Coming soon proposed and its acknowledgement
   on screen.
3. **Given** a site in Coming soon, **When** the operator switches to Production, **Then** the
   existing launch confirmation and readiness result apply unchanged, and on success visitors
   receive the real site.
4. **Given** a site already in Coming soon, **When** the operator applies Coming soon again,
   **Then** nothing is written and the screen says nothing changed.

---

### User Story 2 — A visitor sees the client's page, and nothing else (Priority: P1)

**Why this priority**: the page is the public face of the site until launch, and a leak of the
unfinished site is the failure this mode exists to prevent.

**Independent Test**: signed out, request the home URL and three other URLs (a published page, a
post, a URL that does not exist). Home answers 200 with the coming-soon page. Each of the others
answers with a temporary redirect to home.

**Acceptance Scenarios**:

1. **Given** Coming soon is on, **When** an anonymous visitor requests the home URL, **Then** the
   response is 200 and the body is the coming-soon page rendered by the active theme.
2. **Given** Coming soon is on, **When** an anonymous visitor requests any other front-end URL,
   **Then** the response is a temporary redirect to the home URL and no part of the requested page
   is sent.
3. **Given** Coming soon is on, **When** a form on the coming-soon page submits, **Then** the
   submission is processed normally.
4. **Given** Coming soon is on and WordPress is not set to discourage search engines, **When** a
   crawler requests the home URL, **Then** nothing in the response asks it not to index the page.

---

### User Story 3 — An administrator keeps working on the real site (Priority: P1)

**Why this priority**: a switch that locks the builder out of what they are building cannot be
turned on.

**Independent Test**: signed in as an administrator with Coming soon on, browse the front end — the
real site is served on every URL, and a visible notice says visitors currently see the coming-soon
page. Follow the notice's link to see the coming-soon page itself.

**Acceptance Scenarios**:

1. **Given** Coming soon is on, **When** a signed-in administrator requests any front-end URL,
   **Then** the real site is served.
2. **Given** Coming soon is on, **When** a signed-in administrator views the front end or the admin,
   **Then** a persistent notice states that Coming soon is on and links to Operations & Security.
3. **Given** Coming soon is on, **When** an administrator asks to preview the coming-soon page,
   **Then** they see exactly what an anonymous visitor at the home URL sees.

---

### User Story 4 — A stakeholder reviews the site through a link (Priority: P2)

The operator copies a preview link from Operations & Security and sends it to the client. The
client opens it and browses the real site in that browser, without an account.

**Why this priority**: it is what makes Coming soon usable on a real client project, but the mode
works without it.

**Independent Test**: open the preview link in a signed-out browser — the real site is served and
stays served while browsing. Regenerate the link, reload in the first browser — the coming-soon
page is served.

**Acceptance Scenarios**:

1. **Given** a valid preview link, **When** it is opened, **Then** that browser is served the real
   site on every front-end URL for a bounded period, and the address bar no longer shows the secret.
2. **Given** the operator regenerates the link, **When** a browser that used the old link makes its
   next request, **Then** it is treated as an anonymous visitor.
3. **Given** an invalid or revoked link, **When** it is opened, **Then** the visitor is treated as
   anonymous and nothing reveals whether the link was ever valid.
4. **Given** a browser with preview access, **When** it requests anything under the admin, **Then**
   it has no more access than an anonymous visitor has.

---

### User Story 5 — A deployer reads and sets the mode from the command line (Priority: P2)

**Why this priority**: a deploy script has to be able to put a fresh production install into Coming
soon before anyone visits it, and an operator who cannot reach the screen needs a way back.

**Independent Test**: from the command line, read the current mode, set Coming soon, confirm it in
a signed-out browser, set it back, and confirm the history records each change.

**Acceptance Scenarios**:

1. **Given** any mode, **When** the deployer reads the mode from the command line, **Then** the
   current mode and whether it was declared or inherited are reported.
2. **Given** any mode, **When** the deployer sets Coming soon from the command line with its
   explicit confirmation, **Then** the result is identical to applying it on the screen, including
   the history entry.
3. **Given** a mode that needs a confirmation, **When** it is set from the command line without
   one, **Then** nothing changes and the command exits with a failure.

---

### User Story 6 — A new client site starts with a page it can edit (Priority: P2)

**Why this priority**: without it every client site begins by discovering the template name.

**Independent Test**: generate a client site, activate its theme, turn Coming soon on — the page
served is the one in the client theme, and it can be opened and changed in the Site Editor.

**Acceptance Scenarios**:

1. **Given** a freshly generated client theme, **When** Coming soon is on, **Then** the page served
   comes from the client theme's coming-soon template.
2. **Given** a client theme with no coming-soon template, **When** Coming soon is on, **Then** the
   CoreX parent theme's default coming-soon template is served.
3. **Given** an active theme that provides no coming-soon template by any route, **When** Coming
   soon is on, **Then** a self-contained CoreX page is served with a 200 status, and the mode still
   behaves as specified.

### Edge Cases

- **The login route, including a custom login address.** Never intercepted, so signing in, signing
  out and resetting a password work while Coming soon is on.
- **`robots.txt`.** Served normally. A redirect there would hide the file crawlers are asked to read.
- **Feeds and sitemaps.** They do not list or serve anything other than the home URL while the mode
  is on.
- **The home URL with a query string** (a campaign tag, for example). Still the coming-soon page,
  with no redirect loop.
- **A site whose front page is a static page, or one that redirects.** The home URL serves the
  coming-soon page regardless.
- **A multilingual site.** Each language's home URL is a home URL for this purpose.
- **A page cache or CDN in front of the site.** Responses to administrators and preview-link
  holders, and the redirects, are marked non-cacheable, so a cache cannot hand the real site to an
  anonymous visitor or the coming-soon page to an administrator.
- **A multisite network.** The mode is per site, as it already is.
- **Content published in WordPress is still readable through the REST API**, as on any WordPress
  site. This mode hides the front end. It is not a confidentiality control, and the documentation
  says so: content that must not be seen before launch stays in draft.
- **A client site needs one more public URL** (a privacy policy linked from the page). A documented
  extension point lets the client plugin allow specific requests through.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Coming soon MUST be a selectable Operations Mode alongside development, staging,
  production and maintenance, persisted and audited by the same store as the others.
- **FR-002**: Switching to Coming soon MUST require a ticked acknowledgement that visitors are
  affected. Switching away from it MUST follow the target mode's existing rules unchanged.
- **FR-003**: The mode's description, consequences and warnings on the Operations screen and the
  Overview MUST state what the code does — the status served, who passes, what is never intercepted
  — and nothing it does not.
- **FR-004**: While the mode is on, an anonymous request for the home URL MUST receive the
  coming-soon page with a 200 status.
- **FR-005**: While the mode is on, an anonymous request for any other front-end URL MUST receive a
  temporary redirect to the home URL. A permanent redirect MUST NOT be used.
- **FR-006**: The admin, the login route, the REST API, AJAX and cron MUST never be intercepted.
- **FR-007**: A signed-in administrator MUST always be served the real site. No configuration of
  this feature may lock an administrator out.
- **FR-008**: The coming-soon page MUST be supplied by the active theme through a named template
  that a child theme can override and the Site Editor can edit. The CoreX parent theme MUST ship a
  default. The mode's logic MUST NOT live in the theme (Principle I).
- **FR-009**: When no theme template is available, the mode MUST still work, serving a
  self-contained CoreX page (Principle II).
- **FR-010**: The response for the coming-soon page MUST NOT carry a noindex signal unless
  WordPress's own search-engine visibility setting asks for one.
- **FR-011**: An operator MUST be able to obtain one secret preview link, and to regenerate it. A
  valid link MUST grant the browser that opens it front-end access to the real site for a bounded
  period, and MUST then be removed from the visible address.
- **FR-012**: Regenerating the link MUST revoke every access granted by the previous one.
- **FR-013**: Preview access MUST grant no capability. It MUST NOT be stored in a form from which
  the secret can be read back out of the database.
- **FR-014**: An invalid link MUST be indistinguishable, to the visitor, from no link.
- **FR-015**: Responses that depend on who is asking — to administrators, to preview holders, and
  the redirects — MUST be marked non-cacheable.
- **FR-016**: A signed-in administrator MUST see a persistent notice, on the front end and in the
  admin, whenever Coming soon is on, with a route to the screen that changes it and a way to view
  the coming-soon page as a visitor sees it.
- **FR-017**: The mode MUST be readable and settable from the command line, with the same
  confirmation rules and the same history entry as the screen. A change from the command line MUST
  NOT bypass the acknowledgement.
- **FR-018**: A documented extension point MUST let client code allow specific requests through,
  matching the one Maintenance mode already has.
- **FR-019**: `robots.txt` MUST be served normally. Feeds and sitemaps MUST NOT expose any URL
  other than home while the mode is on.
- **FR-020**: `make:site` MUST generate an editable coming-soon template in every new client theme.
- **FR-021**: Every user-facing string MUST be translatable, and the default page and the notice
  MUST be correct in RTL and meet WCAG 2.2 AA.
- **FR-022**: The test asserting that `coming-soon` is an invalid mode MUST be replaced by tests of
  the mode, not deleted.
- **FR-023**: The operations guide (EN and AR) MUST document the mode, the preview link, the
  command, the template name, the cache note and the REST limitation in the same change.

### Out of scope

- A countdown, a launch date, or automatic launch at a scheduled time.
- A built-in email-capture form. The template can hold a CoreX form or the newsletter block.
- Password-protecting the site, or hiding content from the REST API.
- A list of public URLs managed from the admin. The extension point in FR-018 covers the need.
- Any change to Maintenance mode's behaviour.

### Key Entities

- **Operations mode**: the site's single declared state. Gains one value. Exactly one mode is in
  force, so Coming soon and Maintenance cannot both be on.
- **Preview access**: one secret per site, held only in a non-reversible form, with the time it was
  issued. Replaced, never accumulated.
- **Coming-soon template**: a theme template with a fixed name, owned by presentation.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An operator turns the coming-soon page on, and off again, in under one minute from
  the Operations screen, without touching code.
- **SC-002**: With the mode on, 100% of anonymous requests to non-home front-end URLs under test
  are redirected, and 0 bytes of the requested page are served.
- **SC-003**: With the mode on, an administrator reaches every front-end URL under test, and the
  notice is present on each.
- **SC-004**: A stakeholder with the link reaches the real site with no account, and loses access
  within one request of the link being regenerated.
- **SC-005**: A newly generated client site serves its own coming-soon template with no step other
  than activating the theme and switching the mode.
- **SC-006**: Maintenance mode's existing tests pass unchanged.
- **SC-007**: The page and the notice pass the browser suite in LTR and RTL, light and dark, at
  mobile and desktop widths.

## Assumptions

- **A mode rather than a separate switch.** Spec 063 named it as a mode, there is one place an
  operator already goes to change public behaviour, and making it a mode means Coming soon and
  Maintenance cannot be on together. The cost is that a site in Coming soon is not also labelled
  staging or production; the Overview already reports the hosting environment separately.
- **Launch is the existing production switch.** Leaving Coming soon for Production reuses the
  typed-phrase confirmation and readiness result rather than adding a second launch flow.
- **Administrator means `manage_options`**, as it does for Maintenance mode.
- **One preview link per site** is enough. Per-person links with individual revocation are not
  needed for a pre-launch review and can be added without changing this contract.
- **Bounded period** for preview access defaults to 14 days, renewed by opening the link again.
- **WordPress's "discourage search engines" setting** is the control for a client who wants the
  page hidden until launch. This spec adds no second one.
