# Research: Emails that send what was designed

Each entry is a decision the stories leave to the build, with why and what else was weighed. Where it is a gap the
stories do not settle, it was decided under the owner's 2026-10-21 mandate with the recommended answer and is logged in
`DECISIONS.md` under the number given.

## R1 One frame, drawn by one class (FR-012 to FR-016; DECISIONS #450)

**Decision.** `Corex\Email\Design\EmailFrame` draws every email CoreX draws. It takes an `EmailContent` (subject, body,
recipient and, new and last, its **kind** and its **links**), asks the site's brand (`SiteEmailBrand`, which already
reads Settings → Mail) and hands both to the site's `EmailDesign` (`SiteEmailDesign`: the site's own design where it
accepts the kind, `StandardEmailDesign` otherwise). The standard design's footer gains the kind's reason line and a
line of links. `Layout` (the second frame) is removed; `TemplateRenderer` (code templates) and `EmailTemplateService`
(Studio templates) draw through the frame.

- **Kinds** (`EmailKind`): `reply`, `notification` (to the team), `confirmation` (to someone who filled in a form,
  subscribed or applied), `newsletter`, `account`, `message` (anything else; no reason line). The reason line is
  "You receive this email because …" with the site's name.
- **Links**: the site's privacy page on every kind when the site has a published one
  (`get_privacy_policy_url()`); the sender adds its own (the newsletter's unsubscribe link). A "Manage preferences" link
  is drawn only where a site supplies an address through `corex_newsletter_preferences_url`: corex-newsletter has no
  preferences page, and the owner's no-dead-UI rule rules out a link to one that does not exist.
- **Accent**: the Studio layout's accent region when it is `#rrggbb`; otherwise Settings → Mail's accent; otherwise
  the brand's primary colour from theme.json when it is `#rrggbb`; otherwise CoreX's own. This fixes the default layouts'
  empty accent, which `??` never fell back from.
- **Logo**: always the frame's header band. A Studio layout's header region is drawn inside the card, under the band.
  A paragraph or bold line in a header or footer region that says only the site's name is dropped: the shipped header
  and footer partials say exactly that, and the band and the footer already do.

**Alternatives.** Keep `Layout` and fix its two defects: still two frames, and the Mail settings still reach replies
only. Make `Layout` call the design: two names for one thing. A frame per kind: the story asks for one.

## R2 A site's own design is given only the kinds it accepts (FR-016; DECISIONS #451)

**Decision.** A new interface `KindAwareEmailDesign extends EmailDesign` adds `accepts(string $kind): bool`. A design
that implements only `EmailDesign` is a design for replies, as every such design has been since spec 106; any other kind
is drawn in the standard design, with no failure reported (it is not a failure). A design that accepts a kind and fails
while drawing falls back and is reported, as replies do.

**Why.** Muva's design says on its band that the email is a reply. Given a lead or a newsletter, it would mislabel it.

## R3 Code templates are drawn in the frame, with a kind and a plain-text part (FR-012, FR-018; DECISIONS #451)

**Decision.** `EmailTemplate` gains `kind()` (default `message`) and `links(MailContext)` (default none). The contact
notification is a `notification`; newsletter mail `newsletter` with its unsubscribe link moved into the frame's links;
bookings, careers and guides confirmations `confirmation`, their team emails `notification`. `TemplateRenderer` returns
a plain-text version made by `PlainText::of()` from the merged body, with the frame's footer as its signature, and
`MessageBuilder` sends it as the text/plain part. A non-contact code-defined form's notification, which was a finished
body with no frame, is the new code template `form-notification`, drawn from the answers; its fallback body for the
`wp_mail()` floor is the plain one-answer-per-line text, which the floor sends as plain text (it used to hand the floor
HTML).

**Not changed.** A `MailRequest` with no template name is a finished message and is sent as given (FR-017).

## R4 The preview is the send (FR-001 to FR-006; DECISIONS #452)

**Decision.** Two routes draw from the draft being edited, whether it is saved or not, with the same
`EmailTemplateService::render()` that sends: `POST /corex/v1/email-studio/templates/{id}/preview` answers the subject,
HTML, plain text, the sample values used and any warnings; `POST /corex/v1/email-studio/test` with a `template_id` draws
the same email and sends it with "[Test] " before the subject, the template's From and the plain text. Sample values
come from one place, `EmailSampleValues`: the site's real name and address, "Jane Cooper", "jane@example.com", a
believable message, an "All answers" list of three answers, and "Sample <field>" for any other submission field. The
request may carry edited samples; they are used for that draw and never stored. The result of a test send is said in
words built from the attempt's state and the policy's decision code ("Captured: nothing left this site", "Delivered to
the mail server", "Blocked: no mail provider is set", "Blocked: live delivery is switched off", "Failed: the mail server
did not accept it"), with the reason and the address of its row in the log (`&tab=logs#corex-attempt-<id>`).

A template that cannot be drawn answers 422 with the field errors, each naming the variable or partial; the Test send
tab then shows the error and no button. An invalid address is refused before anything is drawn.

**Alternatives.** Keep a browser preview and draw only the test on the server: two drawings that can disagree, which is
the defect.

## R5 What a Studio send carries (FR-007 to FR-011; DECISIONS #453)

**Decision.** Every Studio send path builds its message with the template's From address (when set and valid), From
name (when set) and the rendered plain text: `EmailRouteMessageFactory` (routes and flow bindings), the inbox's
`resendTo()`, the log's resend (the attempt's template version gives the From; the plain text is made from the typed
body) and the test send. `EmailStudioService` runs the `HeaderGuard` over subject, From, From name and Reply-To before
any decision, as `MailService` does, and refuses a message that fails it. The From address becomes optional on save;
an invalid one, or a From name with a line break, is refused with a sentence that names the field. A From address on
another domain answers the transport warning's own sentence as a warning.

The automatic plain text is `PlainText::of()` of the arranged body (regions and content), which keeps paragraphs,
breaks, list items and link addresses; a merged value is escaped into the HTML, so decoding it gives it back whole
("under <500 EGP"). A hand-written plain text merges values as they are: plain text is not markup.

## R6 Variables (FR-025 to FR-030; DECISIONS #454)

**Decision.** `EmailVariableCatalog` (corex-email) is the one list: the configured `mail.variables` schema plus what
each kind of email supplies, in groups: every email (`site.name`, `site.url`, `links.privacy`, `recipient.email`),
form emails (`submission.<field>`, `form.title`, `form.answers`, `links.submission`, `failure.message`), newsletter
(`action.url`, `links.unsubscribe`, `links.preferences`), account (`user.name`, `user.email`, `action.url`,
`account.url`, `reset.expires`, `reset.asked`), access requests (`request.*`, `requester.*`, `reviewer.name`). A key in
none of them (`recipient.name` in the shipped schema) is listed under "Not supplied by CoreX". A placeholder is
**known** when it is in the catalog or matches `submission.<field>`; an unknown one is refused on save, named. The
template's declared keys are taken from its content; the comma-separated field goes.

- `{{ form.answers }}` is drawn by CoreX from `form.answers` in the context (a list of label, value and, for a file,
  the submission's address), as an escaped table in the HTML and "Label: value" lines in the plain text.
- **Missing at send time**: a paragraph, list item, heading or table row whose every placeholder has no value is left
  out; any other empty placeholder is replaced by nothing. The names are returned with the render and stored on the
  attempt (`missing_variables`); the log row says "Sent without: …".
- **Warnings on save** name each variable the emails the template is bound to do not supply. Which emails: a default
  template's own kind, its routes' triggers (`forms.*` → form emails, `access.*` → access), and the flows that bind it,
  asked through a new corex-core contract `Corex\Mail\MailTemplateUsage` that corex-forms implements.
- **Binding** a template to a flow is refused by the flow validator while it uses a variable the flow does not supply
  (a field the flow does not have counts), unless the binding carries `accept_missing: true`, which the Emails tab sets
  when the person ticks "Send it without them". `MailTemplateCatalog` entries gain `variables`, the keys the active
  version uses.

## R7 Default templates (FR-019 to FR-024; DECISIONS #455)

**Decision.** `DefaultEmailTemplates` (corex-email) holds each default's slug, name, kind, layout and shipped content,
written in the site's language (`EmailLanguage::during`). corex-email registers four; corex-profile adds "Password
reset" and "Welcome to your account" when it is active. On `init`, a default whose slug no template uses yet is
created; the defaults are **active** on a site that had no Email Studio defaults before this release (option
`corex_email_studio_defaults_version` empty) and **drafts** on an upgraded one, and the choice is kept
(`corex_email_default_templates_state`) so a default an add-on adds later follows the same rule. Templates are made
under the first administrator's id, since a record needs a person and `init` may run with nobody signed in.

- **Reset** (`POST …/templates/{id}/reset`) saves the shipped content as a new version and, when the template is
  active, activates it; the edited version stays in the history. Offered only for a default, behind a confirmation.
- **New flows** are created with the two bindings when both defaults are active: the team gets "Admin notification"
  (to the site's admin address, Reply-To the `email` field), the submitter "Contact confirmation" (to the `email`
  field). A submitter confirmation whose form gives no address is recorded `no_recipient` and does not fail the
  submission's email stage.
- **Fallback.** When a flow's team notification has no email to send (its template has no active version, or none of
  its recipients resolves), the PHP contact notification goes to the admin through the notification dispatcher. A
  binding switched off sends nothing, as today; so no upgraded site gains an email.
- **Newsletter** sends "Double opt-in" and, on confirmation, "Newsletter welcome", when active, through R8.

## R8 Designed emails on the shared mailer (FR-021, FR-031 to FR-034; DECISIONS #456)

**Decision.** A corex-core contract, `Corex\Mail\DesignedEmails`, with `available(string $slug): bool` and
`send(string $slug, array $to, array $context, string $path): ?MailResult`. corex-email implements it: the active
version of the template with that slug is drawn (frame, From, plain text) and sent through `MailService`, the shared
mailer, so the delivery rule is the one every other CoreX email on it follows: a production site sends, a development
or staging site captures (spec 113's gate). `null` means it could not be drawn; the caller sends its own email and the
failure is recorded in the activity log (`email.template.fell_back`) and announced as an `EmailDesignFailedEvent`.

**Why the shared mailer and not Email Studio's own sending rule.** Studio sends are blocked on a production site with
no provider set, which is how both clients run. A password reset or a subscription confirmation that a new production
site could not send would be a regression against WordPress's own email, which sends.

## R9 Account emails (FR-031 to FR-034; DECISIONS #457)

**Decision.** In corex-profile, while a reset asked through the account block is answered (`ResetEmailLink::during`),
`send_retrieve_password_email` is answered `false` when "Password reset" is available: CoreX makes the key with
`get_password_reset_key()` and sends the template with `action.url` (the account page's reset form,
`ResetLink::emailed()`), `user.*`, `reset.asked` (the time, in the site's format) and `reset.expires` (from
`password_reset_expiration`, "24 hours" by default). When it cannot be drawn, WordPress sends its own, relinked as
before. A reset asked on the login screen is not touched (DECISIONS #341). Registration sends "Welcome to your account"
instead of `wp_new_user_notification()`, linking to the account page the form was on (its signed page mark is now sent
by the register form too) or the home page; when it cannot be drawn, WordPress's own email is sent as before.

## R10 A plain-text version on a finished message (FR-035 to FR-037; DECISIONS #458)

**Decision.** `MailRequest` gains `?string $plainText = null` as its last constructor argument; an empty or
whitespace-only value is stored as null. `RequestMailer` hands it to `MessageBuilder`, which puts it on the
`EmailMessage` of a finished message only (a template makes its own); `MailRequestPayload` carries it through the queue;
the capture already keeps `plainText`. Email Studio's log shows each captured email's plain text in a disclosure.

## R11 Release notes and client sites

Before and after for each kind is described in words in the changelog (a reply, a contact notification, a newsletter
email, a Studio template email): the band, the accent, the footer's reason and links. No client file changes; Muva's
design is a reply design and keeps its replies; both clients' finished messages are untouched (SAFE-D2).

## R12 How it is tested

Pest unit tests for the frame, kinds, the sample values, rendering (missing variables, the answers block, plain text),
the payload and the seams; Pest integration for the routes (preview, test send, reset), defaults installation (fresh
and upgrade), flow defaults and fallback, newsletter and account emails captured on a development site, and finished
messages captured unchanged; Jest for the Email Studio client and the flow Emails tab; Playwright for Preview, Plain
text, Test send and the log (light, dark, right to left, 375px). Tests never deliver mail: they capture, or use
`MailDelivery::DELIVER` with a `pre_wp_mail` spy.
