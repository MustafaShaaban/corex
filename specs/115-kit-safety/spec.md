# Feature Specification: Kit and public page safety

**Feature Branch**: `claude/spec-115-kit-safety-88dd45`

**Created**: 2026-10-21

**Status**: Draft

**Input**: The owner approved, on 2026-10-20, the stage 0 stories of the CoreX revamp as written. This spec is the
fourth release of that stage, R0.4, and carries seven of them as its acceptance criteria: US-SAFE-07, US-SAFE-09,
US-SAFE-14, US-SAFE-15, US-FLW-02, US-AUT-02 and US-VIS-11 (the last came in through an approved change request).
The stories, their test cases and the verification behind them are kept in the owner's private planning workspace.
On 2026-10-21 the owner delegated the revamp and asked not to be asked; where this spec settles an open point it
says "decided under the owner's 2026-10-21 mandate".

## Why this spec exists

Seven places where CoreX can cost a site owner content or credibility, or turn a visitor away:

- a kit reset that deletes a page the owner had before the kit;
- a setup wizard that says it backs the site up, which it does not;
- shipped patterns that print figures, a quote and contact details nobody wrote;
- a front-page hero whose heading cannot be read and whose button goes nowhere;
- a flow editor that drops edits without a word;
- account registration and the newsletter, call request and job application routes that refuse every visitor when a
  captcha provider is configured.

Each change is small and contained. They ship together as one patch release.

This spec describes what each change does. It does not describe how the current behaviour could be misused.

## Clarifications

### Session 2026-10-21

Planning found two points the approved stories do not settle. Both are decided under the owner's 2026-10-21
mandate, with the recommended answer:

- Q: CoreX ships the newsletter signup block, but no visitor form for a call request or a job application. The
  bookings and careers add-ons are routes that a site's own form posts to. What does "the call request and job
  application forms show the provider's check" mean? → A: **CoreX gives every form that posts to a CoreX route one
  way to carry the provider's check: a server helper prints the token field and the place for the check, and the
  provider's scripts bind to any form that declares itself protected. The newsletter block uses it. The bookings and
  careers routes refuse and answer exactly like the others, and their README shows the markup a site's form uses.
  Shipping new visitor blocks for them is not part of a patch release.**
- Q: The approved story says a sample block is hidden "until an editor changes its content". How does CoreX know a
  sample was changed? → A: **A sample is a block that carries the sample marker. In the block editor, the first change
  inside it removes the marker. A page that still references a pattern, never opened in the editor, keeps the marker,
  so it stays hidden from visitors.** A page saved in the editor before this release no longer holds the marker, so it
  is not changed by this release. The release notes say so.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Reset never destroys a page the owner had before the kit (Priority: P1) — US-SAFE-07

A site owner who chose "Replace" for one of their pages when applying a kit runs a reset later and gets the page
back. Trying a kit never costs them content.

**Why this priority**: it is the only story in this release that can lose content for good.

**Independent Test**: create page "About" with content and make it the front page. Apply the Company kit with Replace
for About, then run `wp corex reset`. About exists with the same ID, URL, title and content, and is the front page
again.

**Acceptance Scenarios**:

1. **Given** a kit about to be applied, **When** apply runs, **Then** before changing anything it records, for every
   page it replaces or adopts, the page's title, content, template and status, and records the site's front-page
   and posts-page settings.
2. **Given** a replaced or adopted page with a recorded state, **When** reset runs, **Then** the page is restored to
   that state, keeps its ID and URL, and is never deleted.
3. **Given** a replaced page with no recorded state (applied before this release), **When** reset runs, **Then** the
   page is left as it is, is not deleted, and the output lists it as "kept: no earlier version recorded".
4. **Given** any kit apply, **When** reset runs as a dry run, **Then** each page is listed as created (will be
   removed), replaced (will be restored) or adopted (will be emptied), and nothing changes.
5. **Given** the site's front-page and posts-page settings recorded at apply, **When** reset runs, **Then** those
   settings are put back.
6. **Given** a page the kit created from nothing, **When** reset runs, **Then** it is removed, as today.
7. The one-click restore in the admin is not part of this story (it belongs to stage 3). This story makes the
   record and makes reset use it.

---

### User Story 2 - The setup wizard promises only what it does (Priority: P1) — US-SAFE-09

