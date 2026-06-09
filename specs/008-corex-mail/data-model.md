# Phase 1 Data Model: Corex Mail (MVP)

Pure value objects + code-registered templates + one persisted entity (`corex_email_log`).

## Entity map

```text
Mail::to(addr)->template('contact-notification')->with(['form'=>…,'event'=>…])->send()
  MessageBuilder → (template? TemplateRenderer(MailContext) : raw body) → EmailMessage{to,cc,bcc,replyTo,subject,body}
    MailService.send(EmailMessage):
      HeaderGuard.assert(subject, headers)         → reject on CR/LF/control
      RecipientResolver.validate(to/cc/bcc)        → drop+log invalid; none valid → reject
      MailDriver.send(EmailMessage)                → WpMailDriver (wp_mail)
      EmailLogRepository.record(status, message)   → corex_email_log
    → EmailResult{status, message, logId}
```

## 1. EmailMessage *(FR-001)* — immutable value object

- `to`, `cc`, `bcc` (lists of addresses), `replyTo` (?address), `subject`, `body` (rendered HTML or raw),
  `headers` (assoc). Built by `MessageBuilder`; never mutated after build. The only thing a driver delivers.

## 2. EmailTemplate *(FR-002, FR-003)* — code-registered

- Abstract base: `name(): string`, `subject(MailContext): string`, `body(MailContext): string`. A concrete
  template returns straight-line text with `{{ path }}` placeholders. Registered in `TemplateRegistry` by
  name; an unknown name resolves to null (non-fatal, logged).

## 3. MailContext *(FR-003)* — typed, whitelisted, pure

- Wraps the allowed data: `event.*`, named model(s) (`user.*`, `form.*`, …), `site.*` (name, url, admin
  email). `get('user.name')` walks dotted paths over the whitelisted roots only; an out-of-whitelist or
  absent path returns empty string. No object methods are invoked, no arbitrary traversal — values are read
  from a pre-assembled array, so a template can never reach code or unintended data.

## 4. TemplateRenderer *(FR-003, FR-004, FR-005)* — pure

- Replaces each `{{ path }}` with `MailContext::get(path)`, **sanitized then escaped for HTML output**, and
  wraps the merged body in `Layout`. Unknown placeholders render empty. Produces safe HTML; a merge value
  containing markup is escaped, never live.

## 5. Layout *(FR-005)* — pure (brand at runtime)

- A shared HTML shell (header/footer, logo, brand color) whose values come from the resolved `brand.json`
  (injected, same source as the site). Logical CSS (RTL-correct), inline-styled for email-client
  compatibility but driven by the brand values, not hardcoded design constants.

## 6. HeaderGuard *(FR-006)* — pure

- `assert()` throws/returns a rejection if the subject, from, reply-to, or any recipient display name
  contains `\r`, `\n`, or a control character (header-injection defense). Stateless.

## 7. RecipientResolver *(FR-007, FR-008)* — pure (role via injected directory)

- Resolves a recipient spec — `fixed(addr)`, `role(roleName)` (via the injected `UserDirectory`), or
  `dynamic(contextPath)` (via `MailContext`) — into a flat address list, then **validates** each (drops +
  flags invalid). Reply-to resolved the same way. Returns the validated set; empty set ⇒ the service rejects.

## 8. EmailLog (`corex_email_log`) *(FR-010)* — persisted

- A non-public CPT. One row per delivery attempt: title `{template|subject} — {timestamp}`; meta
  `corex_mail_status` (`sent|failed|rejected`), `corex_mail_to` (recipients), `corex_mail_template`,
  `corex_mail_subject`. Persisted by `EmailLogRepository` (a `PostRepository`); queryable by status.

## 9. The corex-core seam: `Corex\Mail\Mailer` + `MailRequest` *(FR-012)*

- `Mailer` interface (in corex-core): `send(MailRequest): void` (best-effort). `MailRequest` is a primitive
  value object — `to` (list<string>), `templateName` (?string), `context` (array), `subject`/`body`
  (?string for raw), `replyTo` (?string) — using only scalars/arrays so corex-core carries no Corex Mail
  types. Corex Mail binds the implementation; Forms checks `container->has(Mailer::class)`.

## Error paths

| Trigger | Handling | FR |
|---|---|---|
| CR/LF in subject/header | rejected before send; logged `rejected` | FR-006 |
| invalid recipient address | dropped + logged; valid co-recipients still sent | FR-007 |
| no valid recipient | not sent; logged `failed` (no recipient) | FR-007 |
| driver throws / returns false | caught; logged `failed`; request not aborted | FR-011 |
| unknown template name | resolved null; send rejected/logged, non-fatal | FR-002, FR-011 |
| merge path outside whitelist / absent | renders empty; no code run, no leak | FR-003 |
| Corex Mail inactive (Forms) | `container->has(Mailer)` false → `wp_mail` fallback | FR-012 |
