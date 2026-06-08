# Quickstart & Validation: Forms Engine

Runnable scenarios. Types live in [contracts/forms-contracts.md](./contracts/forms-contracts.md) and
[data-model.md](./data-model.md).

## Prerequisites

- corex-core active (specs 001–006); **corex-forms** active; WordPress ≥ 7.0 at `./wp`. `composer install`.

## Run the tests

```bash
composer test               # headless: dispatcher, validator, schema resolver, submission service
composer test:integration   # real WP: the secured submit lifecycle for the contact form
```

## Scenario 1 — Validate a payload (US1, SC-002)

```php
$result = $validator->validate($schema, ['email' => 'not-an-email', 'name' => '']);
// $result->isValid() === false
// $result->errors === ['name' => 'required', 'email' => 'email']   // one error per field (bail)
```
**Expected**: each supported rule (`required`/`email`/`max`/`min`/`numeric`) yields the exact field
error on bad input and none on good input; absent optional fields are valid.

## Scenario 2 — Dispatch to listeners in order (US2, SC-003, SC-008)

```php
$provider->listen(FormSubmittedEvent::class, $a);
$provider->listen(FormSubmittedEvent::class, $b);     // throws
$provider->listen(FormSubmittedEvent::class, $c);
$dispatcher->dispatch(new FormSubmittedEvent('contact', $values));
// → $a, then $b (logged on throw), then $c — all attempted, in order
```
**Expected**: every listener for the type runs once in registration order; a throwing listener is
logged and does not stop the others; listeners of other event types are not invoked.

## Scenario 3 — Secured submission (US3, SC-004, SC-006)

```text
POST corex/v1/forms/contact   (valid X-WP-Nonce, honeypot empty, valid fields)
  → 200 { ok: true, values: {…} }   and StoreSubmissionListener + SendEmailListener both ran
POST corex/v1/forms/contact   (bad/missing nonce)          → rejected, no side effect
POST corex/v1/forms/contact   (honeypot filled)            → rejected silently, no side effect
POST corex/v1/forms/contact   (message empty)              → 422 { errors: { message: 'required' } }, no side effect
```
**Expected**: the nonce/sanitize/throttle middleware + honeypot gate the side effects; only a fully
valid submission dispatches the event; a stored `corex_submission` is retrievable by slug.

## Scenario 4 — Form block on a page (US4, SC-005)

```text
Add the "Corex Form" block, choose the contact form → the page renders every field with a
<label for>, required markers, a nonce field, and a hidden honeypot; styling references only
var(--wp--preset--*); the form's view script is enqueued only on this page.
```
**Expected**: accessible (WCAG 2.2 AA) RTL-aware markup, token-only styling, i18n strings, conditional
script load.

## Acceptance → scenario map

| Success criterion | Scenario |
|---|---|
| SC-001 headless cores covered | `composer test` |
| SC-002 each rule exact error | 1 |
| SC-003 ordered dispatch | 2 |
| SC-004 secured lifecycle | 3 (integration) |
| SC-005 block render + conditional asset | 4 |
| SC-006 zero side effects on failure | 1, 3 |
| SC-007 token-only / i18n / RTL | 4 |
| SC-008 best-effort dispatch | 2 |
