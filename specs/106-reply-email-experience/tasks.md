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
- [ ] T044 Pest unit and integration: a draft is one person's, for one submission; saved,
      read, discarded; removed when the reply is sent; gone with the submission
- [ ] T045 `SubmissionReplyDrafts`, its routes
- [x] T046 Jest (rendered): the Reply section with the editor, the preview dialog at two
      widths. Not yet: the draft restored on reopening (with T044)
- [x] T047 The Reply section on the editor; the preview. Not yet: the draft (with T045)
- [x] T048 Playwright: write a formatted reply with the keyboard only, preview, send; the
      captured email is the preview; an Arabic paragraph reads from the right; measured
      spacing of the toolbar, light and dark, both directions
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
