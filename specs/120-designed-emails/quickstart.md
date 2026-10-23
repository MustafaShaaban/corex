# Quickstart: checking spec 120

On a scratch site (never corex.local's real mailbox): corex-email, corex-forms, corex-config, corex-newsletter and
corex-profile active, operations mode Development so every email is captured into Email Studio → Delivery logs.

## Automated

```bash
vendor/bin/pest -d memory_limit=512M tests/Unit/Email tests/Unit/Mail
vendor/bin/pest -d memory_limit=512M --configuration=phpunit-integration.xml.dist tests/Integration/Email tests/Integration/Mail
npx jest plugins/corex-config/src/Email plugins/corex-config/src/Forms
COREX_BASE_URL=http://127.0.0.1:8920 npx playwright test tests/e2e/email-studio.spec.js tests/e2e/email-studio-preview.spec.js
```

## By hand

1. **Defaults (story 4).** On a fresh install open Email Studio → Templates: Contact confirmation, Admin notification,
   Double opt-in and Newsletter welcome, active. Create a flow with an `email` field; its Emails tab has the two bindings
   ticked. Submit it: the log has both, framed; the admin one names the form and links to the submission.
2. **Preview (story 1).** Open a template, Preview: the framed email with the site's name and "Jane Cooper"; change a
   sample, the preview follows; Mobile is 375px wide; Right to left flips it. Test send to `me@example.test`: "Captured:
   nothing left this site" and a link to the log row, whose HTML and plain text equal the preview.
3. **From and plain text (story 2).** Set From "Sales" `sales@site.test`, save, test-send: the capture's headers carry
   the sender; the Plain text tab says "Sent as the plain-text version" and equals the captured plain text.
4. **Frame (story 3).** Set a logo, accent and footer in Settings → Mail; send a reply from the inbox, submit the
   contact form, subscribe to the newsletter, test-send a template: one frame in the four captures.
5. **Variables (story 5).** Save `{{ nonsense.key }}`: refused, named. Save `{{ user.name }}` on a template a flow binds:
   saved with a warning; binding it in the flow's Emails tab asks for confirmation.
6. **Account (story 6).** With the account block on a page, ask a reset there: a framed reset email with a button, a
   plain link to that page, "24 hours", no login address. Register with a password: a framed welcome, no set-password
   link.
7. **Plain text on the seam (story 7).** `wp eval 'corex()->make(Corex\Mail\Mailer::class)->send(new
   Corex\Mail\MailRequest(to: ["a@example.test"], subject: "S", body: "<p>Hi</p>", plainText: "Hi"));'`: the capture
   shows "Hi" as its plain text.
