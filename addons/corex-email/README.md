# Corex Mail

The Corex transactional email engine: code-defined templates with safe merge variables, a
secured send pipeline (header-injection guard + recipient validation), a driver abstraction,
and an audit log. Built on corex-core — the event seam, Config, and the data layer. Works on a
stock install with corex-core active; no SMTP plugin or other optional plugin is a hard
dependency.

> Requires the `corex-core` plugin active. The default driver sends through `wp_mail`, so it
> honors whatever SMTP/MTA the site already has.

## Send an email

```php
use Corex\Email\Mail;

Mail::to($user->email)
    ->template('welcome')
    ->with(['user' => ['name' => $user->name], 'site' => ['name' => get_bloginfo('name')]])
    ->send();

// Ad-hoc (no template) — you own the body's escaping:
Mail::to('team@example.com')->subject('New lead')->body('<p>…</p>')->send();
```

`send()` returns an `EmailResult` (`isSent()`, `status`, `logId`) and **never throws** — a
delivery failure or a security rejection is caught and logged. Recipients also accept a role or
a dynamic context path, plus cc/bcc and reply-to:

```php
Mail::to('owner@example.com')
    ->toRole('administrator')                 // every admin's email (one bounded query)
    ->toDynamic('event.submitter.email')       // a whitelisted context path
    ->cc('cc@example.com')->replyTo('noreply@example.com')
    ->template('contact-notification')->with([...])->send();
```

## Templates

A template is a class — a stable name and the subject/body sources. The body is straight-line
HTML with `{{ path }}` placeholders; register it with the `TemplateRegistry`.

```php
use Corex\Email\Template\EmailTemplate;
use Corex\Email\Template\MailContext;

final class WelcomeTemplate extends EmailTemplate
{
    public function name(): string { return 'welcome'; }
    public function subject(MailContext $context): string { return __('Welcome!', 'corex'); }
    public function body(MailContext $context): string
    {
        return '<p>' . esc_html__('Hi', 'corex') . ' {{ user.name }},</p>';
    }
}
```

**Merge is safe by construction.** A placeholder resolves only from the whitelisted `MailContext`
(roots like `event`, `site`, and named models); a path outside it — or a non-scalar leaf —
renders empty. Every body value is escaped (`htmlspecialchars`), so a value containing markup is
never live HTML; there is no code execution in templates. The merged body is wrapped in a shared
layout whose brand name, accent color, logo, and text direction come from the resolved
`theme.json` (including any `brand.json` override), so mail is on-brand and RTL-correct.

## Security

Applied automatically on every send (you cannot bypass it):

- **Header-injection guard** — a CR, LF, or control character in the subject or a header field
  rejects the message before delivery (status `rejected`).
- **Recipient validation** — each address is validated; invalid ones are dropped and logged. A
  message with no valid recipient is not sent (status `failed`).

## Delivery & configuration

Delivery goes through the `MailDriver` abstraction; the default `WpMailDriver` uses `wp_mail`.
The from-identity and reply-to come from the Config engine:

| Config key | WordPress option | Env |
|---|---|---|
| `mail.from.name` | `corex_mail_from_name` | `MAIL_FROM_NAME` |
| `mail.from.address` | `corex_mail_from_address` | `MAIL_FROM_ADDRESS` |
| `mail.reply_to` | `corex_mail_reply_to` | `MAIL_REPLY_TO` |

An empty from-identity falls back to the site's own. A provider driver (SES/Brevo/…) is an
additive change behind the same interface.

## Audit log

Every attempt is recorded as a non-public `corex_email_log` post with its final status
(`sent` / `failed` / `rejected`), recipients, and subject — queryable through the repository:

```php
$logs = Corex\Boot::app()->container()->make(Corex\Email\Log\EmailLogRepository::class);
$failed = $logs->byStatus('failed');   // Collection<EmailLog>
```

## Forms integration (detect-and-defer)

The Corex Forms email listener depends on the neutral `Corex\Mail\Mailer` seam in corex-core,
never on this add-on. When Corex Mail is active (the seam is bound), a contact-form submission is
delivered as the `contact-notification` template (rendered + logged); when it is not, Forms falls
back to basic `wp_mail`. Neither plugin hard-depends on the other (Principle IX).

## Out of scope (deferred to later specs)

Action Scheduler queue + retries + rate limiting, attachments, multi-provider drivers and
credential encryption, the DataViews admin UI, CLI generators, suppression/unsubscribe,
per-language template variants, and the WooCommerce override.

## Tests

```bash
composer test              # headless: renderer, header guard, recipient resolver, mail service
composer test:integration  # real ./wp: templated send + log, header-injection rejection, the Mailer seam
```
