# Tasks: Production Findings from a Live Build

Verification came first, and changed the work twice — one reported defect was already fixed, and one
"obvious" one-line fix would have made a data-loss bug worse.

## Phase 1 — Verify every claim before writing anything

- [x] **T001** Re-check each of the eleven reported items against the current tree.
      **Finding A:** #150's "Correction to #138 item 3" is stale — `reply()` does call
      `replyToAddress()`. The reporter's own later comment on #149 confirms it. Nothing done.
      **Finding B:** #149 item 1b (`RecordDetail` handling one record shape) was already fixed by
      spec 080; `recordRows()` has the `Array.isArray(record.fields)` branch. Only 1a remained.

## Phase 2 — #149, the Data admin

- [x] **T010** `detail()` returns the payload. One line; the modal had never worked for any source.
- [x] **T011** A test through `detail()` itself, proven to fail against the old line. The existing
      test passed throughout because it calls `recordRows()` directly — it skips the broken layer.
- [x] **T012** `FieldValue` — links `^https?://\S+$` only, everything else inert, with the refusals
      tested one by one.
- [x] **T013** Used by both the Data detail and the Submissions drawer.

## Phase 3 — #148, forms and the runtime

- [x] **T020** Multi-value: `collect()` reads `selectedOptions`, `sanitizeShape()` gains a list arm.
      Shipped together, and tested together, because each alone is worse than the bug.
- [x] **T021** `ValidationMessages` rendered as `data-corex-messages`; `messageFor()` prefers it and
      keeps the `wp.i18n` table as a fallback; the three server literals go through `__()`.
- [x] **T022** `novalidate` on both form renderers, matching their siblings.
- [x] **T023** `Rules\Phone` + registry entry; `url` and `phone` in the client table, mirroring the
      PHP exactly. `max_words` deliberately not added — see the spec.
- [x] **T024** `revalidateField()` on blur, and on input/change once a field is already invalid.
- [x] **T025** `inputmode`/`autocomplete`/`dir` for `phone`, `email`, `url`.
- [x] **T026** `corex:form:error` on the client branch, carrying the failing field names.
- [x] **T027** The spinner's border as longhands, so a missing token costs the width and not the
      whole declaration. Longhands rather than a raw fallback: it satisfies the token contract too.

## Phase 4 — #150, the mail stack

- [x] **T030** `?string $from` through `MailRequest`, `EmailMessage`, `MessageBuilder`,
      `RequestMailer` and `WpMailDriver::fromHeader()`. Last parameter, nullable, additive.
- [x] **T031** The queue arm, so an immediate and a queued message leave from the same address.
- [x] **T032** Tests, including the null case — `''` would reach the driver as a mailbox named
      nothing.

## Phase 5 — Close

- [x] **T040** Full gate: Pest unit + integration, Jest, Playwright, lints, token inventory.
- [x] **T041** Guards: `wp-guard`, `clean-code-guard`, `test-guard`.
- [x] **T042** `PROGRESS.md`, `DECISIONS.md`, PR, close #148 / #149 / #150.
