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
  draws a message as a whole document. A reply from the Submissions inbox is the first email
  drawn this way; the others still go through the layout above. The inbox's **Preview** asks
  `POST corex/v1/submissions/{id}/reply/preview` for that document and its plain text, built by
  the code that builds the sent one. It sends nothing and keeps nothing of the reply; a site's
  email design that fails there is reported as it is when a reply is sent.
- **Log** — each send is recorded to the `corex_email_log` CPT.
- **Conversation** — an email sent by Email Studio, or through `Corex\Mail\Mailer`, leaves
  under a `Message-ID` CoreX chose (`Corex\Mail\MessageId`), on a site whose address has a
  host name to make one of. A reply from the Submissions inbox can name the email it answers
  (`In-Reply-To`, `References`), which is what a mail program reads to show it under that one.

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

**Corex → Email Studio** is available when the CoreX Email add-on is active. Its Overview, Templates, Layouts,
Partials, and Variables tabs inspect the real engine state. Registered-template detail provides Edit, Preview,
Plain text, Test send, Routing, and Delivery logs. Preview and Plain text use the real renderer; Layouts previews
the active brand wrapper; Variables lists only detectable `{{ path }}` placeholders from registered template
output; Delivery logs are real site-wide attempts and are labelled as not yet filterable by template.

The studio does not mutate code-defined templates or routing. Test Send stays disabled until the neutral `Mailer`
seam returns a per-send result that a capability + nonce-gated action can report truthfully.

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

`COREX_MAIL_DRIVER` (and host/port/credentials) live in `.env`; secrets are documented in
`.env.example`, never committed. The **mail queue** is gated behind the `mail_queue`
[feature flag](/guides/configuration/). With it on, a send is deferred so the request that
asked for it does not wait for the mail server: through Action Scheduler where that is
installed, and through WP-Cron where it is not. On WP-Cron the message leaves on the site's next
cron run, so it suits the few messages a form sends; install Action Scheduler to send to a long
list.
