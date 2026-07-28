# Before: which admin refusals render WordPress's white box

**Captured**: 2026-07-28, against `main` at `8e61e0a`, on the running install `http://corex.local`.
**Signed in as**: `corex-requester` (subscriber) — a real session, not a simulated capability.

Eleven admin addresses, opened in a browser as somebody who genuinely lacks access. `corex-brand`
means the response contained `corex-standalone` or `corex-denied`, i.e. it is a CoreX document.

| status | WP white box | CoreX brand | surface |
|---|---|---|---|
| 403 | – | **yes** | `admin.php?page=corex-forms` |
| 404 | – | **yes** | `admin.php?page=corex-nonexistent` |
| 403 | **YES** | – | `edit.php?post_type=corex_job` — **CoreX's own Careers screen** |
| 403 | **YES** | – | `edit.php?post_type=corex_project` — **CoreX's own Portfolio screen** |
| 403 | see below | – | `users.php` |
| 403 | **YES** | – | `plugins.php` |
| 403 | see below | – | `options-general.php` |
| 403 | **YES** | – | `tools.php` |
| 403 | **YES** | – | `upload.php` |
| 403 | **YES** | – | `edit.php` |
| 403 | see below | – | `options.php?_wpnonce=deadbeef` |

**Two of the eleven are covered.** Both are `admin.php?page=corex-*`, which is the entire scope of
`AccessDeniedGate.php:39`. Nine are not, and two of those nine are screens CoreX itself registers.

## The finding that changes the test

The three rows marked "see below" do **not** contain the sentence spec 079's SC-005 names. They are
refusals all the same:

```
=== /wp-admin/users.php                        [403]
You need a higher level of permission.
Sorry, you are not allowed to list users.

=== /wp-admin/options.php?_wpnonce=deadbeef    [403]
You need a higher level of permission.
Sorry, you are not allowed to manage options for this site.

=== /wp-admin/edit.php?post_type=corex_job     [403]
Sorry, you are not allowed to access this page.
```

WordPress has **many** refusal sentences, not one, and every one of them is translated. So spec 079's
SC-005 — *"'Sorry, you are not allowed to access this page.' no longer appears"* — is a test that can
pass on a site where every refusal is still a white box: switch the site to Arabic, or open
`users.php` instead of `edit.php`, and the string is already absent today.

**The assertion has to be positive.** Not *"WordPress's sentence is absent"* but *"this response is a
CoreX document"*. That is the one form that cannot be satisfied by accident, and it is what
`tests/e2e/admin-errors.spec.js` asserts.

## A second correction

`options.php?_wpnonce=deadbeef` was intended to reproduce an expired-nonce refusal. It does not: the
capability check runs first and refuses for want of `manage_options`, so the nonce is never reached.
Covering the expired-link path needs an action the signed-in user is actually permitted to start,
with a stale nonce attached.

## Reproduction

A throwaway Playwright script signed in as the subscriber and walked the list, recording
`response.status()` and whether the markers appeared in `page.content()`. It was not kept: the
permanent version is `tests/e2e/admin-errors.spec.js`, which asserts rather than reports.