A site owner running the wizard reads true words about backups, so they take their own backup when the wizard is not
taking one for them.

**Why this priority**: an owner who believes a backup exists takes risks they would not otherwise take.

**Independent Test**: open the wizard with a plan that replaces About and adopts Contact. The Backup step says what
CoreX records, lists "About" and "Contact", recommends a full backup and keeps Apply disabled until the person
confirms one exists.

**Acceptance Scenarios**:

1. **Given** any step of the wizard, its server messages or its code comments, **When** they are read, **Then** none
   says the wizard takes or checks a backup.
2. **Given** the welcome and the Backup step, **When** they are read, **Then** they say: "Before applying, CoreX
   records the pages this plan replaces or adopts and your front-page settings, so a reset can put them back. It does
   not back up anything else."
3. **Given** a plan, **When** the Backup step is shown, **Then** it lists, by title, the pages that will be recorded.
4. **Given** the Backup step, **When** it is read, **Then** it recommends a full backup with the host or a backup
   plugin and asks the person to confirm they have one; Apply stays disabled until they do.
5. **Given** an apply request without the confirmation, **When** the server refuses it, **Then** the refusal's
   message matches the step's wording.

---

### User Story 3 - Shipped patterns make no claims for the client (Priority: P1) — US-SAFE-14

A site owner who applied a kit finds no number, quote, name, address or contact on their site that they did not
write.

**Why this priority**: invented figures published under the owner's name are false claims made to their visitors.

**Independent Test**: on a fresh CoreX theme and on a Company kit at the standard level, view Home, About and the
landing template as a visitor. No figure, quote, person, address, phone or email appears that the owner did not
write.

**Acceptance Scenarios**:

1. **Given** any shipped pattern, template or kit page, **When** it is viewed as a visitor, **Then** it shows no
   figure, quote, person, company, address, phone or email as if it were the site's own.
2. **Given** a pattern that needs an example (a figure, a quote, a list of partners), **When** an editor views it,
   **Then** it carries a "Sample: replace before publishing" label; **when** a visitor views it, **Then** nothing of
   that block is shown, until an editor changes its content.
3. **Given** a sample block, **When** an editor changes one value in it and updates the page, **Then** the block is
   shown to visitors; other sample blocks on the page that were not touched stay hidden.
4. **Given** a pattern that shows contact details, **When** it renders, **Then** they come from the site's brand
   settings, and a detail with no setting is left out.
5. **Given** any shipped pattern, **When** its markup is read, **Then** it contains no `href="#"`: each link points to
   a real address (a page of the site, or a setting), or it is shown as text, without a link.
6. **Given** a pattern edited to add an invented figure, an example.com address, a 555 number or `href="#"`, **When**
   the build checks run, **Then** they fail and name the pattern.
7. The release notes say that sites whose kit pages still reference these patterns will see the sample figures and
   quote disappear for visitors until someone edits them.

---

### User Story 4 - The front-page hero can be read and its buttons used (Priority: P1) — US-SAFE-15

A visitor reads the front page's main heading and uses its buttons, including with a keyboard.

**Why this priority**: it is the first thing a visitor sees on a CoreX theme site.

**Independent Test**: run an automated contrast and keyboard check on the front page in light and dark.

**Acceptance Scenarios**:

1. **Given** the hero, **When** it is checked in light and dark, **Then** its heading has a contrast of at least 4.5:1
   against its background.
2. **Given** the hero and CTA buttons, **When** a keyboard user presses Tab, **Then** each takes focus with a visible
   ring and is a link to a real page (the brand's primary action link, or the site's contact page). A button with no
   address to go to is not shown.
3. **Given** each button, **When** it is checked, **Then** its background differs from the section behind it by at
   least 3:1.
4. **Given** any other page with headings, **When** it is compared with the previous release, **Then** its heading
   colours are unchanged: the fix is in the pattern.

---

### User Story 5 - Edits in the flow editor are never lost without a warning (Priority: P1) — US-FLW-02

Someone building a form is told when they have unsaved changes and is stopped before anything throws them away.
They never publish, test or leave without the changes they just made.

**Why this priority**: today edits vanish three ways without a word, and Publish publishes the version without them.

