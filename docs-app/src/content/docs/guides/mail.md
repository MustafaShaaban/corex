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
- **Driver** — `WpMailDriver` sends via `wp_mail` with the configured from-identity.
- **Log** — each send is recorded to the `corex_email_log` CPT.

## Inspect email state in wp-admin

**Corex → Email Studio** is available when the CoreX Email add-on is active. Its Overview, Templates, Layouts,
Partials, and Variables tabs inspect the real engine state. Registered-template detail provides Edit, Preview,
Plain text, Test send, Routing, and Delivery logs. Preview and Plain text use the real renderer; Layouts previews
the active brand wrapper; Variables lists only detectable `{{ path }}` placeholders from registered template
output; Delivery logs are real site-wide attempts and are labelled as not yet filterable by template.

The studio does not mutate code-defined templates or routing. Test Send stays disabled until the neutral `Mailer`
seam returns a per-send result that a capability + nonce-gated action can report truthfully.

## Configure

`COREX_MAIL_DRIVER` (and host/port/credentials) live in `.env`; secrets are documented in
`.env.example`, never committed. The bulk **mail queue** (Action Scheduler) is gated behind
the `mail_queue` [feature flag](/guides/configuration/).
