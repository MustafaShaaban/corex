# Corex Profile

Front-office accounts for CoreX — login, registration, forgot/reset password, profile
editing, notifications, and active-session management — layered over WordPress
authentication and kept separate from `wp-admin`. The add-on owns the account logic; the
theme only presents it.

## What it provides

- **`corex/account` block** — an auth-aware panel. Signed out: login, registration
  (gated by the site's *Membership → Anyone can register* setting), and a forgot-password
  form. Signed in: profile editing, active sessions, and recent account activity. Placed
  by the **Account** page template (`page-account`). Each field's label is tied to it by
  an id made of the block, the form and the field's name
  (`corex-account-1-login-password`), so two forms that ask for the same thing, or two
  blocks on one page, do not share one. The number is WordPress's
  `wp_unique_prefixed_id()`: it counts the account blocks rendered in the request.
  Each field also says what it is for with an `autocomplete` value (`username`,
  `current-password`, `new-password`, `email`, `nickname`, `given-name`, `family-name`),
  which a browser and a password manager read. Registration's optional username has
  none; the profile guide says why.
- **`corex/v1/account/*` REST routes** — `login`, `register`, `reset-request`, `reset`
  (public auth endpoints), and `profile`, `sessions`, `sessions/revoke-others`,
  `sessions/revoke-all`, `notifications` (require a signed-in user + REST nonce).
- **`account.js`** — posts each form to its REST route and writes the answer in that
  form's own message line, under its button (`.corex-account__status`, a polite status),
  bringing the line into the window when it is outside it. A request that does not arrive,
  or is answered with something that is not JSON, or is refused with no message, is said
  in that line too, in one sentence the server hands the script
  (`corexAccount.i18n.noAnswer`).
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
| Reset | `Reset\ResetEmailLink`, `Reset\ResetLanding`, `Reset\ResetTicket`, `Reset\AccountPageMark`, `Reset\ResetLink` | A forgotten password is reset on the page the block is on: the email's link, the visitor's arrival, where the key is kept, and how the page is named. |
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

## A long display name

WordPress holds a display name in 250 characters, and it holds the name as it cleans it, not
as it was typed: an `&` is kept as `&amp;`, five characters, and a `<` that opens no tag as
`&lt;`. It does not refuse a longer one. `wp_update_user()` hands back the account's id,
the account keeps the name it had, and nothing else of the account's row is saved either
(a website address sent with it was not, as measured), while a last name sent with it is.

So the Profile form refuses it, with the code `display_name_too_long` and nothing saved.
`WordPressAuthGateway::displayNameAsKept()` gives the name as WordPress would hold it
(`sanitize_text_field()`, then the `pre_user_display_name` filters), and
`AccountService::MAX_DISPLAY_NAME_LENGTH` is the 250 it is counted against, in characters.
A name over 250 as typed is told the number; one within it as typed and over it as kept is
told that a character such as `&` is kept as several.

A name of up to 250 characters is saved. The record of the change on the activity stream
holds a name in 191 characters (`Corex\Activity\ActivityEvent::LONGEST_TEXT`), and
`ActivityService::record()` cuts a longer one to 190 and an ellipsis; the account keeps the
whole name. Covered by `tests/Integration/Profile/ProfileDisplayNameLengthTest.php`.

## A forgotten password

The reset begins and ends on the page the account block is on.

1. **The form names its page.** The forgot-password form carries a hidden field `page`: the
   id of the page the block is rendered on and a signature of it made with the site's salt
   (`Reset\AccountPageMark`). The block signs the page because the page cannot be asked: the
   Account template places the block, and the page's own content is empty.
2. **The email is WordPress's, with one address changed.** `reset-request` still calls
   `retrieve_password()`. While it does, `Reset\ResetEmailLink` filters
   `retrieve_password_message` and replaces the address that carries the key with the page's:
   `…/account/?corex_reset_login=…&corex_reset_key=…`. It runs only when the request named a
   published page with a mark this site made. Without one (an older cached page, a client of
   the route that sends no `page`, the block in a template that is no single page) the
   email is as WordPress writes it, and so is the email of a reset asked at `wp-login.php`.
3. **The key leaves the address.** `Reset\ResetLanding`, on `template_redirect`, puts the
   login and the key in a session cookie scripts cannot read (`corex-resetpass-…`,
   `Reset\ResetTicket`) and redirects to the same page with `?corex_reset=1`. This is what
   `wp-login.php` does with its own key. Both answers are sent with `nocache_headers()`.
4. **The block shows the new-password form, and no other.** WordPress checks the key
   (`check_password_reset_key()`). The form posts to `reset`, which checks it again and
   saves the password with `reset_password()`. A key WordPress does not take is answered
   with a message that says so above the usual forms, where a new link can be asked for.
5. **The page says what happened, once.** `reset` marks the cookie as used. `account.js`
   loads the page again, as it does after a sign-in, and the block reads "Your password
   has been updated. You can now sign in." above its forms. The cookie is then removed.
   This note and the one for a refused key are a `.corex-account__status` that is the
   block's first child, printed on those two page loads only: nobody pressed a button for
   them to be written under. `account.js` empties it with the other message lines when it
   writes the next answer.

Covered by `tests/Integration/Profile/ResetOnTheAccountPageTest.php`,
`tests/Unit/Profile/ResetEmailLinkTest.php`, `tests/Unit/Profile/AccountRendererResetTest.php`
and, in a browser, `tests/e2e/account-reset.spec.js`.

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
- A reset asked for at the block is finished at the block: its email links to the account
  page, not to `wp-login.php`, so it does not carry the address login protection gives the
  login. A signed-in member still reads that address in the **Sign out** link, which is
  WordPress's own, and the new member's email still carries WordPress's link.

## Testing

- Unit: `tests/Unit/Profile/` — `AccountServiceTest`, `SessionListTest`,
  `NotificationListTest`, `AccountRendererTest`.
- Script: `tests/corex-account.test.js` (Jest) — where an answer is written, that one is on
  the page at a time, how the line is brought into view, and what is said when a request
  gets no answer the script can read.
- Browser: `tests/e2e/product-surfaces.spec.js` — the message is inside the window, under
  its button and as wide as the fields, on a desktop and a phone, in both directions; a
  sign-in whose request is aborted, or answered with an error page, says so in its form;
  with scripting off the note is above the forms and inside the window.
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
  `tests/Integration/Profile/ProfileDisplayNameLengthTest.php` — through the Profile route:
  a display name of up to 250 characters is saved and its change recorded, a longer one is
  refused with nothing saved, and an account given a long name on a WordPress screen keeps
  working.
  `tests/Integration/Profile/ResetOnTheAccountPageTest.php` — where a reset email links to,
  and what the account page makes of the key.
- Browser: `tests/e2e/account-reset.spec.js` — a forgotten password is reset on the account
  page, from the form to the sign-in with the new password.
