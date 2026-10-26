---
title: Send email (Corex Mail)
description: Templated, secured email via the Mailer seam and the corex-email add-on.
---

Corex Mail is the `addons/corex-email` add-on plus a neutral `Corex\Mail\Mailer` seam in
the core. Consumers (Forms, Newsletter, Careers…) send through the seam; if the add-on is
active it handles delivery, otherwise the framework falls back to `wp_mail` — no add-on is
a hard dependency.

## Send a message

```php
use Corex\Email\Mail;

Mail::to('user@example.com')
    ->template('contact-notification')
    ->with(['submission' => [
        'name' => $name,
        'email' => $email,
        'message' => $message,
    ]])
    ->send();
```

The builder also offers `toRole()`, `toDynamic()`, `cc()`, `bcc()`, `replyTo()`,
`subject()`, and `body()`.

Delivery is **best-effort and never throws** — failures are logged, not fatal.

## What the add-on provides

- **Templates** — `TemplateRenderer` merges `{{ path }}` placeholders into an escaped,
  brand-aware layout (pulled from `theme.json` / `brand.json`).
- **Security** — `HeaderGuard` rejects CR/LF/control characters in headers; recipients are
  resolved + validated.
- **Driver** — `WpMailDriver` sends via `wp_mail` with the configured from-identity. A message
  that carries a `plainText` is sent with it as the plain-text alternative of the HTML.
