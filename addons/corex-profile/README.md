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
- **`corex/v1/account/*` REST routes** — `login`, `register`, `reset-request`, `reset`
  (public auth endpoints), and `profile`, `sessions`, `sessions/revoke-others`,
  `sessions/revoke-all`, `notifications` (require a signed-in user + REST nonce).
- **`account.js`** — posts each form to its REST route and writes the answer in that
  form's own message line, under its button (`.corex-account__status`, a polite status),
  bringing the line into the window when it is outside it. Without JavaScript a form is
  sent to the page's own address, which does not answer it.

## Architecture

| Layer | Class | Responsibility |
| --- | --- | --- |
| Orchestration | `Account\AccountService` | Validation + policy (registration gate, password rules, enumeration-safe reset). Pure; unit-tested with a fake gateway. |
| Boundary | `Account\WordPressAuthGateway` | Every WordPress auth call (`wp_signon`, `wp_insert_user`, `retrieve_password`, the reset flow, `wp_update_user`). Never reimplements hashing. |
| Sessions | `Session\SessionService` + `Session\SessionList` | Lists a user's sessions (pure formatter, no token leak) and ends other/all sessions via core helpers. |
| Notifications | `Notification\NotificationService` | The signed-in user's own activity, projected from the shared core activity stream. |
| Presentation | `Block\AccountRenderer` | Pure, fully escaped HTML for the block. |

Every account operation returns a typed `Account\AccountResult` — no caller infers success
from a void call. Passwords, reset keys, and session tokens never appear in a result.

## Security

- Registration is honeypot- and captcha-gated (when Corex Captcha is active) and respects
  the site registration setting.
- A username WordPress would store as a different one (`CORP\salma` as `CORPsalma`) is
  refused with the code `invalid_username`, by WordPress's own `validate_username()`. With
  no username the login is made from the email address and the person signs in with the
  address.
- Login delegates to `wp_signon`, inheriting core's auth filters and any login hardening.
- Password reset responses are generic, so the endpoint cannot enumerate accounts.
- Authenticated routes require a signed-in user and the REST nonce; the service also
  confirms a user only ever edits their own profile or sessions.
- Recovery keeps working even when login is hardened (front-office is separate from
  `wp-admin`).

## Testing

- Unit: `tests/Unit/Profile/` — `AccountServiceTest`, `SessionListTest`,
  `NotificationListTest`, `AccountRendererTest`.
- Script: `tests/corex-account.test.js` (Jest) — where an answer is written, that one is on
  the page at a time, and how the line is brought into view.
- Browser: `tests/e2e/product-surfaces.spec.js` — the message is inside the window, under
  its button and as wide as the fields, on a desktop and a phone, in both directions.
- Integration: `tests/Integration/Profile/ProfileLifecycleTest.php` — the real lifecycle
  on WordPress (register → duplicate guard → credential validity → profile edit → reset →
  sessions; plus the registration-disabled gate).
  `tests/Integration/Profile/RegistrationUsernameTest.php` — which usernames the register
  route refuses and which it keeps. `tests/Integration/Profile/ProfileNamesKeepBackslashesTest.php`
  — a profile's names are saved as written.
