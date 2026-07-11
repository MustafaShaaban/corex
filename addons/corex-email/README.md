# CoreX Mail

CoreX Mail is the optional transactional-email engine for CoreX. It provides editable and code-defined templates,
safe merge variables, environment-aware delivery, local capture, trigger routing, queue support, immutable delivery
attempts, and a private audit trail. It requires `corex-core`; SMTP and WooCommerce remain optional.

## Delivery safety

Delivery is fail-closed:

- `local` and `development` always capture locally and never contact a real transport.
- `staging` captures unless a matching provider and live delivery are deliberately enabled.
- `production` rejects sends until both provider gates pass.

The built-in provider name is `wp-mail`. It uses WordPress `wp_mail()`, including a configured SMTP/MTA plugin.
Another provider label stays blocked until a matching driver is registered, so configuration cannot impersonate a
transport.

| Config key | WordPress option | Environment variable |
|---|---|---|
| `mail.from.name` | `corex_mail_from_name` | `MAIL_FROM_NAME` |
| `mail.from.address` | `corex_mail_from_address` | `MAIL_FROM_ADDRESS` |
| `mail.reply_to` | `corex_mail_reply_to` | `MAIL_REPLY_TO` |
| `mail.provider` | `corex_mail_provider` | `MAIL_PROVIDER` |
| `mail.live_delivery` | `corex_mail_live_delivery` | `MAIL_LIVE_DELIVERY` |

An empty sender falls back to the site identity. For deliberate Production delivery through the built-in driver,
open **CoreX → Settings → Mail**, select `wp-mail`, verify the site's WordPress transport, then enable live delivery.

## Functional Email Studio

CoreX → Email Studio provides:

- template identities with immutable numbered drafts and explicit activation;
- Transactional, Minimal, Newsletter, and dependency-gated WooCommerce layouts;
- append-only reusable partial revisions;
- schema-declared `{{ variable.path }}` placeholders with escaped values;
- desktop, mobile, and RTL previews in a script-disabled iframe;
- generated or manual plain text, routing, test sends, health checks, captures, attempts, and retry lineage;
- capability-gated reads and capability + REST-nonce-gated mutations under `corex/v1/email-studio`.

Saving content appends a version; it never overwrites history. Activation moves only the active-version pointer.
Scripts, event handlers, executable URLs, PHP, header control characters, unknown placeholders, and undeclared
variables are rejected before persistence.

## Route a notification

Routes bind a trigger to one active editable template. Recipient and reply-to rules are either fixed validated
addresses or dot paths resolved only from the supplied event context. The example assumes an injected
`EmailRouteRepository $routes` and an existing `EmailTemplate $template`.

```php
use Corex\Email\Routing\EmailRoute;

$routes->save(new EmailRoute(
    id: 0,
    trigger: 'forms.contact.submitted',
    templateId: $template->id,
    recipientRules: [['source' => 'context', 'path' => 'submission.email']],
    replyToRule: ['source' => 'context', 'path' => 'submission.email'],
    enabled: true,
    updatedBy: get_current_user_id(),
    updatedAt: new DateTimeImmutable(),
));
```

Built-in callers emit:

- `forms.<form-slug>.submitted`
- `access.request.created`
- `access.request.approved`
- `access.request.denied`

Forms checks its active route first and retains the code-defined `contact-notification` / `wp_mail` fallback when
no route exists. Access state changes remain authoritative if optional notification delivery fails. Both consumers
depend on the neutral `Corex\Mail\RoutedMailer` seam, not this add-on.

## Code-defined mail

Existing code templates remain supported:

```php
use Corex\Email\Mail;

$result = Mail::to('owner@example.com')
    ->template('contact-notification')
    ->with([
        'submission' => ['name' => 'Sam'],
        'site' => ['name' => get_bloginfo('name')],
    ])
    ->send();
```

`send()` returns `EmailResult` and never aborts the triggering request. The service validates recipients, rejects
header injection, catches driver failures, and records the real outcome.

A code template extends `Corex\Email\Template\EmailTemplate`; its HTML may contain `{{ path }}` placeholders.
The renderer reads scalar values only from `MailContext`, escapes body values, and wraps output in the brand layout
resolved from `theme.json`.

## Attempts, capture, and queue

Development captures retain inspectable message content in private WordPress records. Delivery attempts store a
redacted recipient plus its HMAC lookup hash, template, state, provider, event, timestamp, retryability, request
correlation, and parent-attempt relationship. Captured, rejected, sent, failed, queued, bounced, and opened are
distinct typed states.

The legacy `corex_email_log` remains available for code-defined mail. The `mail_queue` feature flag decorates the
neutral Mailer seam with Action Scheduler when it is installed; otherwise delivery remains inline. Action Scheduler
is never a hard dependency.

## Security and extension boundaries

- Email Studio REST reads require `manage_options`; mutations also require a valid WordPress REST nonce.
- Private Studio assets use the non-public `corex_email_asset` post type.
- Preview iframes have no script permission and include a restrictive content security policy.
- The driver interface is the extension point for provider APIs.
- Attachments, credentials for additional providers, provider webhooks, suppression processing, and per-language
  variants require dedicated provider/locale contracts and are not simulated by `wp-mail`.

## Tests

```bash
composer test
composer test:integration
npm run test:js
```

Focused coverage lives in `tests/Unit/Email`, `tests/Integration/Email`,
`tests/Integration/Mail/EmailStudioLifecycleTest.php`, and the Email Studio client-state suite.
