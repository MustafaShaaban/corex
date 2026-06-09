# Corex Newsletter

Topic-based newsletter subscriptions with **double opt-in**, secure one-click unsubscribe, GDPR
consent, and an **on-publish trigger** that emails confirmed subscribers. Optional add-on; builds on
corex-core (custom tables, events), Corex Mail (008), and Corex Captcha (012).

## Flow

1. A visitor subscribes (email + topics + consent) via `POST /wp-json/corex/v1/newsletter/subscribe`
   (honeypot + captcha gated). A **pending** subscriber is stored and a **confirmation email** with a
   signed link is sent.
2. Clicking the link **confirms** the subscriber (signed token, fail-closed — no enumeration).
3. When a post in a **newsletter topic** is published, every **confirmed** subscriber of that topic is
   emailed (through Corex Mail), each with a signed **one-click unsubscribe** link.
4. Unsubscribing **suppresses** the address; suppressed/pending subscribers are never emailed.

## Data + security

- Subscribers live in a `corex_subscribers` custom table (topics as JSON, status, consent, timestamps).
- Confirm/unsubscribe use **HMAC-signed tokens** (`TokenSigner`, secret from `newsletter.secret` or
  `wp_salt`); a tampered token verifies to nothing. The email links carry their own auth (no nonce).
- The `newsletter_topic` taxonomy on posts is the shared topic set.

## Tests

```bash
composer test              # headless: token signing + the subscribe/confirm/unsubscribe lifecycle + notifier
composer test:integration  # real ./wp: the subscriber custom-table data path (subscribe -> confirm)
```

> Bulk sending on publish is a bounded pass; the **mail queue** (Action Scheduler) for very large lists is
> the deferred home. The email rendering + full REST/publish flow over HTTP are best confirmed in a browser.
