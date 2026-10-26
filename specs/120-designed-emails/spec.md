# Feature Specification: Emails that send what was designed

**Feature Branch**: `spec/120-designed-emails`

**Created**: 2026-10-23

**Status**: Draft

**Input**: Revamp release R3.11 of the CoreX revamp, seven stories: US-EML-01 (default templates), US-EML-02 (true
preview and test send), US-EML-03 (a template's From and plain text are sent), US-EML-05 (one frame for every CoreX
email), US-EML-07 (every variable has a value or is flagged), US-EML-15 (designed password reset and new-member emails)
and US-EML-19 (a finished message on the shared mailer can carry a plain-text version, change request CR-013). The
owner delegated the revamp on 2026-10-21; the decisions that shape these stories (EML-D4, EML-D5, EML-D10, EML-D11)
were taken under that mandate with the recommended answers, quoted under Clarifications. The stories, their 42 test
cases and the verification behind them are kept in the owner's private planning workspace. It builds on spec 113
(mail capture, R0.2).

## Why this spec exists

Email Studio shows one email and sends another. Its preview is put together in the browser with "Sample key" values
and without the frame the server adds; a test send delivers the typed HTML with `{{ site.name }}` still in it; a routed
email drops the template's From name, From address and plain text. CoreX draws its emails in two frames (Studio and
code templates in one, replies in another), the Mail settings reach replies only, the default layouts hide the logo and
lose the accent colour, and some notifications have no frame at all. A template cannot use a form's own fields, and a
variable a flow never supplies goes out blank without a warning. A new site has no template, so a new flow sends no
email at all. The account add-on sends WordPress's plain-text password emails. And client code that renders its own
text version of an email cannot hand it to the shared mailer.

This release makes the preview and the test send the real email, sends what a template says it sends, draws every
CoreX email in one frame with the site's logo, colour and footer, flags variables before an email goes out blank,
ships editable default emails, designs the account emails, and lets a finished message carry its own text version.
Messages that client code hands over finished are sent exactly as before (SAFE-D2).

## Clarifications

### Session 2026-10-21 (the owner's mandate; recommended answers recorded in the planning workspace)

- Q (EML-D4): One email frame, and a visual layout builder? → A: **One frame now; the visual builder later.**
- Q (EML-D5): Ship editable default templates? → A: **Yes.**
- Q (EML-D10): Variables: any submission field and an all-fields block? → A: **Yes.**
- Q (EML-D11): Design the password reset and new-member emails now? → A: **Yes.**
- Q (SAFE-D2, the owner, 2026-10-19): → A: **Production delivery on the shared mailer does not change.**

### Session 2026-10-23 (gaps the stories leave, decided under the same mandate; DECISIONS #450 to #458)

- Which mail the account and newsletter defaults go through → the shared mailer, with the delivery rule every other
  CoreX email on it follows, so a production site sends them as it sends WordPress's own today (#456).