**Independent Test**: change a field label, press Back to flows, choose Cancel (the edit stays), press Back again and
choose Discard (it leaves and the saved label is back). Change it again, press Publish and choose Save; the
published form shows the new label.

**Acceptance Scenarios**:

1. **Given** a flow on screen that differs from its saved draft, **When** the header is read, **Then** it shows
   "Unsaved changes"; after a successful save, or after undoing every change, it shows "All changes saved".
2. **Given** unsaved changes, **When** "Back to flows" is pressed, **Then** a question asks "Save your changes before
   leaving?" with Save, Discard and Cancel. Save saves then leaves, Discard leaves without saving, Cancel stays with the
   edits intact.
3. **Given** unsaved changes, **When** Publish, Move to draft, Close or Run marked test is pressed, **Then** the same
   question is asked; Save saves first and then runs the action on the saved version, so what is published or tested
   is what was on screen; Cancel does nothing.
4. **Given** unsaved changes, **When** the page is left by the admin menu, a link, a reload or closing the tab,
   **Then** the browser's own "Leave site?" warning appears; with no unsaved changes, there is none.
5. **Given** a save inside any of these that fails, **When** it fails, **Then** the action does not run, the edits stay
   on screen and the reason is shown.
6. **Given** the Test tab, **When** it is read, **Then** it says which version it tests ("Tests the saved draft,
   version 4").
7. **Given** the question, **When** it opens, **Then** it takes focus, works by keyboard, returns focus to the control
   that opened it on closing, and its text is translated, in light and dark, left to right and right to left.

---

### User Story 6 - Visitors can register when a captcha provider is on (Priority: P1) — US-AUT-02

A visitor to a site protected by a captcha provider sees the provider's check on the registration form and can
create an account.

**Why this priority**: today every registration is refused on such a site, for a check that is never shown.

**Independent Test**: with Turnstile's always-pass test keys, open the registration form, pass the check and press
Create account; the account is created.

**Acceptance Scenarios**:

1. **Given** Turnstile or hCaptcha configured, **When** the registration form is shown, **Then** the provider's
   check is above "Create account", and the account is created once the check is passed.
2. **Given** reCAPTCHA v3 configured, **When** the form is sent, **Then** no check is shown, a token is fetched as the
   form is sent, and the account is created when the provider accepts it.
3. **Given** a registration sent without a passed check, **When** the server answers, **Then** it is refused with a
   message saying to complete the check, and no account is created.
4. **Given** the provider refuses or cannot be reached, **When** the form is sent, **Then** it says the check failed
   and to try again, and the check is reset for the next attempt.
5. **Given** no provider, or the honeypot, **When** a visitor registers, **Then** it works exactly as today.
6. **Given** any site, **When** a page shows the registration form, **Then** the provider's script loads on that
   page, also on sites without the forms plugin, and on no other page because of this change.
7. **Given** Muva (captcha active, account add-on off), **When** its pages load, **Then** they load nothing new.
8. **Given** an Arabic site, **When** the messages show, **Then** they are translated and laid out right to left.

---

### User Story 7 - Newsletter, call request and job application forms work when a captcha provider is on (Priority: P1) — US-VIS-11

A visitor to a site protected by a captcha provider can subscribe, request a call or apply for a job after passing
the provider's check.

**Why this priority**: today each of these routes refuses every visitor on such a site.

**Independent Test**: with Turnstile test keys on a site whose mail is captured, pass the check and submit the
newsletter signup, a call request form and a job application form built with the documented markup; each succeeds
and its email is captured.

**Acceptance Scenarios**:

1. **Given** Turnstile or hCaptcha configured, **When** a newsletter, call request or job application form is shown,
   **Then** the provider's check is before its send button, and the form succeeds once it is passed.
2. **Given** reCAPTCHA v3 configured, **When** the form is sent, **Then** no check is shown, a token is fetched as the
   form is sent, and the form succeeds when the provider accepts it.
3. **Given** a form sent without a passed check, **When** the server answers, **Then** it is refused with a message to
   complete the check; nothing is stored and no email is sent.
4. **Given** the provider refuses or cannot be reached, **When** the form is sent, **Then** it says the check failed
   and to try again, and the check resets.
5. **Given** no provider, or the honeypot, **When** the three forms are sent, **Then** they work exactly as today.
6. **Given** any page, **When** it loads, **Then** the provider's script loads only where one of these forms or
   another protected form is shown; Muva's pages (captcha active, these add-ons off) load nothing new.
7. **Given** an Arabic site, **When** the messages show, **Then** they are translated and laid out right to left.
8. **Given** the newsletter footer pattern, **When** it is used, **Then** it behaves the same way (it holds the
   newsletter signup block).

### Edge Cases

- A kit is applied twice. The record made by the first apply is kept, so reset returns the page to what the owner had
  before any kit, not to the first kit's version.
- The page a recorded front-page setting points to no longer exists at reset. That setting falls back to showing the
  latest posts, as reset does today.
- A sample block is inside a page that an editor opens and saves without touching it. The marker stays and the block
  stays hidden.
- A sample block is edited in the code editor by hand. The marker is part of the markup; the person sees the label in
  the editor and can remove the marker there.
- The flow editor's save fails because the flow changed elsewhere (a version conflict). The edits stay on screen and
  the conflict's message is shown; nothing is published or tested.
- A captcha provider is chosen in Settings but its keys are not entered. As today, nothing is loaded, and the forms
  work with the honeypot only.
- A page shows two protected forms (registration and the newsletter footer). One provider script loads, and each form
  gets its own check.

## Requirements *(mandatory)*

### Functional Requirements

**Kit reset (US-SAFE-07)**

- **FR-001**: Applying a kit MUST record, before it changes a page it replaces or adopts, that page's title, content,
  template and status, unless an earlier apply already recorded it.
- **FR-002**: Applying a kit MUST record the site's front-page and posts-page settings before it changes them, unless
  an earlier apply already recorded them.
- **FR-003**: A reset MUST restore a replaced or adopted page that has a record to that record, keeping its ID and
  URL, and MUST NOT delete it.
- **FR-004**: A reset MUST leave a replaced page without a record as it is, MUST NOT delete it, and MUST list it as
  "kept: no earlier version recorded".
- **FR-005**: A reset dry run MUST list each tracked page as created (will be removed), replaced (will be restored) or
  adopted (will be emptied), before anything happens.
- **FR-006**: A reset MUST put back the front-page and posts-page settings recorded at apply.
- **FR-007**: A reset MUST remove pages the kit created from nothing, as it does today.

**Wizard wording (US-SAFE-09)**

- **FR-008**: No wizard text, server message or code comment may say the wizard takes or checks a backup.
- **FR-009**: The welcome and the Backup step MUST state exactly what CoreX records (the sentence in User Story 2).
- **FR-010**: The Backup step MUST list, by title, the pages the plan will replace or adopt, as the person has chosen
  them.
- **FR-011**: The Backup step MUST recommend a full backup with the host or a backup plugin and ask the person to
  confirm they have one; Apply MUST stay disabled until they do, and the server MUST refuse an apply without it, with
  a message that matches the step.

**Patterns (US-SAFE-14)**

- **FR-012**: A sample block MUST be hidden from visitors and shown to people who can edit it with the label "Sample:
  replace before publishing", on the site and in the block editor, until its content is changed.
- **FR-013**: The block editor MUST remove a block's sample marker on the first change to its content.
- **FR-014**: The stats, testimonial and logo cloud patterns MUST be sample blocks.
- **FR-015**: Contact details in shipped patterns MUST come from the brand settings (email, phone, address); a detail
  with no setting MUST be left out.
- **FR-016**: No shipped pattern may contain `href="#"`. A link MUST point to a page of the site or a setting, or be
  shown as text.
- **FR-017**: An automated check MUST fail when a shipped pattern contains, outside a sample block, an invented figure,
  an example.com address, a 555 number or `href="#"`, and MUST name the pattern.

**Hero (US-SAFE-15)**

- **FR-018**: The hero heading MUST have a contrast of at least 4.5:1 against the hero's background, in light and dark.
- **FR-019**: The hero and CTA buttons MUST be links to the brand's primary action link or the site's contact page,
  and MUST be left out when neither exists; they MUST take keyboard focus with a visible ring.
- **FR-020**: Each button's background MUST differ from its section's by at least 3:1.
- **FR-021**: The fix MUST be in the patterns; heading colours elsewhere on the site MUST NOT change.

**Flow editor (US-FLW-02)**

- **FR-022**: The editor MUST know whether the flow on screen differs from its saved draft and show "Unsaved changes"
  or "All changes saved" in its header.
- **FR-023**: Back, Publish, Move to draft, Close and Run marked test MUST ask "Save your changes before leaving?"
  (Save, Discard, Cancel) when there are unsaved changes. Back offers all three; the others offer Save and Cancel and
  run their action on the saved version after a successful save.
- **FR-024**: Leaving the page with unsaved changes MUST trigger the browser's leave warning; with none, it MUST NOT.
- **FR-025**: A failed save MUST stop the action, keep the edits and show the reason.
- **FR-026**: The Test tab MUST name the version it tests.
- **FR-027**: The question MUST take focus on opening, close on Escape as Cancel, return focus to the control that
  opened it, and be translatable and right-to-left ready.

**Captcha on CoreX forms (US-AUT-02, US-VIS-11)**

- **FR-028**: CoreX MUST give any form that posts to a CoreX route one way to carry the configured provider's check:
  the token field and, for Turnstile and hCaptcha, the place where the check is drawn.
- **FR-029**: The provider's scripts MUST load on a page only when a form on it declared itself protected (a corex-forms
  form, or one using FR-028), whether or not the forms plugin is active.
