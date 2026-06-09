# Phase 0 Research: Corex Mail (MVP)

Decisions resolved before design. Each is the lowest-risk option consistent with the delivered framework.

## R1 — Delivery: `wp_mail` default driver, not a managed SMTP/provider client

**Decision**: the default `MailDriver` delegates to `wp_mail`, honoring whatever SMTP/MTA the site already
has. **Rejected**: bundling an SMTP client + storing provider credentials in the MVP.
**Why**: storing credentials at rest safely needs the (not-yet-built) `Cryptor`; `wp_mail` lets the engine
ship securely now, and the `MailDriver` interface keeps provider drivers a later, additive change.

## R2 — Templates: code-registered PHP classes, flat `{{ path }}` merge

**Decision**: templates are PHP classes (`name`/`subject`/`body`) with flat `{{ path }}` placeholders.
**Rejected**: a full templating language (conditionals/loops) and DB/visual authoring in the MVP.
**Why**: code-registered templates are version-controlled and deployable; a flat, whitelisted merge is the
smallest renderer that is provably injection-safe. Control structures and authoring UIs are deferred.

## R3 — Merge safety: whitelisted context + escape-on-output, never eval

**Decision**: `MailContext` exposes a pre-assembled, whitelisted array (`event.*`/`site.*`/named models);
`get(path)` reads dotted keys only, returning empty for anything else; every value is sanitized then escaped.
**Rejected**: resolving arbitrary object graphs or evaluating expressions in templates.
**Why**: closes template-injection/XSS by construction — a template can never reach code or unintended data.

## R4 — Audit log store: `corex_email_log` CPT via the data layer

**Decision**: a non-public CPT through a `PostRepository`, mirroring the spec-007 submission store.
**Rejected**: a custom table (not yet a framework capability) and an options blob (unqueryable).
**Why**: reuses the only persistence layer that exists today; swappable for a custom table later without
touching the engine. Retention/pruning is deferred.

## R5 — Forms integration: a neutral `Corex\Mail\Mailer` seam in corex-core

**Decision**: corex-core defines a primitive `Mailer` interface + `MailRequest` (scalars/arrays only); Corex
Mail binds the implementation; Forms checks `container->has(Mailer::class)` and falls back to `wp_mail`.
**Rejected**: Forms referencing `Corex\Email\*` directly (class_exists), which couples to a concrete add-on
and is unreliable in a monorepo where all classes autoload regardless of activation.
**Why**: a container-bound seam is the true activation signal and keeps both add-ons free of a hard
dependency (Principle IX), exactly like the spec-007 event seam pattern.

## R6 — Best-effort, synchronous send (no queue in MVP)

**Decision**: `send()` renders/validates/delivers in-process and returns an `EmailResult`; it never throws —
failures are caught and logged. **Rejected**: an Action Scheduler queue in the MVP.
**Why**: keeps the MVP small and non-fatal; the queue (retries, rate limiting) is a clean later addition
behind the same `MailService` API, since callers already treat send as fire-and-forget.
