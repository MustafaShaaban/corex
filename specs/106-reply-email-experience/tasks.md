# Tasks: Replies that are written and sent as designed emails

Tests are written first and seen to fail. One slice per pull request. Decisions are in
[plan.md](plan.md).

## Slice 0: a reply keeps its line breaks

- [x] Done in #297, released in v0.44.0.

## Slice 1: a reply says what will happen to it and what did (US0, US4.1, US4.3; FR-021, FR-023, FR-025, FR-028 to FR-031)

**Server**

- [x] T001 Pest unit: `DeliveryPolicy` names each of its seven outcomes with a code
- [x] T002 `DeliveryDecision::$code`
- [x] T003 Pest unit: `EmailStudioSubmissionGateway::readiness()` answers `refuse` with no
      provider and with live delivery off in production, `capture` in development and staging,
      `deliver` when both are set; the reason names the Mail settings; the answer and what
      `reply()` then does agree in each case
- [x] T004 `Corex\Mail\ReplyReadiness`; `readiness()` on the seam, on the Email Studio gateway
      and on the unavailable one
- [x] T005 Pest integration (the reduction is WordPress's own): `ReplyBody::reduce()` keeps paragraphs, emphasis, lists and web, mail
      and telephone links; removes scripts, styles, images, attributes and other schemes;
      turns typed lines into paragraphs; gives a schemeless link `https://`
- [x] T006 `ReplyBody`, declared as the reply route's sanitizer; `SubmissionReply` refuses an
      empty result
- [x] T007 Pest unit: `SubmissionEmailService::reply()` stores the reply with its subject, its
      reduced text, who sent it and its attempt, whatever the state; the history entry carries
      the reply's id and no text; nothing is stored when the submission is out of scope
- [x] T008 `SubmissionReplyStore`, `WpSubmissionReplyStore`, the service, container bindings
- [x] T009 Pest integration: the route answers `result`; the detail record carries
      `replies` with the sender's name and `reply_delivery`; anonymizing removes the replies;
      deleting for good leaves nothing
- [x] T010 The reader's `replies` and `reply_delivery`; the anonymize list; `mailSettingsUrl`

**Interface**

- [x] T011 Jest: `replyHtml.js` reduces markup to the allowlist; `replyOutcome()` words each
      state; `readinessNotice()` words each mode with and without a settings link; the history
      line names the reply's state
- [x] T012 `replyHtml.js`, the state helpers in `detailState.js`
- [x] T013 Jest (rendered): with `refuse` the notice is shown and Send is off and described by
      it; with `capture` the notice is shown and Send works; a reply that was not sent leaves
      the box as it was; a sent one empties it and is listed
- [x] T014 The Reply section: notice, outcome, the list of replies; `useInbox.reply()` answers
      the result; styles from tokens
- [x] T015 Playwright: on the development site the section says a reply is kept and not
      delivered; a reply is sent, the pane says kept, and it is listed with its text; measured
      spacing of the notice and the list, both directions, light and dark
- [x] T016 Guards; the inbox guide; CHANGELOG with Client impact; PROGRESS; DECISIONS

## Slice 2: the standard template, with a plain-text version (US2; FR-008 to FR-013)

- [x] T020 Pest unit: `EmailBrand::standard()` holds the palette the support email uses;
      every text and background pair it offers has a contrast of 4.5 to 1, light and dark
- [x] T021 `Corex\Email\Design\EmailBrand`; the support email reads it; `SupportEmailPalette` removed
- [x] T022 Pest unit: `StandardEmailDesign` draws the logo with the site's name as its `alt`,
      or the name; a footer naming the site; `dir`; the message once; no width over 600; no
      style outside the brand's values; escapes the subject and the site's name
- [x] T023 `EmailDesign`, `EmailContent`, `StandardEmailDesign`
- [x] T024 Pest unit: `PlainText::of()` writes paragraphs, list items and links out
- [x] T025 `PlainText`
- [x] T026 Pest unit: `ReplyComposer` answers the HTML and the text, in Arabic as in English;
      the gateway sends both
- [x] T027 `ReplyComposer` over an `EmailBrandSource`; `EmailMessage::$plainText`, carried by
      `MailService`; the gateway composes through it
- [x] T028 Pest integration: a reply sent through `wp_mail` is `multipart/alternative` with
      both parts; a message with no plain text is sent as before; the capture holds both
- [x] T029 `WpMailDriver` and the capture carry the plain text
- [x] T030 Playwright: the captured reply drawn at 320, 375 and 800 pixels, light and dark: no
      sideways scroll, measured contrast, the header and footer, and an Arabic line starting
      from the right beside an English one. A right-to-left site is the unit test's and was
      looked at as a screenshot
- [x] T031 Guards; the email guide; CHANGELOG with Client impact; PROGRESS; DECISIONS

## Slice 3: the editor and the true preview (US1; FR-001 to FR-007)

Built in two pull requests: the editor and the preview first, the draft after it.

- [x] T040 Jest: the editor's commands, shortcuts, toolbar keys and pressed states; paste is
      reduced; the value given back is reduced
- [x] T041 `CorexRichText`, `richTextCommands.js`; `replyHtml.js` moved beside them
- [x] T042 Pest unit and integration: the preview route answers what `reply()` then captures,
      byte for byte; it is guarded as the reply route is
- [x] T043 `POST …/reply/preview`
- [x] T044 Pest unit and integration: a draft is one person's, for one submission; saved,
      read, discarded; removed when the reply is sent; gone with the submission, with an
      anonymization and with the person
- [x] T045 `SubmissionReplyDrafts`, its store, its routes
- [x] T046 Jest (rendered): the Reply section with the editor, the preview dialog at two
      widths, the draft restored on reopening
- [x] T047 The Reply section on the editor; the preview; the draft
- [x] T048 Playwright: write a formatted reply with the keyboard only, preview, send; the
      captured email is the preview; an Arabic paragraph reads from the right; measured
      spacing of the toolbar, light and dark, both directions; a draft written, left,
      come back to and discarded
- [x] T049 Guards; guide; CHANGELOG; PROGRESS; DECISIONS, for the editor and the preview.
      The independent review ran the editor in a browser and named four defects and a dozen
      lesser ones; each is fixed with a test, and what is left is in DECISIONS #313

## Slice 4: a client's own template (US3; FR-014 to FR-020) — built before slice 3

- [x] T060 Pest unit: `corex_email_brand` and the Mail settings change the standard design's
      accent, logo and footer; an invalid colour is refused when saved
- [x] T061 The settings fields; the filter
- [x] T062 Pest unit: `corex_email_design` replaces the design for the reply and the preview;
      a design that throws, answers nothing or leaves the message out falls back to the
      standard one and is recorded
- [x] T063 `EmailDesigns` (the resolver and its guard); the activity entry and notification
- [x] T064 Pest unit: `make:email-design` writes a design that renders, in the site's plugin
- [x] T065 The generator and its stub
- [ ] T066 Client-layout check: a generated site with its own design sends in it and no
      framework file is changed. **Not added to the `client-site-layout` job.** The behaviour is
      held by `ReplyEmailDesignTest` (a design registered through the filter from outside the
      framework's classes) and by the generator's test, which loads what it wrote and draws
      with it (DECISIONS #311)
- [x] T067 The developer guide, with the example that runs; CHANGELOG with Client impact;
      guards; PROGRESS; DECISIONS

## Slice 5: "Send again", and the reply sender (US4.2, US5; FR-022, FR-024)

- [ ] T080 Pest unit: sending a stored reply again adds an attempt to it and a history entry;
      refused on a reply already delivered and outside the scope
- [ ] T081 `SubmissionEmailService::sendAgain()`; `POST …/replies/{reply}/send`
- [ ] T082 Pest unit and integration: with a reply sender set, From, its name and Reply-To are
      those; without, as today; an invalid address is refused when saved
- [ ] T083 The three settings; `EmailMessage::$fromName`; the driver
- [ ] T084 Jest and Playwright: "Send again" on a reply that was not delivered
- [ ] T085 The control; guards; guide; CHANGELOG; PROGRESS; DECISIONS

## Slice 6: saved replies (US6; FR-026)

Tasks are written when the slice is reached.

## Slice 7: every email in the template (US7; FR-027)

Tasks are written when the slice is reached. It changes how every existing email looks, and is
announced under Client impact before it is merged.

## Slice 8: a header that holds a logo of any shape (US8; FR-032 to FR-034) — built before slice 5

- [x] T100 Pest unit: the header is a band of the card; a square, a wide and a tall logo are
      each given a width and a height inside 180 by 48 with their proportions kept; a logo of
      unknown size is limited by height; no logo, the site's name in the band
- [x] T101 `EmailBrand::$logoWidth`, `$logoHeight`, `logoBox()`; `SiteEmailBrand` reads the
      logo's size; `StandardEmailDesign`'s header
- [x] T102 Pest integration: the size of the theme's logo, of the logo the Mail settings
      name, of a cut that says its size in its name, and none for an address nothing is
      known about
- [x] T103 The generator's stub from the same header; its test draws an email with it
- [x] T104 Playwright: the captured reply at three widths, light and dark: the header stands
      on the card's ground under the brand's edge. And a reply sent as a site with a square
      mark and as one with a wordmark (the mail fixture answers `X-Corex-E2E-Mail-Logo`): the
      logo inside its box, its attributes the size it was drawn at, the name beside a mark and
      level with it. A right-to-left header is the unit test's; the header with images not
      shown was drawn by a one-off script and looked at, and is not in the suite
- [x] T105 Guards; the email-design guide; CHANGELOG with Client impact (the email looks
      different; a site's own design is untouched); PROGRESS; DECISIONS. The review drew the
      header with its image missing and found the site's name cut off inside the logo's box:
      the name is now text beside a mark, and no logo has a height in its style

## Slice 9: a subject to start from (US9; FR-035, FR-036)

- [x] T110 Pest unit and integration: `reply_subject` from the form's name; one "Re: "
- [x] T111 `ReplySubject`; `reply_subject` in the submission's detail
- [x] T112 Jest: the box starts with it; a default alone is not a draft; a draft's subject
      wins; after sending or discarding the box goes back to it
- [x] T113 The Reply section and `useReplyDraft` on it
- [x] T114 Playwright: the box opens with it and no draft is kept of it; changed, the reply
      is sent with what was typed; the box goes back to it afterwards
- [x] T115 Guide; CHANGELOG; PROGRESS; DECISIONS. The subject from a conversation's latest
      email (story 9, scenario 4) comes with slice 10

## Slice 10: a reply in the conversation the person already has (US10; FR-037 to FR-043)

- [x] T120 Pest unit: a message is given a Message-ID of the site's host; only an id is an
      id; the gateway sets what a reply answers
- [x] T121 `Corex\Mail\MessageId`, `EmailMessage::withHeaders()`, `EmailStudioService`,
      `RequestMailer`, `WpMailDriver`; the id on the attempt. Not new fields on `MailRequest`:
      the message already carried headers, and the driver now writes three of them by name,
      each only as an id
- [x] T122 Pest integration: what WordPress's own mailer built carries the id CoreX chose,
      once, and the email it answers; nothing of one email is left on the next
- [x] T123 Pest unit and integration: `SubmissionConversation::of()` answers the confirmation,
      then each reply, in order; `began()` records a site's own confirmation
- [x] T124 `SubmissionConversation`, its store. Not `MailResult::$messageId`: a site's own
      email is sent under an id made of its request's, which the request keeps through the
      mail queue, so `began()` takes the request
- [x] T125 Pest unit and integration: a reply sent in the conversation names the latest email
      and the ones before it; as a new email, none; the record keeps which
- [x] T126 `SubmissionEmailGateway::reply()` takes the conversation; the route's `thread`
- [x] T127 Jest: the two choices where there is a conversation, the sentence where there is
      none, the subject that follows the choice, the warning when it was rewritten
- [x] T128 The Reply section; the list of replies says which way each went
- [x] T129 Playwright: reply both ways; the kept emails' headers; measured spacing of the
      choice, light and dark, both directions
- [x] T130 The mail guide (a site's own confirmation; what threading cannot promise);
      CHANGELOG with Client impact (the seam's signature); PROGRESS; DECISIONS
- [x] T131 Review, 2026-10-13 (DECISIONS #325): the way an email is sent is asked after the
      mail server is set up; an email sent on hearing how a reply went does not leave under
      the reply's id; `began()` records only an email to the submission's sender; a draft
      keeps the way it was to be sent; anonymizing removes what the site said it sent; one
      rule for what an id is, and an id as long as its host leaves room for. Each with a test
      that fails without it

## Slice 11: the email says what it is (US11; FR-044)

Tasks are written when the owner has answered story 11's question.