- **Design** — `Corex\Email\Design` holds what a designed email is drawn from: `EmailPalette`
  (the colours, light and dark, the one place an email's colours are written), `EmailBrand`
  (the site's name, address, logo, direction and language), and `StandardEmailDesign`, which
  draws a message as a whole document. `EmailFrame` puts every email CoreX draws in it: a reply,
  a code template, an Email Studio template (see [A site's own email design](/guides/email-design/)).
  The inbox's **Preview** asks
  `POST corex/v1/submissions/{id}/reply/preview` for that document and its plain text, built by
  the code that builds the sent one. It sends nothing and keeps nothing of the reply; a site's
  email design that fails there is reported as it is when a reply is sent.
- **Log** — each send is recorded to the `corex_email_log` CPT.
- **Conversation** — an email sent by Email Studio, or through `Corex\Mail\Mailer`, leaves
  under a `Message-ID` CoreX chose (`Corex\Mail\MessageId`), on a site whose address has a
  host name to make one of. A reply from the Submissions inbox can name the email it answers
  (`In-Reply-To`, `References`), which is what a mail program reads to show it under that one.

## A finished message with its own plain text

A message your code builds itself (its own subject and HTML body, no template) is sent exactly
as it is given. If your code also renders the same words as plain text, hand that over too: it
is sent as the text/plain part beside the HTML, for a reader whose mail program shows plain text.

```php
use Corex\Mail\Mailer;
use Corex\Mail\MailRequest;

$mailer->send(new MailRequest(
    to: [$email],
    subject: 'We received your enquiry',
    body: $renderer->html($lead),
    from: 'info@example.com',
    plainText: $renderer->text($lead),
));
```

`plainText` is optional and the last argument, so a request built without it is sent as it
always was: CoreX adds no text part of its own to a finished message. An empty value, or one of
spaces only, counts as none. It is kept through the mail queue, and on a development or staging
site the captured email keeps it: **Email Studio → Delivery logs → Show the email** shows both.

## Emails an owner designs, sent by your code

`Corex\Mail\DesignedEmails` sends an Email Studio template by its slug, through the same mailer
and under the same delivery rule as every other CoreX email on it. The newsletter's double opt-in
and welcome and the account add-on's password reset and welcome are sent this way.

```php
use Corex\Mail\DesignedEmails;

$sent = $designed->send('double-opt-in', [$email], [
    'action' => ['url' => $confirmUrl],
], 'Newsletter: confirmation');

if ($sent === null) {
    // No active template has that slug, or it could not be drawn: send your own email.
}
```

`null` means nothing was sent. When the template exists and could not be drawn (a partial it
names was removed), the people who manage email are told in the notification centre and the
activity log records it.

## Replies that continue your own confirmation

The Submissions inbox offers to send a reply "in the conversation they already have" when
CoreX has a record of an email it sent that person for that submission. A flow's own
confirmation is recorded by the flow. A site that sends its confirmation itself tells CoreX
so, where it sends it:

```php
use Corex\Config\Submissions\SubmissionConversation;
use Corex\Mail\Mailer;
use Corex\Mail\MailRequest;

final class SendLeadConfirmation
{
    public function __construct(
        private readonly Mailer $mailer,
        private readonly SubmissionConversation $conversation,
    ) {
    }

    public function handle(int $submissionId, string $email): void
    {
        $request = new MailRequest(
            to: [$email],
            subject: 'We received your enquiry',
            body: '<p>Thank you. We will be in touch.</p>',
        );
        $this->mailer->send($request);

        // Replies to this submission can now be sent under this email.
        $this->conversation->began($submissionId, $request);
    }
}
```

The email leaves under an id made of the request's own, whenever it leaves: at once, or later
from the mail queue. `began()` records that id, and not what became of the email, which
`send()` does not say. It records an email only where the submission's sender is one of the
request's recipients: called for the notice you send your own team, it records nothing.

What this cannot promise:

- Gmail keeps emails together by their subject as well as by what they answer. A reply whose
  subject was rewritten may stand alone there; the reply box says so when that is the case.
- A sending service that replaces an email's `Message-ID` with its own breaks the link: the
  reply arrives, and stands alone. This is not yet checked for an SMTP plugin's Gmail
  connection.
- Through PHP's own `mail()`, WordPress's mailer rewrites a header longer than 47 characters,
  so a reply names the one email it answers and not the ones before it; and on a site whose
  host name is longer than 34 characters it names none, and arrives as an email of its own
  though the inbox lists it as sent in the conversation. Where a plugin chooses a mail server
  on the `phpmailer_init` hook, a reply names up to the whole conversation, on any host.
  FluentSMTP chooses after that hook, for its SMTP and its Gmail connections alike, so on it
  a reply is written as it is for `mail()`.
- The id is made of the host of the address the request is handled on. A site with one
  domain per language, sending its confirmation later from the mail queue, may record one
  host and send under another: the reply then names an id that was never sent.
- Which emails a person was sent is recognised with the site's own secret keys. After a move
  to a host with new keys, earlier emails are not recognised and the next reply is a new
  email.
- An email sent before CoreX chose ids (before the release that carries this) cannot be
  continued.

## Inspect email state in wp-admin

**Corex → Email Studio** is available when the CoreX Email add-on is active. A template's **Preview** is drawn by
the server, by the code that sends it, from the draft being edited: its layout, partials and the frame, with a
sample value for every variable, shown at 600 and 375 pixels and right to left. **Plain text** shows the
plain-text version that is sent. **Test send** sends exactly that email to one address, with "[Test]" before its
subject, under the site's delivery rules, and says what happened with a link to its row in the log.

A template's From name and From address, when set, are what it leaves from; empty, it leaves from the sender in
Settings → Mail. **Variables** lists what each kind of email supplies, with the sample each is previewed with: a
form email also has each of the form's fields as `{{ submission.<field> }}`, and `{{ form.answers }}` lists every
answer with its label. A variable that has no value when an email is sent is left out, with the label it was the
point of, and the log row says "Sent without: …". A variable no email supplies is refused when the template is
saved, by name.

A new site starts with four templates active: Contact confirmation, Admin notification, Double opt-in and
Newsletter welcome (and, with the account add-on, Password reset and Welcome to your account). A new flow is bound
to the first two. A site upgraded from an earlier release gets them as drafts, bound to nothing. **Reset to shipped
version** puts one back as CoreX ships it, as a new version.

## The sender of a reply

`mail.reply.from_name`, `mail.reply.from_address` and `mail.reply.reply_to` name who a reply from
the Submissions inbox is from and where an answer to it goes. Each falls back to its general
counterpart (`mail.from.name`, `mail.from.address`, `mail.reply_to`). They are set under
**CoreX → Settings → Mail**, or as any config value is. No other email reads them.

`EmailMessage` has a `fromName` for this. It is not for a caller to vary per message: a sender's
name that changes with the message is how a spoof reads. A name is cleaned by one rule,
`Corex\Email\Message\SenderName::of()`: one line, `<`, `>` and `"` taken out, no `=?` sequence,
at most 100 characters. The gateway that sends a reply applies it and `WpMailDriver` applies it
again, to the general From name too. The mail service refuses a name with a line break in it. A
driver of your own is handed the reply's name already cleaned, and has to clean a name it reads
from anywhere else.

A name with no address to stand in front of (none set for replies and none set for the site) is
given to WordPress's mailer directly, where no plugin has chosen a name. A `wp_mail_from` or
`wp_mail_from_name` filter, which is how a transport plugin forces a sender, is applied by
WordPress after the header this driver writes and replaces it.

## Configure

CoreX has no SMTP driver and reads no mail host, port, username or password. It hands each
message to WordPress's `wp_mail()`, and the site's SMTP plugin or mail server holds those. What
CoreX reads is who a message is from and whether it may leave the site, set in **CoreX → Settings →
Mail** or in `.env` (`.env` wins):

| Config key | WordPress option | `.env` name |
|---|---|---|
| `mail.from.name` | `corex_mail_from_name` | `MAIL_FROM_NAME` |
| `mail.from.address` | `corex_mail_from_address` | `MAIL_FROM_ADDRESS` |
| `mail.reply_to` | `corex_mail_reply_to` | `MAIL_REPLY_TO` |
| `mail.provider` | `corex_mail_provider` | `MAIL_PROVIDER` |
| `mail.live_delivery` | `corex_mail_live_delivery` | `MAIL_LIVE_DELIVERY` |

Until 2026-10-13 this page named `COREX_MAIL_DRIVER`, and `.env.example` named it with
`COREX_MAIL_HOST`, `COREX_MAIL_PORT`, `COREX_MAIL_USERNAME` and `COREX_MAIL_PASSWORD`. Nothing reads
them; a `.env` that has them can drop them.

The **mail queue** is gated behind the `mail_queue`
[feature flag](/guides/configuration/). With it on, a send is deferred so the request that
asked for it does not wait for the mail server: through Action Scheduler where that is
installed, and through WP-Cron where it is not. On WP-Cron the message leaves on the site's next
cron run, so it suits the few messages a form sends; install Action Scheduler to send to a long
list.
