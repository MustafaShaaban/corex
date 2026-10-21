# Quickstart: checking spec 114

Prerequisites: a scratch WordPress for this worktree (port 8914, database `corex_w114`), CoreX plugins active, mail
captured. Never run these checks on corex.local, which sends real mail.

## Automated

```bash
php -d memory_limit=512M vendor/bin/pest --testsuite=Unit
php -d memory_limit=512M vendor/bin/pest --testsuite=Integration
npx jest
COREX_BASE_URL=http://127.0.0.1:8914 npx playwright test tests/e2e/truthful-screens.spec.js
```

## By hand

1. **Overview** on a fresh install: Brand "Not set", Insights "Keyless (shared quota)", Captcha "Not configured", Spam
   protection "Honeypot only", Login protection "Off", a separate "Hardening checks" row.
2. **Security Center** with protection off: "Default login endpoints: Reachable"; `curl -s http://127.0.0.1:8914/wp-login.php`
   shows the login form. Turn protection and hiding on: "Hidden", and the same request returns no login form.
3. **Insights** on the local site: Run performance gives "Could not run", "PageSpeed cannot reach a local site", Retry,
   no grade.
4. **Security events**: change the mode, open a CoreX screen as an Editor; the card lists both, with "Recorded since".
5. **Captcha**: choose reCAPTCHA and save both keys; "Test verification" is under the secret field.
6. **Add-ons**: rename an add-on's main file, press Enable: an error with WordPress's reason, the card stays off.
7. **Login**: `wp option patch insert corex_brand login_sso_enabled 1` is not needed; set the stored setting on and open
   `/wp-login.php`: no SSO button, no "or".
8. **Blog Pro**: one line says reading data is not collected yet; no Sharing card.
9. **Settings**: with Corex Media inactive and WebP stored on, save Brand; `wp option get` of the media settings is
   unchanged.
