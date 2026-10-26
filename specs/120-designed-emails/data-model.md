# Data model: Emails that send what was designed

No table changes. Records below are Email Studio's private post records, options, or values in memory.

## EmailContent (value, corex-email): changed

| Field | Type | Notes |
|---|---|---|
| subject | string | plain text |
| bodyHtml | string | the message, arranged |
| recipient | string | |
| kind | string | new, last but one; `EmailKind::*`, default `reply` so every existing caller keeps its meaning |
| links | list of {label, url} | new, last; drawn in the footer; `http(s)` only |

## EmailKind (constants, corex-email): new

`reply`, `notification`, `confirmation`, `newsletter`, `account`, `message`. Each but `message` has a reason line.

## Rendered Studio email (array returned by `EmailTemplateService::render`): changed

`subject`, `html` (framed), `plain` (sent as the text/plain part), `missing` (list of variable keys with no value).

## RenderedEmail (code template): changed

`subject`, `body`, and new `plainText`.

## MailRequest (corex-core): changed

New last argument `?string $plainText = null`; empty or whitespace-only becomes null. Carried in the queue payload as
`plainText`.

## Email attempt (Studio record, type `email_attempt`): changed

Payload gains `missing_variables`: list of keys. Absent on older rows (read as empty).

## Flow email binding (inside a flow version's `email_routes`): changed

Gains `accept_missing` (bool): the person confirmed sending without variables the flow does not supply.

## Default template definition (code, `DefaultEmailTemplates`)

slug, name, kind, layout slug, subject, HTML, plain-text mode. Shipped defaults: `contact-confirmation`,
`admin-notification`, `double-opt-in`, `newsletter-welcome`; from corex-profile `password-reset`, `account-welcome`.

## Options: new

- `corex_email_default_templates_state`: `active` (first installed by this release) or `draft` (upgraded).
- `corex_email_default_templates`: the slugs already installed, so a default is created once.

## MailTemplateCatalog entry (corex-core contract): changed

`{ id, slug, name, variables }`: `variables` lists the keys the active version uses.

## Contracts (corex-core): new

- `DesignedEmails`: `available(slug)`, `send(slug, to, context, path): ?MailResult`.
- `MailTemplateUsage`: `emailsUsing(templateId): list<string>` (kinds of email, for warnings).
