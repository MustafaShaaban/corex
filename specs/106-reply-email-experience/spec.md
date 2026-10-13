# Feature Specification: Replies that are written and sent as designed emails

**Feature Branch**: `spec/106-reply-email-experience`

**Created**: 2026-10-08

**Status**: Planned 2026-10-11 ([plan.md](plan.md), [tasks.md](tasks.md)); slice 0 is in v0.44.0, slices 1 to 4 in v0.45.0 but for the reply draft; stories 8 to 11 added 2026-10-12

**Input**: Owner requirement, 2026-10-08 — "the reply emails from corex it should be a rich text,
should have the same experience of the emails. also should create a standard sytled email template
for this, the standard one should have the corex theme and colors and so on, and give the option
to developer to replace it with his custom template as we need this for muva and perego".

**Added 2026-10-11**: the owner used Reply on a live client site (Muva, CoreX v0.44.0) and
reported what is under "Reported from a live site" below. It adds user story 0 and FR-028 to
FR-031, and changes the order of the slices.

**Added 2026-10-12**: the owner read replies sent from the live site on v0.45.2 and asked for
what is under "Reported from the live site again" below. It adds user stories 8 to 11 and
FR-032 to FR-044. His words reached this spec through the Muva client session, quoted as it
relayed them; story 11 waits on one answer from him.

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

## Reported from a live site *(owner, 2026-10-11; each confirmed in the code)*

- A reply was not delivered until CoreX → Settings → Mail had a delivery provider chosen and
  "Enable live delivery" ticked. Nothing beside the reply box says so. He could not send a reply
  until he was told.
- After sending, the pane says "Reply processed by Email Studio." whether the message was
  delivered, captured or refused. He could not tell whether his first reply had gone.
- The reply's text is not kept or shown: the history holds an action, an attempt and a state.
- The box is a plain text box; the email is CoreX's bare shell (stories 1 to 3 below).

## Reported from the live site again *(owner, 2026-10-12; each read in the code)*

"the reply email that sent from corex admin is now better than before but it needs to be
styled better than that, add background, add propper header since the logo now is on the air
and if i puted large logo it is bad, also we need to sign it as it a private reply or internal
operation something like that, i feel that if we can make it to reply to the user at the same
email that sent to him after the submission will be beter, if it could be an option to be
choosen that if the admin wants to reply to the same email or same thrid replies or sepirate
email ? and add a default title based on that form submited could be edited if wants to
change it".

- **The logo is "on the air".** `StandardEmailDesign` draws the header as a row above the
  card, on the canvas, and the logo as an image limited to 180 pixels of width and nothing of
  height. A square logo is drawn 180 by 180 above the message. (Story 8.)
- **"Add background."** The standard design has a canvas colour and a card on it; what he saw
  as missing is a header that reads as part of the email. A site's own background is its own
  design's, which Muva has since registered. (Story 8.)
- **No subject to start from.** The subject starts empty and is required. (Story 9.)
- **A reply cannot join the email the person already has.** Nothing CoreX sends carries a
  Message-ID of its own choosing, an In-Reply-To or a References header; `MailRequest` has no
  headers; `EmailMessage::$headers` exists and `WpMailDriver` never reads it; the id of the
  confirmation a person was sent is not kept. (Story 10.)
- **The email does not say what it is.** It carries the site's mark, the message and a footer
  naming the site. Nothing says a person wrote it to this person. (Story 11.)

Asked for the same day and not part of this spec: a copy button on the Coming soon preview
link, which is the Operations screen's and has its own task.

## User Scenarios & Testing *(mandatory)*

### User Story 0 - A reply says what will happen to it, and what did (Priority: P1)

Somebody answering a lead is told, before they type, when a reply from this site will not be
delivered and where that is changed. After sending they are told what happened to this reply:
delivered, kept on the site and not delivered, or not sent and why. What they wrote is on the
submission.

**Why this priority**: a reply that silently goes nowhere is worse than no reply box. It
happened to the owner on a client's live site.

**Independent Test**: on a site where live delivery is off, open a submission: the Reply section
says replies are not being delivered and links to the Mail settings, and nothing can be sent.
Switch delivery on, reopen, send: the pane says the reply was sent to the lead's address and
lists it with its text, who sent it, when, and "Sent".

**Acceptance Scenarios**:

1. **Given** a production site with no delivery provider or with live delivery off, **When** a
   submission is opened, **Then** the Reply section says replies are not being delivered and why,
   before anything is typed, and nothing can be sent from it.
