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

### Selecting a field

Select a field by its form and its name, not by its id:

```css
[data-corex-account-form="register"] input[name="password"] { /* … */ }
```

A field's id is there to tie its label to it. It holds the number of the block among
the account blocks WordPress rendered for the page (`corex-account-1-register-password`),
so that a page with two of them has no id twice, and that number is not promised to stay
the same. The forms are `login`, `register`, `reset-request`, `reset` and `profile`.

### What a field tells the browser

Each field that asks for something about the person has an `autocomplete` value, one of
the HTML specification's autofill field names. A browser and a password manager read it
to offer the saved password at sign-in and a new one at registration, and WCAG 2.2
(1.3.5, Identify Input Purpose) asks for it.

| Form | Field (`name`) | `autocomplete` |
|---|---|---|
| `login` | `login` | `username` |
| `login` | `password` | `current-password` |
| `register` | `email` | `email` |
| `register` | `username` | `username` |
| `register` | `password`, `password_confirm` | `new-password` |
| `reset-request` | `login` | `username` |
| `reset` | `password`, `password_confirm` | `new-password` |
| `profile` | `display_name` | `nickname` |
| `profile` | `first_name` | `given-name` |
| `profile` | `last_name` | `family-name` |
| `profile` | `email` | `email` |

Chromium writes the person's saved email address into a field marked `username` when
they pick a saved address for the form, so at registration it writes the address into
**Email** and into the optional **Username**. The account takes a username that is the
person's own address as no username (see [Usernames](#usernames)), and the account made
is the one they get with the field left empty. The display name is a `nickname` and not a
`name`: it is the name a member is shown by, and with `name` Chromium takes it for the
full name of a saved address and writes that over it when a fill is started there.
Chromium does not know `nickname`: it fills nothing into that field, and its developer
tools list the value as one it does not recognise.
WordPress's own profile screen marks its nickname field the same way.

## Usernames

The registration form's **Username** field is optional.

- A username WordPress would store as a different one is refused, with a message that
  names what a username can hold: letters without accents, numbers, spaces and the
  characters `_ . - @`. WordPress does not refuse such a username, it removes what it does
  not accept and keeps the rest, and the person could then not sign in with the name they
  chose. Spaces before and after it are taken off and are not a reason to refuse it.
- A username that is the person's own email address is taken as no username. A browser
  writes a saved address into the field, and an address is not always a username an
  account can have: `name+shop@example.com` has a `+`, and an address can be longer than
  a login. The two are compared without regard to capitals, after the spaces around the
  username are taken off. Any other address typed as the username is judged as a
  username.
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

## A forgotten password

A member who asks for a reset link on the account page gets WordPress's password-reset
email, with one difference: its link comes back to the page they asked from.

1. The link opens that page with a **Choose a new password** form and nothing else on the
   block.
2. Saving it loads the page again, which says "Your password has been updated. You can now
   sign in." above the sign-in form.
3. A link that was already used, or is older than WordPress keeps a reset key (a day,
   unless the site changes `password_reset_expiration`), opens the page with "This password
   reset link is invalid or has expired. Please ask for a new one." above the forms.

The member does not see `wp-login.php` at any step, and the email does not carry its
address. On a site whose login is moved by the Security Center, that is the moved address.

What to know when you build on it:

- The email is still sent by WordPress (`retrieve_password()`), so its wording and its
  sender are WordPress's, and a plugin that filters `retrieve_password_title`,
  `retrieve_password_message` or `retrieve_password_notification_email` still does.
- A reset asked for at `wp-login.php` itself is not changed.
- The link's address ends in `?corex_reset_login=…&corex_reset_key=…`. The page takes both
  out of the address at once, into a cookie of that browser's session, and continues at
  `?corex_reset=1`. A page cache has to pass a request with a query string to WordPress,
  as caches do by default; the answers are sent with no-cache headers.
- The email links to the account page only when the request names it. The block's own form
  does. A client of your own that calls `reset-request` sends the form's hidden `page`
  field with it, or the email keeps WordPress's link.

## REST API

All routes live under `corex/v1/account/`:

| Route | Auth | Purpose |
| --- | --- | --- |
| `POST login` | public | Sign in (delegates to `wp_signon`). |
| `POST register` | public | Create an account (honeypot + captcha gated). Answers `422` with the code `invalid_username` for a username WordPress would alter, `email_too_long` for an address over 100 characters, and `username_required` when no username was typed and no login can be made from the address. |
| `POST reset-request` | public | Email a reset link (generic response — no user enumeration). With `page`, the value of the forgot-password form's hidden field, the link is to that page. |
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
  Security Center. A wrong password is one failed sign-in for the limiter, whatever
  characters it holds.
- A password is reset on the account page, so the reset email does not name the login's
  address. A signed-in member can still read that address: the **Sign out** link is
  WordPress's own.
- Password-reset responses are deliberately generic, so the endpoint cannot be used to
  discover which emails have accounts.
- Every account operation returns a typed result; passwords, reset keys, and session
  tokens never appear in a response.

## The name a new account is shown by

A display name is what WordPress signs a comment or a post with, for every visitor.

- With a username, the account is shown by it.
- With no username, the account's login is its email address, and it is shown by the part of
  the address before the @: `dalia.hassan@example.com` is shown as `dalia.hassan`, and its
  author page is at `author/dalia-hassan`. The address itself is not shown. The
  person's own address in the Username field is no username, so it makes the same
  account. Another email address typed there is the login, and the account is shown by
  the part before its @.
- An address too long to be the login is shown the same way, by the part before the @.
- The person signs in with the address either way, and changes the display name on the
  account block's **Profile** form.

An account registered before this rule (CoreX 0.45.2 and earlier) keeps the name it has,
which is the whole address when no username was typed. Changing its display name does not
change the comments it has already written: WordPress stores the name with each comment.
