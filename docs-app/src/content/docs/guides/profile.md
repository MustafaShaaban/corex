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

The forms are progressively enhanced: with JavaScript they post to the REST API and show
inline results; without it they still submit.

## REST API

All routes live under `corex/v1/account/`:

| Route | Auth | Purpose |
| --- | --- | --- |
| `POST login` | public | Sign in (delegates to `wp_signon`). |
| `POST register` | public | Create an account (honeypot + captcha gated). |
| `POST reset-request` | public | Email a reset link (generic response — no user enumeration). |
| `POST reset` | public | Set a new password from a valid reset key. |
| `POST profile` | signed-in | Update the current user's profile. |
| `GET sessions` | signed-in | List the current user's active sessions. |
| `POST sessions/revoke-others` | signed-in | End every other session. |
| `POST sessions/revoke-all` | signed-in | End every session. |
| `GET notifications` | signed-in | The current user's recent account activity. |

Authenticated routes require a signed-in user and the standard REST nonce
(`X-WP-Nonce`); the service also confirms a user only ever edits their own profile or
sessions.

## Security notes

- Login inherits WordPress's own authentication filters and any login hardening from the
  Security Center — recovery keeps working even when the login route is customized.
- Password-reset responses are deliberately generic, so the endpoint cannot be used to
  discover which emails have accounts.
- Every account operation returns a typed result; passwords, reset keys, and session
  tokens never appear in a response.
