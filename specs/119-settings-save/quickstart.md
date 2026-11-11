# Quickstart: checking Settings that save what you changed

Run on a scratch site, never on corex.local (these checks save settings and start jobs). The worktree recipe:
`powershell -File scripts/setup-wordpress.ps1 -SiteUrl http://127.0.0.1:8919 -DbName corex_w119`, then
`php -S 127.0.0.1:8919 -t wp`.

## Automated

```bash
vendor/bin/pest -d memory_limit=512M tests/Unit/Config tests/Unit/Media
vendor/bin/pest -d memory_limit=512M --configuration=phpunit-integration.xml.dist tests/Integration/Config tests/Integration/Media
npx jest plugins/corex-config/assets/__tests__ addons/corex-media/assets/__tests__ addons/corex-captcha/assets/__tests__
COREX_BASE_URL=http://127.0.0.1:8919 npx playwright test -c tests/e2e/playwright.config.js settings-pages truthful-screens setup-settings-insights
```

## By hand

1. **Pages and the bar** — open Settings: the rail shows the seven pages under Settings. Change the tagline: the bar
   reads "Unsaved changes"; Discard; change it again and click Media: the browser asks. Save: the address is the page's,
   and a reload does not resend. Type "sales@" as the contact email and save: the message names it and the bar stays
   "Unsaved changes".
2. **Locks** — add `BRAND_COMPANY_NAME=Acme` to the project root `.env`; General and appearance shows Acme, read-only,
   "Set in the site's .env file". Remove the line.
3. **Providers** — Captcha: reCAPTCHA with only a site key reads "The secret key is missing, so forms are protected by
   the honeypot only". Insights: a token without an account ID names the account ID; "Test connection" with a wrong key
   reads "Last test failed on <today>: Google refused the key".
4. **Remove** — save a Turnstile secret, press Remove, confirm, save: the field reads "No value saved", the Add-ons
   captcha card says a key is missing, and the activity log has "settings.secret_removed" without the value.
5. **Add-ons off** — turn Corex Guides off: Settings > Guides is read-only with "Open Add-ons" landing on its card.
6. **Switches** — every on/off setting reads On or Off; Space toggles it.
7. **Conversion** — upload JPEGs with conversion off, turn it on, start "Convert existing library", reload, cancel a
   second run.
8. **Right to left** — `wp language core install ar --path=wp && wp site switch-language ar --path=wp`; read each page,
   then switch back to `en_US`.
