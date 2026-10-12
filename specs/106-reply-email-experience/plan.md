# Implementation Plan: Replies that are written and sent as designed emails

**Branch**: one per slice, from `main` · **Date**: 2026-10-11 · **Spec**: [spec.md](spec.md)

## Summary

Seven slices after slice 0, each a pull request. This plan decides what they share and details
slices 1 to 5. Slices 6 and 7 are planned when they are reached, against the code as it is then.

The order is not the one the spec was written with. On 2026-10-11 the owner used Reply on a
client's live site and could not send, could not tell whether he had sent, and could not read
what he had sent. Those three come first (slice 1), because each of them makes the reply box
untrustworthy whatever the email looks like.

| Slice | What a person gets | Stories |
|---|---|---|
| 1 | The reply box says when delivery is off and where to switch it on; the pane says what happened to a reply; the reply is listed on the submission | US0, US4.1, US4.3 |
| 2 | A reply arrives in the standard template, with a plain-text version | US2 |
| 3 | The reply is written in an editor and previewed as it will be sent; a draft is kept | US1 |
| 4 | A client site supplies its own template, or only its colours, logo and footer | US3 |
| 5 | "Send again"; the name and address replies come from | US4.2, US5 |
| 6 | Saved replies | US6 |
| 7 | Every email in the template; Email Studio's preview drawn by the sending code | US7 |
| 8 | The header is part of the card, and a logo of any shape fits it | US8 |
| 9 | A subject to start from | US9 |
| 10 | A reply in the conversation the person already has, or as a new email | US10 |
| 11 | The email says what it is (waits on the owner's answer) | US11 |

Slices 8 to 11 come from the owner's second reading of live replies (2026-10-12) and are
built before 5, 6 and 7, in that order: 8 is what every customer sees, 9 is small and is what
10 builds its subject on, 10 is the largest.

## Technical context

- PHP 8.3, WordPress 7.0+. The route `POST corex/v1/submissions/{id}/reply` is declared in
  `SubmissionsController` and guarded by `SubmissionRestGateway`. It calls
  `SubmissionEmailService::reply()`, which calls the seam `Corex\Mail\SubmissionEmailGateway`
  (corex-core). The email add-on fills the seam with `EmailStudioSubmissionGateway`; without the
  add-on `UnavailableSubmissionEmailGateway` throws.
- Whether an email is delivered, captured or refused is decided in one place,
  `DeliveryPolicy::evaluate()`, from the environment, whether `mail.provider` names the bound
  driver, and `mail.live_delivery`. `EmailStudioService::dispatch()` acts on it and records an
  attempt; a captured message is stored whole by `CapturedEmailRepository`.
- The shell around a reply is `Corex\Email\Template\Layout::wrap()`. `WpMailDriver` sends
  `text/html` only.
- A submission is a post of type `corex_submission`; its status, notes and history are post
  meta. The history is `corex_submission_timeline` (`SubmissionTimelineRepository`).
- The inbox is React in `plugins/corex-config/src/Submissions`; the pane is
  `detail/DetailPane.js`, its requests are in `useInbox.js`, its pure state in
  `detail/detailState.js`.
- Tests: Pest unit (fakes for the stores and the seam), Pest integration on real WordPress, Jest
  for state and components, Playwright for the pane. CI is the authority for the last two.

## Constitution check

- Thin controller, logic in a service, data access behind a store interface: yes. The reply
  record gets a `SubmissionReplyStore`; the controller still calls one service method.
- Injected through the container, no `new` of a dependency inside a method: yes.
- Tokens and existing admin components only, logical properties, every string translatable,
  measured on the rendered page in both directions (owner's rule of 2026-10-07): yes. An email
  cannot read a custom property, so its colours arrive as literals from one named class
  (D8), as spec 093 already decided for the support email.
- No optional plugin as a hard dependency: the email add-on stays behind the seam. What the
  pane needs to know about delivery crosses it as a value object of corex-core.
- Security: the reply route keeps its declared middleware; what is posted is reduced to an
  allowlist on the server before it is stored, previewed or sent (D5); a stored reply is drawn
  through the same allowlist in the browser.
- Spec before code: the spec was amended first (user story 0, FR-028 to FR-031, the order).

No gate is failed and nothing needs a justification.

## Decisions

### D1. The order follows the report

Slice 1 is what the owner met. It needs no design, no editor and no new dependency, so the
sites can take it in the next release whatever happens to the later slices.

### D2. One decision says what will happen to a reply, and the pane reads it

`DeliveryPolicy::evaluate()` already decides. Its `DeliveryDecision` gains a `code`
(`development`, `staging_capture`, `staging_live`, `unknown_environment`, `no_provider`,
`live_off`, `live`), so a caller can word the reason for its own reader without matching on a
sentence.

The seam gains `SubmissionEmailGateway::readiness(): ReplyReadiness`. `ReplyReadiness` is a
value object in corex-core: `mode` (`deliver`, `capture`, `refuse`, `unavailable`), `code`,
`reason`. `EmailStudioSubmissionGateway::readiness()` evaluates the policy with the same
context `reply()` sends with (one private method builds it for both), so FR-029 holds by
construction. `UnavailableSubmissionEmailGateway::readiness()` answers `unavailable`.

The reason is written for the person at the reply box, not for a log: "Replies are not being
delivered. No delivery provider is chosen in CoreX → Settings → Mail." The policy's own
sentences stay as they are for Email Studio.

The readiness travels in the submission's detail record as `reply_delivery`, so it is read
again each time a submission is opened. `corexSubmissions.mailSettingsUrl` is set only for a
person who has `corex_manage_settings`; the pane links when it is there and otherwise says an
administrator can change it.

**Alternative refused**: reading `mail.provider` and `mail.live_delivery` in the inbox. It
would be a second copy of the policy, in the wrong plugin, and wrong the day the policy changes.

**With `refuse` or `unavailable` the Send button is off**, and the notice is what it is
described by. A reply that is certain to be refused is not offered. The fields stay, so
what somebody started is not lost while an administrator switches delivery on.

### D3. The pane says what happened, from the result

The route already answers the `MailResult`. The pane reads its `state`:

| State | The pane says | The box |
|---|---|---|
| `sent`, `accepted`, `queued` | "Reply sent to {address}." | emptied |
| `captured` | "Reply kept on this site, not delivered to {address}." and the reason | emptied |
| `failed`, `rejected`, anything else | "The reply was not sent." and the reason, as a failure | kept |

It is said in the Reply section (`role="status"`, or the pane's failure for the third row),
because the inbox's own notice is behind the pane (issue #306). "Reply processed by Email
Studio." is removed.

### D4. A reply is kept with the submission

`SubmissionReplyStore` (interface) and `WpSubmissionReplyStore`, post meta
`corex_submission_replies`, one row to a reply so that two people answering at once cannot
write over each other. Each holds

```text
id            uuid
subject       string
body_html     the reduced message (D5), before any template
actor_id      who sent it
created_at    ISO 8601
attempts      [ { attempt_id, state, message, occurred_at } ]   newest last
```

`SubmissionEmailService::reply()` reduces, sends, stores, then writes the history entry, which
now carries `reply_id`. The detail record gets `replies`, each with `actor_name` and `state`
(its last attempt's). The route still answers `{ result }`: the pane reads the submission again
after a reply, and the reply comes with it.

A reply is stored whatever happened to it. One that was refused is on record as not sent: that
is what the second person on the team needs to know.

**Why post meta and not a table**: a submission's notes, history and delivery are post meta;
the trash, restore and delete of spec 105 already carry a submission's meta with it, and a
permanent delete already finds a reply's email copy through the history's attempt ids. A table
would need its own removal in each of those.

**Removal** (FR-023): deleted for good, the meta goes with the post. Anonymized, the retention
reader removes `corex_submission_replies` with the answers: a reply quotes the person it
answers. Spec 105's slices 5 and 6 (files on anonymize; WordPress's erasure and export by
address) have to include this key when they are built; it is written into that spec's notes.

**Not in an export.** The inbox's export is the submissions' answers. Whether replies belong
in it is not asked for and not decided.

### D5. What is posted is reduced on the server, to the formatting a reply has

`ReplyBody::reduce()` (config plugin, declared as the reply route's sanitizer): `wp_kses()` with `p`, `br`, `strong`, `b`, `em`,
`i`, `ul`, `ol`, `li`, `a[href]`, and the protocols `http`, `https`, `mailto`, `tel`; then
`wpautop()` so text typed in a plain box keeps its paragraphs (slice 0). A link with no scheme
becomes `https://`. Nothing left after reduction is refused: "Write a message before sending."

Today the route refuses a body that contains `<script`, an `on…=` attribute or `javascript:`.
That becomes removal: the person sees what remains, as the spec's edge case asks, and the
allowlist is the stronger rule. The subject keeps its refusal of control characters.

The browser draws a stored reply through the same allowlist (`replyHtml.js`, a DOM walk), so a
value that reached the meta some other way is still not markup the admin runs. Slice 3's paste
handling uses the same function.

### D6. The editor is CoreX's own, small, over `contenteditable` (slice 3)

A reply has paragraphs, bold, italic, links and two kinds of list. For that:

- **The block editor's `RichText`** edits one block; a list is a block, so paragraphs and lists
  together need the block editor's store and its weight.
- **TinyMCE** (`wp.editor.initialize`) is in WordPress, but it draws in an iframe with its own
  skin: it cannot read the admin's tokens, so it would be the one control that does not follow
  light, dark or the client's accent, and it is the classic editor WordPress is leaving.
- **Tiptap, Lexical, Quill**: a new dependency, with its own advisories to follow (the
  dependency gate already carries six bounded exceptions), for seven commands.
- **Our own**: a `contenteditable` region with a toolbar of the admin's buttons. The commands go
  through `document.execCommand`, which is marked deprecated and is implemented by every browser
  with no replacement; they are in one module, so the day a replacement exists one file changes.

`CorexRichText` in `admin/components/`: `role="textbox"`, `aria-multiline`, labelled; a
`role="toolbar"` with one tab stop and arrow keys, each button `aria-pressed`; Ctrl/Cmd+B, I, K,
Ctrl/Cmd+Shift+7 and 8; a link asked for in a small inline form, not `window.prompt`; `dir`
follows the admin, and `dir="auto"` on each block lets an Arabic reply in an English admin read
right to left. Paste is read as HTML, reduced by `replyHtml.js`, and inserted. The server reduces
again whatever arrives (D5): the editor is a convenience, not the guard.

As built: `replyHtml.js` moved to `admin/components/` beside the editor, since the editor is
the admin's and not the inbox's. A browser writes a line as a `<div>`, and leaves the first
line somebody types as bare text; the editor asks for paragraphs, makes the line being written
one, and the reduction it gives back turns what is left into paragraphs, so that what is
posted is what an email has. `ReplyBody` does the same for whatever posts without the editor.

### D7. The preview is the email (slice 3)

`POST …/submissions/{id}/reply/preview` answers the HTML and the plain text that `reply()` would
send for that subject and message, from the same `ReplyComposer` (slice 2). It is shown in a
sandboxed `<iframe srcdoc>` at 640 and 360 pixels. SC-002 is a test: the preview's HTML equals
the captured message's body, in an integration test and again in the browser.

The draft below was built after the editor and the preview, in its own pull request.

A draft (FR-006) is the person's own, for that submission: user meta
`corex_reply_draft_{submission}`, saved after a pause in typing, removed when the reply is sent
or discarded. Not `localStorage`: the same person on another machine has their draft.

As built, the draft is post meta on the submission, `corex_reply_draft_{user}`, not user meta:
it quotes and names the customer, so it has to go where the submission goes (the trash, a
permanent delete, an anonymization), and kept with the person it would have outlived all
three. The routes are `POST` and `DELETE …/reply/draft`; the draft is read with the
submission's detail, as `reply_draft`, and not by a request of its own.

### D8. The standard template, and the values it is drawn from (slice 2)

In the email add-on, `Corex\Email\Design`:

- `EmailPalette`: the colours, for one scheme. `light()` and `dark()` are the admin's two
  palettes, the one `SupportEmailPalette` held; the Guides add-on's support email reads them
  from here, and `SupportEmailPalette` is gone (FR-012).
- `EmailBrand`: whose the email is (the site's name, address, logo, direction, language and
  footer line) with its two palettes, and the type and shape constants. `EmailBrandSource`
  says which brand an email is drawn with; `SiteEmailBrand` reads it from WordPress.
- `EmailDesign` (interface): `render(EmailContent $content, EmailBrand $brand): string`.
  `EmailContent` holds the subject, the reduced message, and who is answered.
- `StandardEmailDesign`: tables and inline styles, a header with the logo (its `alt` is the
  site's name, so the name stands when images are off) or the name, the message on a surface at
  a readable measure, a footer naming the site. `dir` on the document; `color-scheme: light
  dark` and a `prefers-color-scheme` block for the clients that honour it, with light literals
  inline for those that do not (SC-005 is measured on both palettes).
- `PlainText::of(string $html)`: paragraphs as blank lines, list items as `- ` and `1. `, a
  link as `text (address)`.
- `ReplyComposer::compose(...)`: the one place a reply becomes `{ html, text }`.

`EmailMessage` gains `plainText` (last, nullable). `WpMailDriver` sets it as the message's
alternative body on `phpmailer_init` for that send, so the email is `multipart/alternative`;
the capture stores it. An email with no plain text is sent as today.

The header carries the site's mark, not CoreX's (spec, Assumptions): the owner can overrule it.

### D9. A client's own (slice 4)

- **Only values** (FR-015): CoreX → Settings → Mail gains the accent, a logo and the footer
  line; a filter `corex_email_brand` receives the `EmailBrand` for code that prefers it.
- **Its own template** (FR-014): a filter `corex_email_design` receives the standard design and
  returns an `EmailDesign`. A filter and not a container binding, because it is asked at
  sending time and so does not depend on which provider booted first.
- **A guard around it** (FR-017): `SiteEmailDesign` calls the client's design inside a `try`;
  if it throws, returns nothing, or returns a document without the message, the reply goes in
  the standard design and a notification to the people who manage email says which design and
  how. The message is looked for as its words, so a design is free to restyle it.
- **A start** (FR-019): `wp corex make:email-design` writes a working copy of the standard
  design into the site's own plugin. **A guide** with it, and Client impact (FR-020).

### D11. The header is a band of the card, and the logo has a box (slice 8)

The card becomes two rows of one bordered table: a header band, then the message. The band
holds the site's name, its logo, or its mark with the name beside it.

As built, "never both" did not survive a review that drew the header with its image missing:
a mark's box is 48 pixels wide and holds no name, and Outlook shows no image from a sender it
does not know. A logo at least twice as wide as tall is read as the name and stands alone; a
squarer one is a mark and has the name beside it as text. And a logo of known size has a width
and no height in its style, so that the name standing in for it is not cut off.

The logo's box is 180 by 48 pixels. Two things draw it, because two kinds of mail client read
two things. `max-width` and `max-height` with `width:auto;height:auto` for clients that read
styles. The image's own `width` and `height` attributes for those that do not, Outlook on
Windows first: `SiteEmailBrand` reads the logo's real size where the logo is in the site's
library (the Mail setting, or the theme's custom logo) and writes the size that fits the box
with its proportions kept. A logo given as a bare address by `corex_email_brand` has no known
size: it gets `height="48"` and no width, which every client scales from.

`EmailBrand` gains `logoWidth` and `logoHeight` (0 where unknown). The generator's stub is
written from the same header, and its test still draws an email with it.

### D12. A subject to start from (slice 9)

The submission's detail gains `reply_subject`: "Re: " and the form's name; with a conversation
(D13), "Re: " and the subject of its latest email, with one "Re: " and not two. Made on the
server, where the form's name and the conversation are, and translated there.

The box starts with it when the person has no draft. The draft's hook is given it as what
"nothing written" looks like, so an untouched default is not kept and the line "Draft saved."
does not appear for words nobody wrote. After a reply is sent the box goes back to it.

### D13. A conversation is the emails a submission's sender was sent (slice 10)

**A Message-ID of CoreX's own.** `<{uuid}@{site host}>`, made when a message is built, set on
PHPMailer through `phpmailer_init` as the plain-text part is (slice 2), and kept on the
attempt (`corex_email_log`). WordPress otherwise lets PHPMailer make one at the moment of
sending, which nothing can read back. Where a provider answers an id of its own it is kept
beside CoreX's (`EmailAttempt::$providerMessageId`, declared and never set), and
is the one named in a reply.

**Headers travel.** `MailRequest` gains `inReplyTo` and `references` (ids, validated as ids:
no free header text crosses the seam), carried to `EmailMessage` and written by the driver.
Not a general headers map: a caller that can set any header can set `Bcc`.

**The conversation is read, not stored twice.** For a submission: the attempt bound as its
submitter's confirmation (`corex_email_json`), then each stored reply's last delivered attempt.
`SubmissionConversation::of(int $submissionId)` answers the ids in order, from the attempts.
A reply's attempt already keeps its attempt id (D4); the Message-ID is looked up from it.

**A site's own confirmation.** `MailResult` gains `messageId`. A site that sends its own
confirmation calls `SubmissionConversation::began(int $submissionId, MailResult $sent)`,
which binds that attempt to the submission as CoreX's own flow does. Documented in the mail
guide with the listener it goes in.

**The choice.** `reply_delivery` gains `conversation`: whether there is one, and the subject
of its latest email. The box shows two radio buttons under the subject when there is one:
"In the conversation they already have" (chosen) and "As a new email". Where there is none it
shows a sentence: "This will be a new email: CoreX has no record of an email sent to this
person for this submission." The reply route takes `thread: continue|new`; the record keeps
it; the list of replies says "in the conversation" or "a new email".

**What it cannot promise.** Gmail groups by reference and by subject; some providers replace
the Message-ID. The guide says both, and the box warns beside the subject when the
conversation is chosen and the subject was rewritten.

As built, these differ, each for a reason met while building or found in review.

- **No new fields on `MailRequest`.** `EmailMessage` already carried a `headers` map that no
  driver read, and the capture already kept it. The reply's headers ride on that, and the
  driver writes three of them by name, each only as an id. A general headers map on the
  public seam is still not offered.
- **The id is part of the attempt's, not all of it.** WordPress's mailer rewrites a header
  value it finds too long as an encoded word, which no mail program reads as an id. Through
  PHP's `mail()` that is past 47 characters, and a whole id with a nine-letter host is 48: a
  test against the real mailer read `=?us-ascii?Q?<2222...` where the id should be. So an id
  is twenty characters of the attempt's on a host of up to 24, and fewer on a longer one, down
  to ten on a host of 34. For the same reason the driver names as many earlier emails as fit,
  the latest first, and asks how the email is sent only after whoever sets the mail server up
  has done so (its listener is at priority 9999).
- **The pane is told in `reply_conversation`, beside `reply_delivery`:** `{ available,
  subject }`. Whether a reply can be delivered and whether there is an email to continue from
  are two questions. The route takes `thread: conversation|new`, the words the pane shows, and
  a draft keeps the same field.
- **`SubmissionConversation::of()` takes the submission's record**, which the view already
  holds, and reads the emails bound to it whichever binding they are under: the email add-on
  keeps the ones that went to the person.
- **`began()` records only an email to the submission's sender.** A site's listener sends the
  team's notice from the same place.
- **`MailResult` is not given the id, and `began()` takes the request.** A site's own email
  goes through the older mail path, which records no attempt: its `MailResult` names an
  attempt nothing kept, and `Mailer::send()` answers nothing at all. But a request has an id
  of its own that it keeps through the mail queue, so the email leaves under an id made of
  that, whenever it leaves, and the site records the request.

### D14. What the email says it is (slice 11)

Not designed: it waits on the owner's answer to story 11.

### D10. Send again, and the sender (slice 5)

"Send again" composes the stored reply again and adds an attempt to its record; it is offered
on a reply whose last attempt was not delivered. A reply's attempts are its own list (D4), not
the form's notification bindings that `resend()` works from.

The sender: `mail.reply.from_name`, `mail.reply.from_address`, `mail.reply.reply_to` in the
Mail settings, validated when saved. `EmailMessage::$from` is an address only, on purpose (a
name that varies per message is how a spoof reads); a site-wide reply name set by an
administrator is a setting, not a per-message value, and travels as `fromName` beside it.

## Contracts

**`GET corex/v1/submissions/{id}`**, added to `submission`:

```text
reply_delivery  { mode: deliver|capture|refuse|unavailable, code, reason }
replies         [ { id, subject, body_html, actor_id, actor_name, created_at, state, message,
                    attempts: [ { attempt_id, state, message, occurred_at } ] } ]
```

**`POST corex/v1/submissions/{id}/reply`**, body `{ subject, body }` as now; answers
`{ result: MailResult }`. A message that reduces to nothing is 422, and so is a reply on a site
that would refuse it, with the reason.

**`corexSubmissions`** (the screen's configuration) gains `mailSettingsUrl`, present only for
a person who may change settings.

**`Corex\Mail\SubmissionEmailGateway`** gains `readiness(): ReplyReadiness`. Client impact: a
site that implements this seam itself has one method to add. The framework's two
implementations are the only ones in this repository.

Later slices add: `POST …/reply/preview` and `POST|DELETE …/reply/draft` (3); the filters
`corex_email_brand` and `corex_email_design` (4); `POST …/replies/{reply}/send` (5).

## Slice 1: a reply says what will happen to it and what did

**Server**

- `DeliveryDecision::$code`; `ReplyReadiness`; `readiness()` on the seam and both gateways.
- `ReplyBody::reduce()` as the route's sanitizer for `body`.
- `SubmissionReplyStore`, `WpSubmissionReplyStore` (one meta row to a reply);
  `SubmissionEmailService::reply()` refuses where a reply would be refused, and otherwise sends
  and stores; `SubmissionReplyView` gives the detail record `replies` and `reply_delivery`.
- Anonymizing removes `corex_submission_replies`.

**Interface**

- The readiness notice at the top of the Reply section; Send off when a reply would be refused.
- The outcome said in the Reply section; the box kept when the reply was not sent.
- "Replies" listed in the section: subject, who, when, a state badge, the text.
- The history line names the reply's state.

**Not in slice 1**: the template, the editor, the preview, "Send again", the sender.

## What later slices need from slice 1

- The reply record is where a second attempt is added (5) and what a saved reply is made from (6).
- `replyHtml.js` is the paste filter of the editor (3), and moved to `admin/components/` with it.
- `ReplyReadiness` is what the preview's "this will be captured" line reads (3).
