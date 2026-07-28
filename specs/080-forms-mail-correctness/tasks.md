# Tasks: Forms & Mail Correctness

**Branch**: `spec/080-forms-mail-correctness` | **Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md)

## Phase 1 — Verify the report against the tree

- [x] **T001** Confirm each of items 1–5 at the lines the issue names. All five present as described.
- [x] **T002** Item 2 — **already fixed.** `DataRegistry::registerDeferred()` resolves on first read,
      exactly once, re-entry guarded; `ConfigServiceProvider` already uses it, citing the same
      problem. Spec 074 merged 07:44 on 2026-07-27; the issue was filed at 15:04 the same day against
      an older tree. No work needed — and checking saved building a second mechanism beside a
      working one.

## Phase 2 — The fixes

- [x] **T010** Per-form listener dispatch in `FormsServiceProvider::registerListeners()`.
- [x] **T011** `NotificationDispatcher::htmlBody()`, beside the retained `plainTextBody()`.
- [x] **T012** `SendEmailListener` sends HTML and a validated `Reply-To`.
- [x] **T013** `EmailStudioSubmissionGateway::reply()` wraps in `Layout`, uses the configured reply-to.
- [x] **T014** `MailServiceProvider::brand()` supplies an absolute `logo`.

## Phase 3 — Tests

- [x] **T020** Integration: per-form listeners — own listeners only, removal works, unknown slug runs
      nothing. **Verified load-bearing**: restoring the global registration fails the two
      cross-form tests.
- [x] **T021** Unit: notification body — separation, escaping, line breaks, arrays, empty, and that
      the plain-text form still exists.
- [x] **T022** Unit: layout — logo rendered, fallback, attribute escaping, reply wrapped in a
      document, RTL carried.
- [x] **T023** Brain Monkey stubs for the newly-reached WordPress functions in the existing
      `SendEmailListenerTest`.

## Phase 4 — Close

- [x] **T030** Full gate.
- [ ] **T031** Docs, PROGRESS, DECISIONS.
- [ ] **T032** PR, green CI, merge, delete branch. Leave issue #138 open for items 6–9.

## Findings log

- **T002** — the most useful outcome of the whole phase was discovering there was nothing to do.
  A report written against a tree hours old described a defect that had just been fixed; building
  the proposed `defer()` would have added a second mechanism beside a working one.
- **T010** — the two tests that catch the defect are the two *cross-form* ones. The removal and
  unknown-slug tests pass under the old code as well, for an incidental reason (the fixture forms
  register after boot, so the old boot-time sweep saw none of them). Worth recording: two of four
  green would have looked like partial success rather than what it is.
- **T012** — `MailRequest` has accepted `replyTo` all along and `WpMailDriver` has always emitted
  the header. The missing piece was one argument, not a feature.
