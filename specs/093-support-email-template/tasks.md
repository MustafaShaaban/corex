# Tasks — Spec 093

- [x] T001 `SupportMessage` — one set of parts, both renderings, truncation applied once
- [x] T002 `SupportEmailPalette` — the admin light tokens, with the reason they are literals
- [x] T003 `SupportRequestTemplate` extending `EmailTemplate`
- [x] T004 Subject built from placeholders — the renderer ignores the request's subject
- [x] T005 `SupportMailer` chooses template vs plain text by transport
- [x] T006 Register the template in `boot()`, guarded by `class_exists()`
- [x] T007 Rewire `SupportRequestController` to build the value object
- [x] T008 Tests: template, transport choice, and the malformed-attribute regression
- [x] T009 Update the existing mailer tests to the new signature
- [x] T010 Real send on the running install — HTML, brass, correct subject, Reply-To
- [x] T011 `DECISIONS.md` on the palette literals and the two-accent result

## Two mistakes this spec made, both pinned by tests

- **The subject was a literal.** `TemplateRenderer` uses the *template's* `subject()` whenever a
  template is named — the request's own subject is ignored. A literal silently threw away the site
  name and the category, and the email still arrived, so nothing looked wrong.
- **The font stack was double-quoted inside `style="…"`**, which closes the attribute. Not a styling
  nuance — a broken tag, in 21 attributes.

Both were found by looking at rendered output rather than at the source, which is the only way either
would have surfaced.
