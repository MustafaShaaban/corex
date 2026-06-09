# Quickstart & Validation: Corex Mail (MVP)

Runnable scenarios. Types live in [contracts/mail-contracts.md](./contracts/mail-contracts.md) and
[data-model.md](./data-model.md).

## Prerequisites

- corex-core active (specs 001–007); **corex-email** active; WordPress ≥ 7.0 at `./wp`. `composer install`.

## Run the tests

```bash
composer test               # headless: renderer, header guard, recipient resolver, mail service
composer test:integration   # real WP: a templated send records corex_email_log; Forms delegates to Corex Mail
```

## Scenario 1 — Send a templated email (US1, SC-001)

```php
use Corex\Email\Mail;

$result = Mail::to($user->email)
    ->template('welcome')
    ->with(['user' => ['name' => $user->name], 'site' => ['name' => get_bloginfo('name')]])
    ->send();

// $result->isSent() === true ; one corex_email_log row with status 'sent'
```
**Expected**: the recipient gets the rendered, brand-wrapped email; `{{ user.name }}` is merged and escaped;
exactly one audit record is written.

## Scenario 2 — Header injection is blocked (US2, SC-002)

```php
$result = Mail::to('ok@example.com')->subject("Hi\r\nBcc: victim@example.com")->body('x')->send();
// $result->status === 'rejected' ; nothing delivered ; one log row status 'rejected'
```
**Expected**: a subject (or any header field) with CR/LF never produces a sent email; the attempt is logged.

## Scenario 3 — Invalid recipients are dropped (US2, SC-004)

```php
$result = Mail::to(['good@example.com', 'not-an-email'])->subject('Hi')->body('x')->send();
// delivered to good@example.com only ; 'not-an-email' dropped + logged ; status 'sent'
```
**Expected**: invalid addresses never receive mail; valid co-recipients still do. All-invalid ⇒ 'failed'.

## Scenario 4 — Forms uses Corex Mail when active (US4, SC-006)

```text
corex-email active   → submit the contact form → admin notification rendered from the
                        'contact-notification' template, delivered, and a corex_email_log row exists.
corex-email inactive → submit the contact form → notification still sent via wp_mail fallback.
```
**Expected**: detect-and-defer via `container->has(Corex\Mail\Mailer::class)`; no hard dependency either way.

## Acceptance → scenario map

| Success criterion | Scenario |
|---|---|
| SC-001 one-line templated send | 1 |
| SC-002 header injection blocked | 2 |
| SC-003 whitelist/escape (no leak/XSS) | 1 (unit-detailed) |
| SC-004 invalid recipient dropped | 3 |
| SC-005 one audit record per attempt | 1, 2 |
| SC-006 Forms detect-and-defer | 4 (integration) |
| SC-007 headless cores unit-tested | `composer test` |
| SC-008 RTL + escaped render | 1 |
