# Feature Specification: Replies that are written and sent as designed emails

**Feature Branch**: `spec/106-reply-email-experience`

**Created**: 2026-10-08

**Status**: Draft

**Input**: Owner requirement, 2026-10-08 — "the reply emails from corex it should be a rich text,
should have the same experience of the emails. also should create a standard sytled email template
for this, the standard one should have the corex theme and colors and so on, and give the option
to developer to replace it with his custom template as we need this for muva and perego".

## What is there today *(read from the code on 2026-10-08)*

- A reply is written in the submission's pane: a subject and a plain text box. There is no
  formatting, no preview and nothing to start from.
- **A reply loses its line breaks.** What is typed is sent as HTML unchanged, so three paragraphs
  arrive as one block.
- The reply is put inside the one email shell CoreX has: the site's name or logo, a thin coloured
  line, the text. The shell sets no font, no text colour, no background and no footer.
- Only an HTML version is sent. No email CoreX sends has a plain-text version beside it.
- The reply leaves from the site's general sender address. A site cannot say which address its
  replies come from (the open part of issue #150).
- What was written is not kept where the team can read it. The submission's history says "Reply
  sent" and nothing more; a reply cannot be sent again.
- **There is no rich-text editor anywhere in CoreX's admin.** Email Studio's templates are edited
  as raw HTML in a text box, and its preview is drawn by different code from the email that is
  sent, so the two can differ.
- The only CoreX-styled email is the support request (spec 093), with its colours written into
  one add-on. Nothing else can use them.
- A client site has no way to replace an email's template or shell: no setting, no hook, no file
  it can supply.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Write a reply as it will be read (Priority: P1)

Somebody answering a lead writes the reply with paragraphs, emphasis, a list and a link, sees it
exactly as the lead will receive it, and sends it.

**Why this priority**: it is the request, and the line breaks are lost today on every reply a
client's customer receives.

**Independent Test**: open a submission, write a reply of three paragraphs with one bold phrase,
a two-item list and a link, open the preview, send; the captured email is the preview, mark for
mark, with three paragraphs, the bold phrase, the list and a working link.

**Acceptance Scenarios**:

1. **Given** the reply box, **When** the person types and presses Enter twice, **Then** a new
   paragraph begins, and the email has that paragraph.
2. **Given** selected text, **When** the person uses the formatting controls or their keyboard
   shortcuts, **Then** the text is bold, italic, a link, or part of a bulleted or numbered list.
3. **Given** text pasted from a document or a web page, **When** it lands in the box, **Then**
   its paragraphs, emphasis, lists and links are kept and everything else about its styling is
   dropped.
4. **Given** a reply being written, **When** the person opens the preview, **Then** they see the
   whole email, in its template, as the recipient will, on a wide and on a narrow screen.
5. **Given** a reply is sent, **When** the captured or delivered email is opened, **Then** it is
   the previewed email.
6. **Given** a reply half-written, **When** the pane is closed by mistake and the submission
   reopened, **Then** the draft is still there.
7. **Given** the reply box, **When** it is used with a keyboard and a screen reader, **Then**
   every control is reachable, named and announced, in both directions of text.

---

### User Story 2 - A standard template, in CoreX's design (Priority: P1)

A reply arrives looking like a designed message: the site's name and logo at the top, a readable
measure and type, CoreX's colours, and a footer saying who sent it.

**Why this priority**: the owner asked for "a standard styled email template", and today's shell
styles nothing.

**Independent Test**: send a reply on a site with a logo and on one without; open each in a mail
client set light and one set dark, and on a phone; the layout holds, the text is readable in
both, and the header shows the logo, or the site's name where there is none.

**Acceptance Scenarios**:

1. **Given** a site with no customisation, **When** a reply is sent, **Then** it is in the
   standard template: CoreX's colours and type, the site's logo or name in the header, the
   message, and a footer naming the site.
2. **Given** the email is opened in a client that shows no images, **When** it is read, **Then**
   the site's name stands where the logo would be and nothing is lost.
3. **Given** the email is opened on a phone, **When** it is read, **Then** the text is at a
   readable size with no sideways scrolling.
4. **Given** a right-to-left site or an Arabic reply, **When** the email is read, **Then** it
   reads right to left, with a Latin phrase inside it in its own order.
5. **Given** a mail client in dark mode, **When** the email is read, **Then** the text and its
   background keep a readable contrast.
6. **Given** any mail client, **When** the email is read as plain text, **Then** there is a
   plain-text version with the same words, links written out and list items marked.

---

### User Story 3 - A client site replaces the template with its own (Priority: P1)

A developer building a client site supplies that site's own reply template, from the site's own
code, and every reply from that site uses it. Updating CoreX does not undo it.

**Why this priority**: "we need this for muva and perego". Each replies to its customers in its
own brand.

**Independent Test**: in a generated client site, add a reply template from the site's plugin
without touching a framework file; send a reply; the email is the client's design around the
operator's words, the preview shows the same, and after a CoreX update both still hold.

**Acceptance Scenarios**:

1. **Given** a client site's plugin supplies a reply template, **When** a reply is sent, **Then**
   the email uses it.
2. **Given** that template, **When** the preview is opened, **Then** the preview is drawn with
   it.
3. **Given** that template fails or returns nothing, **When** a reply is sent, **Then** the reply
   goes out in the standard template, and the failure is recorded where an administrator will see
   it.
4. **Given** that template, **When** it is written, **Then** it is handed the message, the
   subject, the site's name and logo, the direction of text, and who is being answered, and does
   not have to clean the message itself.
5. **Given** a client only wants its own colours and logo, **When** it supplies those, **Then**
   the standard template uses them, with no template to write.
6. **Given** CoreX is updated, **When** the site is rebuilt, **Then** nothing of the client's
   template was a framework file, and nothing has to be reapplied.
7. **Given** a developer starting from nothing, **When** they ask CoreX for a starting point,
   **Then** it gives them a working copy of the standard template in their site's code.

---

### User Story 4 - What was answered is on record (Priority: P2)

The team can read what was sent to a lead, when and by whom, and can send it again if it did not
arrive.

**Why this priority**: "Reply sent" with no text is not a record. A second person picking up the
lead cannot see what was already said.

**Independent Test**: send a reply; reopen the submission as another team member; the history
shows the reply's subject and text, who sent it and when, and its delivery state; choose "Send
again" on a failed one and it goes.

**Acceptance Scenarios**:

1. **Given** a reply was sent, **When** the submission's history is read, **Then** the reply's
   subject and text are there, as formatted, with who sent it and when.
2. **Given** a reply that was not delivered, **When** "Send again" is chosen, **Then** the same
   reply is sent again and recorded as a second attempt.
3. **Given** a submission is deleted permanently, **When** it goes, **Then** the stored replies
   go with it.

---

### User Story 5 - Replies come from the address the site answers from (Priority: P2)

A site says which name and address its replies are sent from, and where an answer to them goes.

**Why this priority**: a reply from a no-reply or a general address reads wrongly and may be
answered into nowhere. It is the open part of issue #150.

**Independent Test**: set a reply sender name and address; send a reply; the captured email's
From and Reply-To are those; clear the setting; the next reply leaves from the site's general
sender as today.

**Acceptance Scenarios**:

1. **Given** a reply sender is set, **When** a reply is sent, **Then** it leaves from that name
   and address.
2. **Given** none is set, **When** a reply is sent, **Then** it leaves from the site's general
   sender, as now.
3. **Given** an address that is not valid, **When** it is saved, **Then** it is refused with the
   reason.

---

### User Story 6 - Start from a saved reply (Priority: P3)

Somebody who sends the same answer often starts from a saved one, with the lead's name already in
it, and edits it before sending.

**Why this priority**: it makes the editor faster; the editor is useful without it.

**Independent Test**: save a reply as "Thanks, we will call"; open another submission, choose it;
the box holds that text with this lead's name; edit a line and send.

**Acceptance Scenarios**:

1. **Given** saved replies exist, **When** one is chosen, **Then** its subject and text fill the
   reply, with the submitter's name and the site's name put in.
2. **Given** a reply being written, **When** "Save as a saved reply" is chosen, **Then** it is
   there for the next person.

---

### User Story 7 - Every email from the site wears the same template (Priority: P3)

The notification a form sends, a routed email and a reply all arrive in the same design, the
standard one or the client's.

**Why this priority**: "the same experience of the emails" ends there. It changes how every
existing email looks, so it follows the reply, and is announced.

**Independent Test**: on a site with a client template, submit a form that sends a notification
and answer it; both emails are in that template.

**Acceptance Scenarios**:

1. **Given** a site with no customisation, **When** any CoreX email is sent, **Then** it is in
   the standard template.
2. **Given** a client template, **When** any CoreX email is sent, **Then** it is in the client's.
3. **Given** Email Studio's preview, **When** it is opened, **Then** it is drawn by the code that
   draws the sent email.

---

### Edge Cases

- A reply that is only spaces, or only an empty list: it cannot be sent, and the reason is said.
- Formatting the editor does not offer, typed or pasted as markup: it is removed before the
  preview and before sending, and what remains is what the person sees.
- A link without a scheme: it is made a web link; a link with a scheme that is not web, mail or
  telephone is removed and its text kept.
- A very long reply: the email is still one message, and the preview scrolls.
- The submitter gave no email address: there is no reply box, as now.
- The email add-on is not active: the pane says replies need it, as now.
- Development mode: the reply is captured, not delivered, and the capture shows the template.
- Two people answer the same submission at once: both replies are sent and both are in the
  history; neither draft overwrites the other's.
- A draft is left for days: it is still the person's own draft and nobody else's.
- A client template that leaves out the message: the reply is refused before sending, because an
  email without the words is not the reply.
- An image pasted into the reply: it is not kept; the first version sends words.
- The mail client strips the template's styles: the message is still readable as plain content
  in order.

## Requirements *(mandatory)*

### Functional Requirements

**Writing**

- **FR-001**: The reply MUST be written in an editor that offers paragraphs, line breaks, bold,
  italic, links, and bulleted and numbered lists, by control and by keyboard shortcut.
- **FR-002**: What the person sees in the editor MUST be what is sent: paragraphs and line
  breaks typed MUST arrive as paragraphs and line breaks.
- **FR-003**: Pasted content MUST keep paragraphs, emphasis, lists and links and lose every other
  style, script, image and embedded object.
- **FR-004**: The server MUST reduce a reply to the formatting FR-001 names before it is
  previewed, stored or sent, whatever was submitted to it.
- **FR-005**: The person MUST be able to preview the whole email before sending, at a wide and a
  narrow width, and the preview MUST be produced by the same code that produces the sent email.
- **FR-006**: An unsent reply MUST be kept as that person's draft for that submission until it is
  sent or discarded.
- **FR-007**: The editor and the preview MUST be operable by keyboard, named for assistive
  technology, correct right to left, and built from the admin's existing components and tokens.

**The standard template**

- **FR-008**: CoreX MUST have one standard email template in its own design: its colours, type,
  spacing and shape, as the support email of spec 093 uses them.
- **FR-009**: The template's header MUST carry the site's logo, or the site's name where there is
  no logo or images are not shown. It MUST NOT carry CoreX's own mark unless the site asks for it.
- **FR-010**: The template MUST have a footer that names the site.
- **FR-011**: The template MUST hold its layout and stay readable at phone width, in a mail client
  set dark, with images off, and right to left.
- **FR-012**: The colours and type an email uses MUST be defined once, for every email CoreX
  sends, and not inside one add-on.
- **FR-013**: Every reply MUST be sent with a plain-text version beside the HTML one, with the
  same words, links written out and list items marked.

**A client's own template**

- **FR-014**: A client site MUST be able to replace the standard template with its own, from its
  own plugin or theme, without editing a framework file.
- **FR-015**: A client MUST be able to change only the template's colours, logo and footer text,
  without supplying a template.
- **FR-016**: A replacement template MUST be handed the cleaned message, the subject, the site's
  name and logo, the direction of text, and the recipient; and MUST NOT need to clean the message.
- **FR-017**: When a replacement fails, returns nothing, or leaves the message out, the reply
  MUST go out in the standard template or be refused with the reason, and the failure MUST be
  recorded where an administrator sees it. A reply MUST NOT be sent without its words.
- **FR-018**: The preview MUST use whichever template the site uses.
- **FR-019**: CoreX MUST give a developer a working copy of the standard template to start from,
  placed in the client's own code.
- **FR-020**: How a client supplies a template MUST be documented with an example that runs, and
  a change to it MUST be announced under Client impact.

**The record, and the sender**

- **FR-021**: A sent reply's subject and text MUST be kept with the submission and shown in its
  history, with who sent it, when, and its delivery state.
- **FR-022**: A reply MUST be sendable again from its record, and each sending recorded.
- **FR-023**: Stored replies MUST be removed when the submission is anonymized or deleted, as
  spec 105 lists.
- **FR-024**: A site MUST be able to set the name and address replies are sent from and the
  address answers go to; without them a reply leaves from the site's general sender.
- **FR-025**: A reply MUST follow the same delivery rules as now: captured in development,
  delivered only where live delivery is allowed.

**Later stories**

- **FR-026**: A person MUST be able to save a reply for reuse and start a reply from a saved one,
  with the submitter's name and the site's name put in.
- **FR-027**: Every email CoreX sends MUST be able to use the site's template, standard or
  client's; and Email Studio's preview MUST be produced by the code that produces the sent email.

### Key Entities

- **Reply**: a subject and a formatted message written by a team member to the person who sent a
  submission. Has a draft before it is sent and a record after.
- **Email template**: the design around a message: header, body, footer. One standard, in
  CoreX's design; at most one replacement per site.
- **Email brand values**: the colours, logo and footer text the standard template uses. CoreX's
  by default; a site may set its own.
- **Reply sender**: the name and address a site's replies come from, and where answers go.
- **Saved reply**: a subject and message kept for reuse.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A reply of three paragraphs arrives as three paragraphs, in every mail client
  tested.
- **SC-002**: The preview and the delivered email are the same document: no difference in a
  byte-for-byte comparison of their HTML.
- **SC-003**: A developer replaces the template on a generated client site in under 30 minutes
  from the documentation alone, and no framework file is changed.
- **SC-004**: After a CoreX update, a client site's replies are still in its own template with
  no step taken.
- **SC-005**: The standard template is readable (text contrast of at least 4.5 to 1) in light and
  dark mail clients, at 320 pixels wide, with images off, and right to left.
- **SC-006**: Nothing outside the formatting the editor offers reaches a recipient, whatever is
  posted to the reply route.
- **SC-007**: Anybody on the team can read what was answered to a lead, and by whom, without
  leaving the submission.
- **SC-008**: Writing and sending a formatted reply can be done with the keyboard alone.

## Assumptions

- **"Rich text" is the formatting a reply needs**: paragraphs, emphasis, links, lists. Not
  images, tables, colours or fonts: those belong to the template, so every reply from a site
  looks like that site.
- **"The same experience of the emails"** is read two ways, and both are in scope: a reply is a
  designed email like the others a site sends (stories 2, 3 and 7), and writing one is as
  considered as building a template (stories 1, 4 and 6).
- **"The corex theme and colors" is the design, not the mark.** The standard template uses
  CoreX's colours and type; its header shows the site's own name and logo, because the email is
  from the site to its customer. This is the owner's to overrule; the exported PDF carries
  CoreX's logo by his own decision, and an email to somebody's customer is a different document.
- **A replacement is code a client's developer writes**, as he asked, supplied from the client's
  own plugin under spec 102's rule that client work never edits a framework file. Colours and
  logo alone need no code.
- **There is no rich-text editor in the admin to reuse.** Choosing one is the plan's first
  question: it has to be accessible, right-to-left, and light enough for the inbox's pane.
- **The lost line breaks are fixed first, by themselves**, ahead of the editor, because every
  reply sent today is affected.
- **A plain-text version changes how every email is handed to the mail driver.** It is built for
  replies first and is there for every email when story 7 arrives.
- **Other sessions' work**: the open issue about messages that cannot name their sender (#150)
  is answered for replies by story 5. If the mail queue or the routes that send change first,
  this spec's plan follows them.
- **Slices, each its own pull request**: (0) a reply keeps its line breaks; (1) the standard
  template and its brand values, for replies, with a plain-text version; (2) the editor and the
  true preview; (3) a client's own template, with the generator and the guide; (4) the record and
  "Send again"; (5) the reply sender; (6) saved replies; (7) every email in the template.
