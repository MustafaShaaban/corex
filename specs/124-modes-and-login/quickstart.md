# Quickstart: checking spec 124 by hand

On a scratch site (never a client copy), signed in as an administrator.

1. **Noindex**: `wp corex mode set development`; `curl -sI http://site/ | grep -i x-robots-tag` shows `noindex`;
   `wp corex mode set production --phrase=PRODUCTION --user=admin` (or the override phrase if blockers exist): gone.
2. **Forms paused**: `wp corex mode set forms-paused --acknowledge`; post to `wp-json/corex/v1/forms/contact` signed out:
   503 with the paused message; a flow page shows the paused panel; back to Production reopens at once.
3. **Override**: with debug display on, choose Production: the panel names the blocker and asks for GO LIVE ANYWAY;
   PRODUCTION is refused; the override goes live and the history says "Went live with 1 blocker overridden".
4. **Messages for visitors**: write a message and a back-by time an hour ahead, choose Maintenance, open the site in
   a private window: the message, "Back by", `Retry-After` about 3600.
5. **Login switches**: turn on only "Hide the admin": `/wp-admin/` is not found signed out, `/wp-login.php` signs in.
6. **Lockout release**: fail five times as an existing user; Lockouts names the account; Release; sign in at once.
7. **Hardening by mode**: in Development, Hardening lists HTTPS, debug output and search engines under "Before going
   live"; the Overview's count leaves them out.

Automated: `vendor/bin/pest -d memory_limit=512M tests/Unit`, the integration suite with
`--configuration=phpunit-integration.xml.dist`, `npx jest`, and
`COREX_BASE_URL=http://127.0.0.1:8924 npx playwright test tests/e2e/operations-modes.spec.js tests/e2e/security-access.spec.js`.