2. **Given** that notice and a person who may change settings, **When** they read it, **Then** it
   links to the Mail settings; anybody else is told who can change it.
3. **Given** a development or staging site, **When** a submission is opened, **Then** the Reply
   section says a reply will be kept on the site and not delivered to the lead.
4. **Given** a reply is sent, **When** the answer comes back, **Then** the pane says which of
   the three happened, in words, with the reason when it was not delivered.
5. **Given** a reply was not sent, **When** the pane says so, **Then** what was written is still
   in the box.
6. **Given** a reply was sent, kept or refused, **When** the submission is read by anybody on the
   team, **Then** the reply is listed on it with its subject, its text, who sent it, when, and
   its state.

---

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

**Independent Test**: set a reply sender name and address; send a reply; the email's From and
Reply-To are those; clear the setting; the next reply leaves from the site's general sender as
today. *(Amended 2026-10-13: this read "the captured email's From". An email kept on a
development site records its Reply-To and not its sender, so the From is read off the message
WordPress's mailer builds, in a test, and on a delivered email.)*

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

### User Story 8 - A header that holds a logo of any shape (Priority: P1)

The email opens with a header that is part of it: the site's logo, or its name, on the card
and not floating above it, at a size that a square, a tall or a very wide logo cannot break.

**Why this priority**: it is what every customer who is answered sees first, and on the first
live site it was a 180 pixel square above a narrow message.

**Independent Test**: send a reply from a site whose logo is square, one whose logo is five
times wider than it is tall, and one with no logo; in each the header is one band of the card,
no taller than a line of heading, and the message starts where it did.

**Acceptance Scenarios**:

1. **Given** a site with a square logo of 600 by 600 pixels, **When** a reply is sent, **Then**
   the logo is drawn no taller than 48 pixels and no wider than 180, inside the card.
2. **Given** a site with no logo, **When** a reply is sent, **Then** the header holds the
   site's name, in the same band.
2a. **Given** a square logo and a mail client that shows no images, **When** it draws the
   email, **Then** the site's name is still read in the header, whole.
3. **Given** a mail client that reads no style sheet and ignores `max-height` (Outlook on
   Windows), **When** it draws the email, **Then** the logo is the same bounded size, from
   the image's own width and height.
4. **Given** a developer who runs `wp corex make:email-design`, **When** they read the file it
   writes, **Then** it starts from the same header.

---

### User Story 9 - A subject to start from (Priority: P2)

The subject is already written when the reply box opens, from what the person sent, and can be
changed.

**Independent Test**: open a submission of the form "Contact"; the subject reads
"Re: Contact"; change it; send; the email carries what was typed.

**Acceptance Scenarios**:

1. **Given** a submission with no reply begun, **When** its pane opens, **Then** the subject
   holds "Re: " and the name of the form it came from.
2. **Given** that subject untouched and no message written, **When** the pane is left,
   **Then** no draft is kept: a subject nobody wrote is not something somebody began.
3. **Given** a draft of the person's own, **When** the pane opens, **Then** the subject is the
   draft's, not the default.
4. **Given** a reply that continues the conversation (story 10), **When** the box opens,
   **Then** the subject is "Re: " and the subject of the email being answered.

---

### User Story 10 - A reply in the conversation the person already has (Priority: P1)

The person who wrote in was sent a confirmation. The reply can arrive under that email, in
the same conversation in their mail program, or as an email of its own: whoever answers
chooses, each time.

**Why this priority**: it is the owner's ask in his own words, and to a customer it is the
difference between an answer to what they sent and one more email from a company.

**Independent Test**: submit a form that sends a confirmation; reply with "Continue the
conversation" chosen; in a mail program that threads, the reply is under the confirmation.
Reply to another with "Send as a new email"; it stands alone.

**Acceptance Scenarios**:

1. **Given** a submission whose confirmation CoreX sent, **When** the reply box opens,
   **Then** it offers two ways to send, and "in the conversation they already have" is chosen.
2. **Given** that choice, **When** the reply is sent, **Then** it names the confirmation as
   the email it answers, and its subject is "Re: " and the confirmation's subject unless the
   person changed it.
3. **Given** a first reply already sent, **When** a second is sent the same way, **Then** it
   continues from the first reply, and names the confirmation before it.
4. **Given** "Send as a new email", **When** the reply is sent, **Then** it names no earlier
   email.
5. **Given** a submission for which no email of CoreX's is known (the form sends none, or the
   submission is older than this feature), **When** the box opens, **Then** only "a new email"
   is offered, and the box says why.
