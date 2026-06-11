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
    ->with(['name' => $name, 'message' => $message])
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

## Configure

`COREX_MAIL_DRIVER` (and host/port/credentials) live in `.env`; secrets are documented in
`.env.example`, never committed. The bulk **mail queue** (Action Scheduler) is gated behind
the `mail_queue` [feature flag](/guides/configuration/).
