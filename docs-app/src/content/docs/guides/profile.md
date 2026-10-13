---
title: Front-office accounts (Corex Profile)
description: Login, registration, password recovery, profile editing, notifications, and active-session management for site visitors — layered over WordPress authentication and kept separate from wp-admin.
---

**Corex Profile** is the optional add-on that gives site visitors a real account area —
without exposing `wp-admin`. It layers login, registration, forgot/reset password,
profile editing, notifications, and active-session management over WordPress's own
authentication, so it never reimplements password hashing or session handling.

## Enable it

1. Activate **Corex Profile** (Corex → Add-ons, or `wp plugin activate corex-profile`).
2. Create a page and assign the **Account** template (`page-account`), or drop the
   **Account** block (`corex/account`) into any page.
3. To allow sign-ups, enable **Settings → General → Membership → Anyone can register** in
   WordPress. When it is off, the account block hides the registration form and says so.

## What the account block shows

The `corex/account` block is auth-aware:

- **Signed out** — a sign-in form, a registration form (when registration is open), and a
  forgot-password form.
- **Signed in** — profile editing (display name, first/last name, email), a list of
  **active sessions** with "sign out other sessions" / "sign out everywhere", and a feed
  of the account's own recent activity.

With JavaScript the forms post to the REST API. What the server answers is written in a
message line under the button that was pressed: each form has its own, and so do the two
session buttons. If that line would be outside the window, the page moves by the least
that shows it, at once for a visitor whose system asks for reduced motion. The line is a
polite status (`role="status"`), so a screen reader announces the answer; focus stays on
the button. One answer is shown at a time: sending a form clears the others.

Without JavaScript a form is sent to the page's own address, which does not answer it.

### Styling the message line

The line is `<p class="corex-account__status" role="status">`, inside each
`.corex-account__form` and inside `.corex-account__sessions`. It carries
`data-state="error"` or `data-state="ok"` while it holds an answer and has no box while
it is empty. It takes its colours from the theme's `error`, `success` and `inverse`
colour presets.

## Usernames

The registration form's **Username** field is optional.

- A username WordPress would store as a different one is refused, with a message that
  names what a username can hold: letters without accents, numbers, spaces and the
  characters `_ . - @`. WordPress does not refuse such a username, it removes what it does
  not accept and keeps the rest, and the person could then not sign in with the name they
  chose. Spaces before and after it are taken off and are not a reason to refuse it.
- With no username, the login is made from the email address. WordPress removes from it
  the characters a login cannot hold (the `+` of `name+shop@example.com`, for one), and the
  person signs in with the address.
- A login has 60 characters at most. With no username, an address of 61 characters or more
  gets a login made from the part before the @, and the person still signs in with the
  address. When another account has that login, the new one ends in `-2`, or the next
  number that is free. The account block does not show the person that login, and they do
  not need it; an administrator reads it on the Users screen.
- When no login can be made from a long address (its first part holds nothing a login can,
  or twenty accounts already share it), the form asks for a username.

## Email addresses

An account's address has 100 characters at most. A longer one is refused at registration
and on the **Profile** form, with a message that says so. WordPress itself reports no
error for such an address and stores nothing.

The rule is WordPress's own `validate_username()`, so a plugin that widens or narrows what a
username may hold through the `sanitize_user` or `validate_username` filters is followed.

## Passwords

A password is handed to WordPress the way WordPress's own screens hand it over, so one
chosen on the account block signs in at `wp-login.php` too, and one chosen on a WordPress
screen signs in on the account block. This matters for a password with `'`, `"` or `\` in
it, which WordPress keeps with the slashes of the request it arrived in.

Up to and including v0.45.2 the add-on kept such a password as it was typed. An account
from then still signs in on the account block; the first time it does, its password is
kept again WordPress's way, which signs that member out of their other browsers once.

## REST API

All routes live under `corex/v1/account/`:

| Route | Auth | Purpose |
| --- | --- | --- |
| `POST login` | public | Sign in (delegates to `wp_signon`). |
| `POST register` | public | Create an account (honeypot + captcha gated). Answers `422` with the code `invalid_username` for a username WordPress would alter, `email_too_long` for an address over 100 characters, and `username_required` when no username was typed and no login can be made from the address. |
| `POST reset-request` | public | Email a reset link (generic response — no user enumeration). |
| `POST reset` | public | Set a new password from a valid reset key. |
| `POST profile` | signed-in | Update the current user's profile. Answers `422` with the code `email_too_long` for an address over 100 characters. |
| `GET sessions` | signed-in | List the current user's active sessions. |
| `POST sessions/revoke-others` | signed-in | End every other session. |
| `POST sessions/revoke-all` | signed-in | End every session. |
| `GET notifications` | signed-in | The current user's recent account activity. |

Authenticated routes require a signed-in user and the standard REST nonce
(`X-WP-Nonce`); the service also confirms a user only ever edits their own profile or
sessions.

## Security notes

- Login inherits WordPress's own authentication filters and any login hardening from the
  Security Center — recovery keeps working even when the login route is customized. A
  wrong password is one failed sign-in for the limiter, whatever characters it holds.
- Password-reset responses are deliberately generic, so the endpoint cannot be used to
  discover which emails have accounts.
- Every account operation returns a typed result; passwords, reset keys, and session
  tokens never appear in a response.

## The name a new account is shown by

A display name is what WordPress signs a comment or a post with, for every visitor.

- With a username, the account is shown by it.
- With no username, the account's login is its email address, and it is shown by the part of
  the address before the @: `dalia.hassan@example.com` is shown as `dalia.hassan`, and its
  author page is at `author/dalia-hassan`. The address itself is not shown. An
  email address typed into the Username field is treated the same way.
- An address too long to be the login is shown the same way, by the part before the @.
- The person signs in with the address either way, and changes the display name on the
  account block's **Profile** form.

An account registered before this rule (CoreX 0.45.2 and earlier) keeps the name it has,
which is the whole address when no username was typed. Changing its display name does not
change the comments it has already written: WordPress stores the name with each comment.
