# Corex Profile

Front-office accounts for CoreX — login, registration, forgot/reset password, profile
editing, notifications, and active-session management — layered over WordPress
authentication and kept separate from `wp-admin`. The add-on owns the account logic; the
theme only presents it.

## What it provides

- **`corex/account` block** — an auth-aware panel. Signed out: login, registration
  (gated by the site's *Membership → Anyone can register* setting), and a forgot-password
  form. Signed in: profile editing, active sessions, and recent account activity. Placed
  by the **Account** page template (`page-account`).
- **`corex/v1/account/*` REST routes** — `login`, `register`, `reset-request`, `reset`
  (public auth endpoints), and `profile`, `sessions`, `sessions/revoke-others`,
  `sessions/revoke-all`, `notifications` (require a signed-in user + REST nonce).
- **`account.js`** — posts each form to its REST route and writes the answer in that
  form's own message line, under its button (`.corex-account__status`, a polite status),
  bringing the line into the window when it is outside it. A request that does not arrive,
  or is answered with something that is not JSON, or is refused with no message, is said
  in that line too, in one sentence the server hands the script
  (`corexAccount.i18n.noAnswer`).
- **The two lists of the signed-in view**, active sessions and recent activity, are loaded
  by `account.js`. Under each heading there is the list, or a sentence in its place
  (`<p class="corex-account__list-note">`, which the script adds after the `<ul>`):
  `data-state="empty"` when the server answered with nothing to list, `data-state="error"`
  when the list could not be loaded. The four sentences are handed to the script as
  `corexAccount.i18n.noSessions`, `sessionsNotLoaded`, `noActivity` and
  `activityNotLoaded`. After **Sign out everywhere** the page is loaded again.
- **With scripting off** the block shows a note above its forms
  (`<noscript><p class="corex-account__noscript">`) saying that they need JavaScript. A
  form sent anyway goes to the page's own address, which does not answer it: the forms do
  not work without scripts. The note names no other place to sign in, because WordPress's
  own address is the hidden one on a site with login protection on.

## Architecture

| Layer | Class | Responsibility |
| --- | --- | --- |
| Orchestration | `Account\AccountService` | Validation + policy (registration gate, password rules, enumeration-safe reset). Pure; unit-tested with a fake gateway. |
| Boundary | `Account\WordPressAuthGateway` | Every WordPress auth call (`wp_signon`, `wp_insert_user`, `retrieve_password`, the reset flow, `wp_update_user`). Never reimplements hashing. |
| Naming | `Account\PublicName` | The name a new account goes by: the username that was typed, or the part of the address before the @ when none was, or when an address was typed as the username. Pure. |
| Sessions | `Session\SessionService` + `Session\SessionList` | Lists a user's sessions (pure formatter, no token leak) and ends other/all sessions via core helpers. |
| Notifications | `Notification\NotificationService` | The signed-in user's own activity, projected from the shared core activity stream. |
| Presentation | `Block\AccountRenderer` | Pure, fully escaped HTML for the block. |

Every account operation returns a typed `Account\AccountResult` — no caller infers success
from a void call. Passwords, reset keys, and session tokens never appear in a result.

## The name of a new account

The registration form's Username field is optional, and without one the login is the email
address. WordPress would make the display name, the nickname and the address of the author
page from that login, and a comment would be signed with the address for every visitor to
read. `WordPressAuthGateway::createUser()` hands WordPress those three names itself, made
from the part of the address before the @: `dalia.hassan@example.com` is shown as
`dalia.hassan`, with the author address `dalia-hassan`. An email address typed as the
username is treated the same way; any other username is the name, as WordPress has it. The
person changes their display name on the block's Profile form. Covered by
`tests/Unit/Profile/PublicNameTest.php` and
`tests/Integration/Profile/RegistrationPublicNameTest.php`.

## A long email address

WordPress holds a login in 60 characters, an address in 100 and the address of an author
page in 50.

- **61 to 100 characters, no username.** The address cannot be the login, so
  `WordPressAuthGateway::loginForAddress()` makes one from the part before the @: what
  `sanitize_user()` keeps of it, cut to 60 characters. When an account has that login the
  next one is tried with `-2` after it, then `-3`, up to `-20`. The person signs in with
  the address, as everybody without a username does, and the account is named from the
  whole first part of the address, not from the login.
- **No login can be made** (nothing of the first part can be in a login, or all twenty are
  taken): the answer is `422` with the code `username_required`, and the person types a
  username.
- **Over 100 characters.** Refused with the code `email_too_long`, at registration and on
  the Profile form. WordPress does not refuse it: `wp_insert_user()` hands back `0` and
  stores nothing, and `wp_update_user()` hands back the account's id and changes nothing.
  `createUser()` also answers `register_failed` for any `0` WordPress hands back.

An address of 60 characters or fewer is the login, as before. Covered by
`tests/Integration/Profile/RegistrationLongAddressTest.php`.

## Security

- Registration is honeypot- and captcha-gated (when Corex Captcha is active) and respects
  the site registration setting.
- A username WordPress would store as a different one (`CORP\salma` as `CORPsalma`) is
  refused with the code `invalid_username`, by WordPress's own `validate_username()`. With
  no username the login is the email address, or one made from it when the address is over
  60 characters, and the person signs in with the address.
- Login delegates to `wp_signon`, inheriting core's auth filters and any login hardening.
- A password is handed to WordPress with `wp_slash()`, as WordPress's own screens hand it
  over, at registration, reset and sign-in. An account the add-on hashed as typed before
  that still signs in: `wp_check_password()` finds which form it holds, one `wp_signon()`
  is made with that form, and when it has passed the password is hashed again WordPress's
  way (`wp_set_password()`), the account's sessions are ended and the browser is given its
  cookie again.
- Password reset responses are generic, so the endpoint cannot enumerate accounts.
- Authenticated routes require a signed-in user and the REST nonce; the service also
  confirms a user only ever edits their own profile or sessions.
- Recovery keeps working even when login is hardened (front-office is separate from
  `wp-admin`).

## Testing

- Unit: `tests/Unit/Profile/` — `AccountServiceTest`, `SessionListTest`,
  `NotificationListTest`, `AccountRendererTest`.
- Script: `tests/corex-account.test.js` (Jest) — where an answer is written, that one is on
  the page at a time, how the line is brought into view, and what is said when a request
  gets no answer the script can read; and what each of the two lists shows: its rows, that
  there is nothing to list, or that it could not be loaded.
- Browser: `tests/e2e/product-surfaces.spec.js` — the message is inside the window, under
  its button and as wide as the fields, on a desktop and a phone, in both directions; a
  sign-in whose request is aborted, or answered with an error page, says so in its form;
  with scripting off the note is above the forms and inside the window; a signed-in
  person's two lists say so when their requests are aborted, answered with an error page or
  refused, in a box a step under the heading and a step above the session buttons; **Sign
  out everywhere** loads the page again.
- Integration: `tests/Integration/Profile/ProfileLifecycleTest.php` — the real lifecycle
  on WordPress (register → duplicate guard → credential validity → profile edit → reset →
  sessions; plus the registration-disabled gate).
  `tests/Integration/Profile/RegistrationUsernameTest.php` — which usernames the register
  route refuses and which it keeps.
  `tests/Integration/Profile/PasswordSignsInEverywhereTest.php` — a password with a quote
  or a backslash signs in at the add-on and the way `wp-login.php` checks it. `tests/Integration/Profile/ProfileNamesKeepBackslashesTest.php`
  — a profile's names are saved as written.
  `tests/Integration/Profile/RegistrationLongAddressTest.php` — an address too long to be a
  login registers, and one too long for an account is refused.