6. **Given** a site that sends its own confirmation through CoreX's mailer, **When** it tells
   CoreX which submission that email was for, **Then** replies can continue from it as from
   one CoreX sent.

---

### User Story 11 - The email says what it is (Priority: P2) [NEEDS CLARIFICATION]

"we need to sign it as it a private reply or internal operation something like that."

Read two ways, both plausible: the email should say it is a personal reply from the site's
team to this person, not an automatic message; or it should end with who wrote it. One
question to the owner settles it: a line above the message ("A reply from the {site} team"),
a signature below it (the name of whoever replied, or a line the site sets), or both. Nothing
of this story is built before he answers.

---

### Edge Cases

- A logo address that is not an image of the site's library (set by a filter): its size is
  not known, and it is limited by height alone.
- A confirmation whose mail provider replaced its Message-ID: the reply names the id the
  provider answered where it answered one; where it did not, the conversation may not join, and
  the guide says so.
- A mail program that groups by subject as well as by reference (Gmail): a reply whose subject
  the person rewrote may stand alone though it names the email it answers. The box says so
  beside the subject when the conversation is chosen and the subject no longer begins as the
  confirmation's does.
- A reply sent again (story 4): it keeps the place in the conversation it was first given.

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
- **FR-011a**: An email MUST be drawn in the site's language: its direction, its `lang` and the
  words CoreX writes into it. It MUST NOT follow the language of the request that draws it, which
  in the admin is the profile language of whoever is signed in. The words the person wrote keep
  their own direction (story 2, scenario 4). Added 2026-10-13 (DECISIONS #356).
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

**What a reply says about itself** *(added 2026-10-11)*

- **FR-028**: Before anything is typed, the reply box MUST say when a reply from this site will
  not be delivered, with the reason, and MUST link a person who may change settings to the
  setting; it MUST say when a reply will be kept on the site and not delivered.
- **FR-029**: That statement MUST come from the same decision the sending makes, so that the two
  cannot disagree.
- **FR-030**: After a reply is sent, the pane MUST say what happened to it: delivered, kept and
  not delivered, or not sent, with the reason. It MUST NOT say a reply was sent when it was not.
- **FR-031**: A reply that was not sent MUST leave what was written in the box.

**After the second live review (2026-10-12)**

- **FR-032**: The standard template's header MUST be part of the card that holds the message,
  not a row above it.
- **FR-033**: The logo MUST be limited in height as well as in width, in a mail client that
  reads its styles and in one that reads only the image's own width and height.
- **FR-034**: The design a developer is given to start from MUST have the same header.
- **FR-035**: The subject of a reply MUST start as "Re: " and the name of the form the
  submission came from, or the subject of the email being continued, and MUST be editable.
- **FR-036**: A default subject alone MUST NOT be kept as a draft.
- **FR-037**: Every email CoreX sends through its mailer MUST carry a Message-ID CoreX chose,
  and the id MUST be kept with the record of that sending.
- **FR-038**: For a submission, CoreX MUST know the emails it sent the submitter, in order: the
  confirmation, then each reply.
- **FR-039**: The reply box MUST offer to send in the conversation the person already has, or
  as a new email, and MUST offer only the second where no earlier email is known, saying why.
- **FR-040**: A reply sent in the conversation MUST name the latest email of that conversation
  as the one it answers and the ones before it as its references.
- **FR-041**: The choice MUST be recorded with the reply and shown where the reply is listed.
- **FR-042**: A site that sends its own confirmation through CoreX's mailer MUST have a
  documented way to have that email recorded for a submission, so that replies continue from it.
- **FR-043**: The preview MUST say which of the two ways the reply will be sent. The two are
  one document: only headers differ.
- **FR-044**: [NEEDS CLARIFICATION: story 11] The email MUST say that a person of the site's
  team wrote it to this person, in the way the owner chooses.

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
- **Conversation**: for one submission, the emails CoreX sent its submitter, in order, each
  with the Message-ID it was sent under.

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
- **Slices, each its own pull request** *(reordered 2026-10-11, after the owner's report)*: (0)
  a reply keeps its line breaks; (1) a reply says what will happen to it and what did, and is
  kept on the submission; (2) the standard template and its brand values, for replies, with a
  plain-text version; (3) the editor and the true preview; (4) a client's own template, with the
  generator and the guide; (5) "Send again" and the reply sender; (6) saved replies; (7) every
  email in the template.