- When "a site that turns the defaults off still notifies its admin" applies to a flow → when the flow's team
  notification is bound to a template that has no active version; a binding the owner switched off sends nothing, as it
  does today, so no upgraded site gains an email (#455).
- Where the newsletter's "preferences" link goes → only where the site gives one: corex-newsletter has no preferences
  page, and a link to a page that does not exist is not shipped (#450).
- Which password resets are drawn in the frame → those asked through the account block, whose page the link returns
  to; a reset asked on the login screen stays WordPress's (#457, as DECISIONS #341 already set for the link).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Preview and test send show the email that will be sent (US-EML-02, Priority: P1)

Someone editing a template sees, on the Preview tab, the email the server will send: framed, with every variable filled
with a believable sample. The test send sends exactly that email.

**Why this priority**: what an editor approves must be what recipients get; every later story is checked through it.

**Independent Test**: a template using `site.name`, `submission.name` and the header partial; open Preview: the framed
email with the site's real name and "Jane Cooper", the sample values listed beside it. Test send to an address on a
development site: captured, and the captured HTML and plain text equal the preview, with "[Test]" before the subject.

**Acceptance Scenarios**:

1. **Given** a template, **When** the Preview tab is opened, **Then** it shows the email drawn on the server by the code
   that sends it: the draft being edited, its layout, partials and frame, with a sample for every variable ("Jane
   Cooper", "jane@example.com", the site's real name and address).
2. **Given** the preview, **When** a sample value beside it is changed, **Then** the preview is drawn again with it, and
   nothing is saved.
3. **Given** the preview, **When** Desktop, Mobile or Right to left is chosen, **Then** the same server-drawn email is
   shown at 600px or 375px, and right to left with the site's direction rules.
4. **Given** a development site, **When** a test is sent, **Then** exactly the previewed subject (with "[Test]"), HTML
   and plain text is captured; on a production site with no provider it is blocked.
5. **Given** a test send, **When** it finishes, **Then** the result says in words what happened ("Captured: nothing
   left this site", "Delivered to the mail server", "Blocked: no mail provider is set"), the reason, and a link to its
   row in the log.
6. **Given** `not-an-address`, **When** Test send is pressed, **Then** it is refused before sending, with the reason.
7. **Given** a template with an unknown variable or a missing partial, **When** Preview is opened, **Then** an error
   naming the variable or the partial takes the preview's place and Test send is not offered.
8. **Given** any test send, **Then** it never uses a real submission's data.

---

### User Story 2 - A template email is sent from the address and with the plain text it was given (US-EML-03, P1)

**Why this priority**: the editor's From and plain-text fields must not be decoration.

**Independent Test**: a template with From "Sales" `sales@site.test` and a hand-written plain text, bound to a flow, on
a site that delivers: the message leaves from `Sales <sales@site.test>` with that text as its text/plain part.

**Acceptance Scenarios**:

1. **Given** a template with From name and address, **When** it is sent by a flow binding, a route or a resend,
   **Then** it leaves from them; with both empty it leaves from Settings, Mail's sender. The From address is optional.
2. **Given** a template, **When** it is sent, **Then** it carries a plain-text part: the hand-written one, or one made
   from the HTML that keeps paragraphs, line breaks, list items and each link's address.
3. **Given** the Plain text tab, **When** it is opened, **Then** it shows that same text, labelled "Sent as the
   plain-text version".
4. **Given** an answer "under <500 EGP", **When** it is merged, **Then** the plain text holds it whole, as the HTML does.
5. **Given** a From address that is not an address, or a From name with a line break, **When** the template is saved,
   **Then** it is refused with the reason; a From address on another domain than the site's shows Email Studio's
   transport warning.
6. **Given** a Studio email, **When** it is sent, **Then** its From and plain text pass the header checks every other
   CoreX email passes.
7. **Given** a message handed to the shared mailer finished, **Then** it is not changed: no plain part is added and its
   own From stays.

---

### User Story 3 - Every CoreX email wears one frame (US-EML-05, P1)

A form notification, a newsletter email, a template email and a reply arrive in the same design with the site's logo,
colour and footer.

**Why this priority**: the defaults (story 4) and the account emails (story 6) are drawn in this frame.

**Independent Test**: with a logo, accent and footer set in Settings, Mail on a capturing site, send a reply, a contact
notification, a newsletter confirmation and a Studio template email: all four are in the same frame with the logo,
accent and footer; the newsletter one has an unsubscribe link.

**Acceptance Scenarios**:

1. **Given** any email CoreX draws (Studio template, code template, notification, newsletter mail, reply), **Then** it
   is drawn in one frame: the site's logo or name in the header, the accent colour, the message on a card, and a footer
   with the site's name, a line saying why the person receives the email, and the links the email needs (privacy where
   the site has a privacy page; unsubscribe, and preferences where the site offers a page for it, on newsletter mail).
2. **Given** a logo, accent and footer line in Settings, Mail, **Then** they apply to every one of those emails; with
   nothing set the logo is the site's logo, the accent is the brand's primary colour and the footer names the site.
3. **Given** a Studio layout, **Then** it arranges the body inside the frame and is not a second frame; a region that
   only repeats the site's name is not drawn twice.
4. **Given** the frame, **Then** it is email-client safe (tables, inline styles, at most 600px), has 4.5:1 contrast for
   text in light and dark, uses the system font stack, and runs right to left with the site.
5. **Given** a design registered on `corex_email_design`, **Then** it keeps drawing replies exactly as today, and is
   given another kind of email only if it says it accepts that kind.
6. **Given** a message handed over finished, **Then** it is sent as given, with no frame.
7. **Given** CoreX Mail inactive, **When** a flow or a code-defined form is submitted, **Then** the notification is
   plain text with one answer per line.
8. **Given** the release notes, **Then** they show before and after for each kind of email.

---

### User Story 4 - A new site starts with editable default emails (US-EML-01, P2)

**Independent Test**: a fresh installation has "Contact confirmation", "Admin notification", "Double opt-in" and
"Newsletter welcome", active and framed; a new flow with an email field, submitted on a site that delivers, sends the
admin "Admin notification" (the form's title, a link to the submission) and the submitter "Contact confirmation".

**Acceptance Scenarios**:

1. **Given** a new installation, **Then** Email Studio has the four templates, active, in the frame, in the site's
   language where CoreX has a translation, with the words and structure of the "Corex Email Templates" design.
2. **Given** a new flow, **Then** its Emails tab has "Admin notification" bound to the team and "Contact confirmation"
   to the submitter, selected and switchable off; the admin notification names the form by its title and links to the
   submission.
3. **Given** the newsletter add-on, **When** someone subscribes, **Then** "Double opt-in" is sent when it is active, with
   a confirm button and a plain link to paste; **When** they confirm, **Then** "Newsletter welcome" is sent when it is
   active, and nothing when it is not.
4. **Given** a default template, **Then** it can be edited, previewed and test-sent like any other, and reset to the
   shipped version after a confirmation; the reset is a new version and the edit stays in its history.
5. **Given** a site upgrading from an earlier release, **Then** the four templates arrive as drafts bound to nothing,
   and every email the site already sends is sent as before; the release notes say how to switch them on.
6. **Given** a flow whose team notification is bound to a template with no active version, or a code-defined contact
   form with no route, **Then** the PHP contact notification is sent to the admin.

---

### User Story 5 - Every variable either has a value or is flagged before sending (US-EML-07, P2)

**Independent Test**: a flow with a `message` field; `{{ submission.message }}` saves and its value is in the sent
email; `{{ form.answers }}` lists every answer with its label in the form's order; `{{ user.name }}` bound to the flow
warns and needs a confirmation; `{{ nonsense.key }}` is refused on save, named.

**Acceptance Scenarios**:

1. **Given** a template, **Then** it can use `submission.<field>` for any field and an "All answers" block that lists
   every answer with its label in the form's order, with a file answer as a link.
2. **Given** the Variables tab, **Then** it shows the variables grouped by the emails that supply them, each with a
   sample value.
3. **Given** a template that uses a variable the bound email does not supply, **When** it is saved, **Then** a warning
   names it ("user.name is not available in a form notification") and the save goes through; **When** it is bound to a
   flow, **Then** the binding is refused until the variable is removed or the warning is confirmed.
4. **Given** a variable with no value when the email is sent, **Then** it is left out cleanly (no empty label, no
   braces) and the log entry for that email names it.
5. **Given** an unknown variable, **When** the template is saved, **Then** it is refused with a message naming it.
6. **Given** any value, **Then** it is escaped as today; a variable never inserts HTML.

---

### User Story 6 - Password reset and new-member emails are designed (US-EML-15, P3)

**Independent Test**: with the account add-on active and the login address hidden, ask for a reset on the account page:
a framed email with a button, a plain link, how long the link works and what to do if you did not ask, and no login
address. Register choosing a password: a framed welcome that links to the account page and has no set-password link.

**Acceptance Scenarios**:

1. **Given** a reset asked through the account block, **Then** the email is in the one frame, says which account it is
   for and when it was asked, has a button and a plain link to the account page's reset form, says how long the link
   works and what to do if the person did not ask.
2. **Given** a registration with a chosen password, **Then** the new member receives a welcome email linking to the
   account page, with no "set your password" link.
3. **Given** either email, **Then** it does not contain the site's hidden login address.
4. **Given** Email Studio, **Then** both are templates ("Password reset", "Welcome to your account") that can be
   edited, previewed and test-sent, with the variables they need listed; a broken template falls back to WordPress's
   own email, and the activity log says so.
5. **Given** an Arabic site, **Then** both are in Arabic, right to left, with a plain-text part.
6. **Given** a development site, **Then** both are captured.

---

### User Story 7 - A finished message on the shared mailer can carry a plain-text version (US-EML-19, P3)

**Independent Test**: on a capturing site, a finished message with HTML and a text version is captured with both parts,
each as given, and Email Studio's log shows the text version; one built the old way is captured exactly as before.

**Acceptance Scenarios**:

1. **Given** a finished message with a plain-text version, **Then** it is sent with both, each exactly as given.
2. **Given** one without, **Then** it is sent exactly as today; CoreX adds no text part of its own.
3. **Given** existing callers, **Then** they work unchanged: the field is optional and comes last.
4. **Given** an empty plain-text value, **Then** it is treated as none.
5. **Given** a development or staging site, **Then** the captured email keeps the text version and Email Studio's view
   of it shows it.
6. **Given** the mailer's developer documentation, **Then** it shows the field with an example.

### Edge Cases

- A preview requested while the draft has an unsaved syntax error answers the error, not a stale preview.
- A sample value containing markup is shown as text in the preview, as a real value would be.
- A test send on a site whose mode changes between preview and send follows the mode in force when it is sent.
- A template whose layout revision was removed falls back to drawing the body in the frame without arrangement, and the
  preview says which layout is missing.
- A logo that is an SVG is not drawn (mail clients do not show it); the site's name stands in.
- A site design that accepts a kind but fails while drawing falls back to the standard frame and is reported, as for
  replies today.
- A submitter confirmation whose form has no email field, or whose submitter left it empty, is skipped and recorded as
  "no recipient", not as a failed email.
- A default template slug already used by a template an operator made is left alone; the default is not added twice.
- A reset asked for an account that does not exist sends nothing and says the same as for one that does.
- A finished message whose text version is whitespace only is treated as having none.

## Requirements *(mandatory)*

### Functional Requirements

**Preview and test send (Story 1)**

- **FR-001**: Email Studio MUST draw a template's preview on the server, with the code that sends it, from the draft
  being edited (saved or not), its layout, its partials and the frame.
- **FR-002**: Every variable MUST have a believable sample value; the samples MUST be listed beside the preview and be
  editable for that preview only.
- **FR-003**: The preview MUST be shown at 600px (Desktop) and 375px (Mobile), and right to left with the site's
  direction rules.
- **FR-004**: A test send MUST send the previewed email (subject marked "[Test]", HTML and plain text) to one typed
  address, under the site's delivery rules, and never with a real submission's data.
- **FR-005**: The test result MUST say what happened in words, give the reason, and link to its row in the log.
- **FR-006**: An invalid address MUST be refused before sending, with the reason; a template that cannot be drawn MUST
  show an error naming the variable or partial and MUST NOT be test-sent.

**From and plain text (Story 2)**

- **FR-007**: An email sent from a template (flow binding, route, inbox or log resend, test send) MUST leave from the
  template's From name and address when set, and from Settings, Mail's sender otherwise; the From address is optional.
- **FR-008**: It MUST carry a plain-text part: the hand-written text, or one made from the HTML keeping paragraphs,
  line breaks, list items and link addresses; a merged value MUST appear whole in it.
- **FR-009**: The Plain text tab MUST show the text that is sent, labelled "Sent as the plain-text version".
- **FR-010**: Saving a template MUST refuse an invalid From address or a From name with a line break, with the reason,
  and MUST warn when the From address is on another domain than the site's.
- **FR-011**: Studio sends MUST pass the header checks every other CoreX email passes.

**One frame (Story 3)**

- **FR-012**: Every email CoreX draws (Studio templates, code templates, form notifications, newsletter mail, replies,
  account emails) MUST be drawn in one frame, with the logo or name, the accent, the message on a card, and a footer
  with the site's name, why the person receives it, and the links its kind needs.
- **FR-013**: The Mail settings' logo, accent and footer MUST apply to all of them; with nothing set, the site's logo,
  the brand's primary colour and the site's name.
- **FR-014**: A Studio layout MUST arrange the body inside the frame; a region that only repeats the site's name MUST
  NOT be drawn.
- **FR-015**: The frame MUST be email-client safe, at most 600px wide, use the system font stack, keep 4.5:1 text
  contrast in light and dark, and follow the site's direction.
- **FR-016**: A design on `corex_email_design` MUST draw replies as today and MUST be given another kind only when it
  declares it accepts it.
- **FR-017**: A finished message MUST be sent as given, with no frame.
- **FR-018**: Without CoreX Mail, a form notification MUST be plain text with one answer per line.

**Defaults (Story 4)**

- **FR-019**: A new installation MUST get the four default templates, active, framed, in the site's language.
- **FR-020**: A new flow MUST be created with "Admin notification" to the team and "Contact confirmation" to the
  submitter, enabled and switchable off; the admin notification MUST name the form by title and link to the submission.
- **FR-021**: The newsletter MUST send "Double opt-in" when it is active (confirm button and a plain link), and
  "Newsletter welcome" on confirmation when it is active.
- **FR-022**: A default template MUST be resettable to its shipped version, after a confirmation, as a new version.
- **FR-023**: An upgraded site MUST get the defaults as drafts, bound to nothing, and send every email as before.
- **FR-024**: A flow's team notification bound to a template with no active version MUST fall back to the PHP contact
  notification to the admin.

**Variables (Story 5)**

- **FR-025**: A template MUST accept `submission.<field>` for any field, and `{{ form.answers }}` that draws every answer
  as a labelled list in the form's order, a file answer as a link to the submission.
- **FR-026**: The Variables tab MUST group variables by the emails that supply them, each with a sample value.
- **FR-027**: Saving MUST warn and name every variable a bound email does not supply; binding a template to a flow MUST
  be refused for such a variable until it is removed or confirmed.
- **FR-028**: A variable with no value at send time MUST be left out cleanly, and the email's log row MUST name it.
- **FR-029**: An unknown variable MUST be refused on save, named.
- **FR-030**: Values MUST stay escaped; a variable never inserts HTML.

**Account emails (Story 6)**

- **FR-031**: A reset asked through the account block MUST be the "Password reset" template in the frame, with which
  account, when, a button, a plain link to the account page's reset form, how long it works and what to do if unasked.
- **FR-032**: A new member who chose a password MUST get the "Welcome to your account" template with a link to the
  account page and no set-password link.
- **FR-033**: Neither MUST contain the hidden login address; both MUST be in the site's language and direction with a
  plain-text part; both MUST follow the delivery rules (captured on a development site).
- **FR-034**: A template that cannot be drawn MUST fall back to WordPress's own email and be recorded in the activity
  log.

**Plain text on the shared mailer (Story 7)**

- **FR-035**: A finished message MUST accept an optional plain-text version, last among its fields; given, the email
  MUST carry both parts as given; empty or absent, it MUST be sent exactly as today.
- **FR-036**: A captured email MUST keep it, and Email Studio's log MUST show a captured email's plain text.
- **FR-037**: The mailer's developer documentation MUST show the field with an example.

**All stories**

- **FR-038**: Every new text MUST be translatable; every email and screen MUST read correctly right to left.
- **FR-039**: Every new screen element MUST meet WCAG 2.2 AA in light and dark, at 375px and 1440px.
- **FR-040**: The release notes MUST show before and after for each kind of email and name the client impact on Muva,
  Perego and Mizzey.

### Key Entities

- **Email frame**: the one design every CoreX-drawn email is in; reads the site's brand and Mail settings.
- **Email kind**: reply, notification, confirmation, newsletter, account or message; decides the footer's reason line
  and links, and whether a site's own design is given the email.
- **Default template**: a Studio template CoreX ships, with its shipped content kept so it can be reset.
- **Sample values**: what the preview and the test send fill each variable with.
- **Missing variables**: the variables an email was sent without, kept on its log row.
- **Finished message**: a message handed to the shared mailer with its own subject, body and sender; now with an
  optional plain-text version.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: For 100% of templates, the captured test email's HTML and plain text equal the preview's.
- **SC-002**: 0 template emails leave without the template's From (when set) or without a plain-text part.
- **SC-003**: The reply, contact notification, newsletter confirmation, Studio template email and account emails are
  in the same frame, with the same logo, accent and footer line, in 100% of the checks.
- **SC-004**: 0 messages handed over finished change: their raw subject, body, From, Reply-To and parts equal what
  v0.47.0 sends (TC-EML-01-07, 03-06, 03-07, 05-06, 05-07, 19-02).
- **SC-005**: A new site's first flow submission sends 2 emails with no configuration.
- **SC-006**: 0 emails go out with `{{ }}` or an empty label left in them.

## Client impact

- **Muva**: its leads and briefs are finished messages and are sent as before; its replies stay in its own design (its
  `corex_email_design` filter is for replies and is not given other mail). It gets the four default templates as
  drafts, bound to nothing. Email Studio's preview and test send change for anyone who uses them there.
- **Perego**: its form, comment and reply mail are finished messages on the shared mailer and are sent as before, with
  the same From, Reply-To and single HTML part. Its renderer may start handing over its text version (story 7) when its
  session chooses; nothing changes until it does. It gets the four default templates as drafts.
- **Mizzey**: pinned to v0.42.0; not affected until it moves.
- Neither client runs corex-profile or corex-newsletter, so the account and newsletter changes do not reach them.

## Assumptions

- The newsletter add-on has no preferences page; its emails carry an unsubscribe link, and a preferences link only when
  a site supplies one through the filter documented with it.
- WordPress's own reset-key lifetime (`password_reset_expiration`, one day by default) is what the reset email states.
- "Who asked" in the reset email is the account's address and the time of the request; no visitor address is written
  into an email.
- The rich editor, the five-section navigation, the log viewer and named-event routing are later releases (R3.12); this
  release works inside the current eleven tabs.
- Every story, its test cases and its verification are in the owner's private planning workspace; this spec repeats the
  criteria and nothing else.