- **FR-030**: The provider's scripts MUST bind only to CoreX forms and to forms that declared themselves protected; a
  form that does neither (such as Muva's own forms) MUST NOT be touched.
- **FR-031**: The account registration form and the newsletter signup block MUST use FR-028 and send the token.
- **FR-032**: The registration, newsletter, call request and job application routes MUST keep refusing a missing or
  refused check when a provider is configured, with a translated message: "Please complete the check before
  sending." when no token was sent, "The check failed. Please try again." when the provider refused it. They MUST
  store and send nothing in either case.
- **FR-033**: A form whose check was spent or refused MUST reset the check for the next attempt.
- **FR-034**: With no provider, or the honeypot, the four forms MUST work exactly as before.
- **FR-035**: The bookings and careers READMEs MUST show the markup a site's own form uses to carry the check.

### Key Entities

- **Page record**: what a page was before a kit changed it: title, content, template, status. Kept on the page itself,
  written once, read by reset.
- **Front-page record**: the site's front-page and posts-page settings before the kit. Kept with the kit's other
  settings, and removed by reset with them.
- **Sample marker**: a mark on a block saying that its content is an example. The block editor removes it on the first
  change.
- **Protected form declaration**: a form saying, as it renders, that it needs the provider's check, so the page loads
  the provider's script.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: After apply-with-Replace and reset, 100% of the owner's replaced pages exist with their earlier title,
  content, template and status, and the front page is the one the owner had.
- **SC-002**: No wizard text, server message or code comment in the kit add-on contains a claim that a backup is taken.
- **SC-003**: Zero invented figures, quotes, example addresses, 555 numbers or `href="#"` render for a visitor on a
  fresh CoreX theme and on a Company kit site; the build check fails within one run when one is added.
- **SC-004**: The front-page hero heading measures at least 4.5:1, and each of its buttons at least 3:1 against its
  section and reachable by Tab, in light and dark.
- **SC-005**: In the flow editor, no path (Back, Publish, Move to draft, Close, Run test, leaving the page) loses an
  edit without a question or warning.
- **SC-006**: With Turnstile's always-pass test keys, registration and the newsletter signup succeed on the first
  attempt; with no token, 100% are refused and nothing is stored or mailed.
- **SC-007**: Muva's pages load the same scripts before and after this release.

## Assumptions

- Neither client runs the CoreX theme, the Company kit, reset or the account add-on (verified read-only). Muva runs
  corex-captcha with reCAPTCHA v3 and draws its own forms; Perego does not run corex-captcha. Perego sees the changed
  corex-ui patterns in its inserter only.
- The newsletter signup block lives in corex-blocks, which every client loads; it changes only on pages that show it.
- WordPress revisions of a page are left as they are: restoring a page saves a new revision, as any update does.
- The block editor and the site editor share one editor script, so templates edited in the site editor behave like
  pages.
- The one-click undo of a kit in the admin (stage 3), and visitor blocks for call requests and job applications, are
  out of scope.
