# Implementation Plan: Forms & Mail Correctness

**Branch**: `spec/080-forms-mail-correctness` | **Date**: 2026-07-28 | **Spec**: [spec.md](spec.md)

## Summary

Four small changes and one deletion of a lie. Each is confined to the file that had it.

## What the reconnaissance changed

**Item 2 of the issue was already fixed, and the spec had to say so rather than build it twice.**
`DataRegistry` has `registerDeferred()` and resolves on first read, exactly once, guarded against
re-entry — precisely the `defer(callable)` the issue proposes. `ConfigServiceProvider` already uses
it, with a comment naming the same problem the issue names. Spec 074 merged at 07:44 on 2026-07-27;
the issue was filed at 15:04 the same day, against a tree from before it.

Checking rather than trusting the report saved building a second mechanism beside a working one.
The rest of the issue's items were verified present, in the tree, at the lines it named.

## Technical Context

**Language/Version**: PHP 8.3

**Primary Dependencies**: the existing Forms event pipeline, `NotificationDispatcher`, CoreX Mail's
`MailRequest`/`EmailMessage`, and the Email Studio `Layout`. No new dependency.

**Storage**: none. No schema change, no migration, no stored value moves.

**Testing**: Pest unit + integration.

**Constraints**: listener resolution stays lazy (the mail graph reaches translations, and building it
before `init` triggers `_load_textdomain_just_in_time`); nothing that reaches a mail header is
trusted unvalidated.

## Constitution Check

- [x] **III. Thin controllers, fat services** — the provider wires; the decision about which
      listeners belong to a form stays on the form.
- [x] **IV. Everything injected** — `Layout` is constructor-injected into the gateway; the container
      already binds it.
- [x] **VII. Declarative security** — the two new values that reach a mail header
      (`Reply-To` from a submission, and the configured reply-to) are validated with `is_email()`
      and sanitised.
- [x] **VIII. RTL-first** — the layout already carries `dir`; a test now pins it.
- [x] **IX. No optional dep is hard** — none of this requires Email Studio to be active.
- [x] **X. Spec is source of truth**.

**No violations.**

## Approach

### 1. One listener, resolved per submission

`registerListeners()` registered a *set* — every distinct listener id across every form, on the
shared event. It now registers **one** callback that looks up the submitted form by slug and runs
that form's list.

Everything the original was protecting is preserved: resolution is still lazy (the callback runs at
request time), and listeners are still singletons, so one shared by three forms is built once.

An unknown slug returns early. That is the whole handling — a database-defined flow is not a
code-defined form, and it has no listeners by definition.

### 2. `htmlBody()` beside `plainTextBody()`

A second method, not a change to the first. A genuinely plain-text transport would still want the
plain-text form, and quietly returning HTML from a method called `plainTextBody` is exactly the kind
of mismatch that caused this.

Values are escaped, arrays are JSON-encoded rather than stringified to `Array`, and `nl2br` keeps a
textarea answer readable.

### 3. `Reply-To` from the submission

`MailRequest` already accepts `replyTo` and `WpMailDriver` already emits the header — the listener
simply never passed one. The address is looked up across the field names a form is likely to use and
**validated with `is_email()` before it goes anywhere near a header**. No address found means no
header, which is what happened before.

### 4. The reply is wrapped

`reply()` now wraps the operator's HTML in the injected `Layout` and uses the configured reply-to
instead of a hard-coded null. It deliberately does **not** go through a template.

### 5. The logo becomes reachable

`MailServiceProvider::brand()` gains a `logo` key: the theme's custom logo, falling back to the site
icon, both absolute. The `Layout` branch is unchanged — it was always correct, just unreachable.

## Complexity Tracking

No violations.

One risk accepted: making `reply()` wrap its body changes what existing sites' manual replies look
like. That is the point of the change, and it makes them consistent with every other email the site
sends, but it is a visible change to a shipped behaviour rather than a pure bug fix.
