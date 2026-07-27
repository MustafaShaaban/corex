# Spec 074 — Core Admin Truthfulness and Integration Closure

- **Status:** in progress
- **Branch:** `spec/074-core-admin-truthfulness`
- **Baseline:** `main` @ `9b5939f` (PR #129, Spec 073)
- **Supersedes:** the issue-#114 fix's assumption that a site will inject its own forms
- **Follows:** Spec 073 · **Followed by:** Spec 075 (Blog Pro Functional Completion)

---

## 1. Problem

Four product-completion defects survived Spec 073. Each was reproduced on the real local
WordPress install (`http://corex.local`, WP 7.0, WAMP) before any code was written; the captures
live in `evidence/before/`.

### D1 — A form registered in code is invisible to the framework

`Corex\Forms\FormRegistry` is the documented way to declare a form in code. Nothing in CoreX
discovers it.

- `FlowFilterOptions::all()` (`plugins/corex-config/src/Forms/FlowFilterOptions.php:43`) builds
  its list from `FlowRepository->all()` — database flows only.
- Code forms reach the Submissions and Data filters **only** if the site adds its own
  `corex_submission_filter_options` hook. PR #123 (issue #114) added that filter; it made an
  injected form filterable, it did not make the framework discover one.
- `FormsFlowsScreen` (`plugins/corex-config/src/Forms/FormsFlowsScreen.php:107`) mounts the flow
  builder and nothing else, so Forms & Flows behaves as though code forms do not exist.

**Consequence.** A downstream site (Perego is the live example) registers its forms exactly as
documented, and then has to patch a CoreX-internal filter on every site to make its own forms
appear in CoreX's own screens.

**Root cause.** There is no form catalog. Two form sources exist and each consumer re-derives its
own list from one of them.

### D2 — Data → Import and Data → Migrations are permanent dead ends

`evidence/before/data-import-dark.png`, `evidence/before/data-migrations-dark.png`.

Both tabs render only *"No registered model provides an import adapter."* / *"…a migration
adapter."* — and they always will, because:

- `SubmissionsSource::capabilities()` and `TableDataSource::capabilities()` both declare
  `importDryRun`, `importCommit`, `migrations`, and `rollback` as `false`.
- `WritableDataSource` and `MigrationProvider` are implemented **only** by anonymous classes inside
  `tests/`. No shipped source implements either.
- `modelClient.js:10` gates all five tabs on the `models` ability alone, so being *permitted* to
  open Import is treated as reason enough to show it.

**Consequence.** CoreX advertises two primary workspace tabs for capabilities nothing can satisfy,
and explains the emptiness in a word ("adapter") that means nothing to the person reading it.

**Root cause.** Capability declaration exists (`DataSourceCapabilities` is granular and complete)
but no source can *opt in*, and tab visibility ignores capability entirely.

### D3 — The Submission Inbox heading has no layout-level spacing

`evidence/before/submissions-dark.png` — "TEAM WORKSPACE / Submission Inbox / 194 accessible
submissions" render as one compressed block.

`plugins/corex-config/src/Submissions/index.js:114` wraps the three lines in a bare `<div>` with no
layout. `assets/submissions-admin.scss:17-28` then sets `h2 { margin-block: 0 }` and
`p { margin-block: var(--corex-admin-space-2xs) 0 }` — the only separation any of the three lines
gets is one 2xs margin, applied per-paragraph rather than to the stack.

**Root cause.** Spacing is expressed as per-element margins on a container that has no layout.

### D4 — Notifications conflate *read* with *resolved*, and under-report

`evidence/before/notifications-dark.png`.

`Corex\Notifications\Notification` carries type, category, severity, source module, source
type/id, occurrence count, first and latest occurrence, expiry, resolution state and reason,
environment, actor, recipient, and an optional action. The screen renders **title, body, and
"Mark read"**. Nine competing view tabs sit above it.

- `NotificationController` already registers `read`, `unread`, `dismiss`, `snooze` (line 74) and
  `resolve` (line 81). Only `read` and `read-all` are wired in the UI.
- The "Requires attention" view filters on `status: 'unread'`. Reading a production readiness
  blocker therefore removes it from the attention list while the blocker is still true.
- Producer copy is event-shaped, not outcome-shaped: *"Readiness blocker: HTTPS"*,
  *"Background job failed"*, *"New access request"*.

**Root cause.** Per-user *read* state was used as the proxy for the condition's *resolution* state,
and the presentation layer surfaces a fraction of the record.

---

## 2. User stories

- **US1 — Downstream developer.** I register a form in `FormRegistry` on my site. Without writing
  any CoreX filter, it appears in Forms & Flows, in the Submission Inbox form filter, in the Data
  Records form filter, and in filtered CSV exports.
- **US2 — Site operator.** Opening Forms & Flows I see one catalog. Visual flows are editable.
  Code forms are shown as real definitions I can read — fields, validation, where their submissions
  are — with a plain statement that they live in code.
- **US3 — Site operator.** I am never shown a Data tab that cannot do anything. When a model cannot
  be imported into, the Models catalog tells me so in words I understand, without the word
  "adapter".
- **US4 — Site operator.** I import a CSV into a model that genuinely supports it: dry run, mapping,
  rejections, explicit unknown-column behaviour, confirmation, commit, audit trail.
- **US5 — Site operator.** I apply and roll back a schema migration on a model that genuinely
  supports it, and rollback is offered only when it truly works.
- **US6 — Anyone with the bell.** Every notification tells me what happened, why it matters, what to
  do, where it came from, and whether the condition is still live. Reading it does not make it go
  away.
- **US7 — Authorised operator.** I can mark unread, dismiss, snooze, and resolve — and I am not shown
  a Resolve control I am not permitted to use.
- **US8 — Site operator.** One place tells me what is registered, what each thing can do, and what is
  missing — with a link to fix it.

---

## 3. Functional requirements

### FR-1 · Unified form catalog

- **FR-1.1** A `FormCatalog` merges visual flows (`FlowRepository`), code forms
  (`FormRegistry::all()`), and third-party entries from registered `FormCatalogProvider`s.
- **FR-1.2** Each entry exposes, where available: stable slug · display label · flow id or `null` ·
  source (`visual_flow` | `code_form` | `external`) · field count · active/published state ·
  editable-in-builder flag · submission count **or an explicitly unavailable state** · the
  capability required to manage it.
- **FR-1.3** Entries are deduplicated by canonical slug. Precedence on collision is deterministic:
  **visual flow > code form > external**. A shadowed entry is retained for diagnostics, never shown
  twice.
- **FR-1.4** Malformed third-party entries are dropped, never rendered as broken controls.
- **FR-1.5** No site must add `corex_submission_filter_options` for its code forms to appear.
- **FR-1.6** The `corex_submission_filter_options` filter keeps working, applied **after** the merge,
  with `id => 0` retaining its "match by slug" meaning.
- **FR-1.7** Numeric `corex_flow_id` filtering, `corex_form_slug` filtering, and filtered CSV export
  all keep working unchanged.
- **FR-1.8** Consumers: Forms & Flows, Submission Inbox form filter, Data Records form filter,
  Overview form counts, capability diagnostics.
- **FR-1.9** Forms & Flows shows code forms as read-only definitions: label, slug, source badge,
  field definitions, validation summary, a link to their submissions, and a plain-language
  explanation that they are defined in code. No code form is written to the database.
- **FR-1.10** Forms remains an optional add-on: an absent Forms plugin yields an empty catalog, never
  a fatal.

### FR-2 · Submission Inbox heading rhythm

- **FR-2.1** The eyebrow / title / count stack gets layout-level spacing from design tokens only.
- **FR-2.2** Correct with long translations, in RTL, at narrow widths, and at 200% zoom.
- **FR-2.3** No additional whitespace is introduced around the header as a whole.

### FR-3 · Truthful Data Models

- **FR-3.1** A workspace tab is shown only when the actor is permitted **and** at least one
  registered source supports it. Otherwise it is hidden, not shown empty.
- **FR-3.2** The Models catalog states each model's capabilities in plain language and distinguishes
  read-only · writable · import-capable · export-capable · migration-capable.
- **FR-3.3** "Adapter" terminology appears only in developer diagnostics, never in the user workflow.
- **FR-3.4** A managed model opts in to writes/import/migrations by **explicit declaration**:
  writable fields, import aliases, per-field validation, unknown-column policy, migration
  definitions. Absent declaration means read-only — the safe default.
- **FR-3.5** Import requires a dry run; commit goes through the existing confirmation token and job
  infrastructure and records activity.
- **FR-3.6** Migrations come from explicit definitions. Rollback is offered only when the definition
  declares a down path; snapshot claims are truthful.
- **FR-3.7** Form submissions are **not** importable. They are historical visitor records.
- **FR-3.8** Audit and system tables (activity, jobs, login attempts, notifications, notification
  user state, access grants, access requests, blog reading events) remain read-only.
- **FR-3.9** At least one shipped model proves import and migrations end to end.

### FR-4 · Notification action center

- **FR-4.1** *Read* and *resolved* are separate states. A read but unresolved condition stays in
  Action needed until resolved, dismissed, expired, or superseded by recovery.
- **FR-4.2** A new occurrence reopens a resolved condition.
- **FR-4.3** Top-level views: **Action needed · Updates · History · Preferences**. Category, source
  module, severity, environment, and assignment are filters, not tabs.
- **FR-4.4** Item anatomy: category icon · human title · concise body · severity badge **with text**
  · source module · environment where relevant · relative time plus accessible exact timestamp ·
  occurrence count when > 1 · primary action when one exists · read/unread · resolved/unresolved ·
  permitted secondary controls.
- **FR-4.5** Wired actions: mark read, mark unread, dismiss, snooze, resolve (authorised only),
  primary contextual action, mark all read.
- **FR-4.6** An unauthorised actor is not shown Resolve at all.
- **FR-4.7** Producer copy is outcome-first and human ("3 submissions need assignment", "Contact-form
  email delivery failed", "Production readiness has 2 blockers", "Your CSV export is ready",
  "Repeated login failures triggered a temporary lockout").
- **FR-4.8** Icons come from the existing icon/token system. No emoji, no external icon dependency,
  no meaning carried by colour alone.
- **FR-4.9** The drawer and the full screen render the same item component, so they cannot drift.

### FR-5 · Capability diagnostics

- **FR-5.1** A focused summary on the existing Models screen reports registered forms and their
  source; registered data models and their read/write/import/export/migration capability; add-on
  installed/active/configured/operational state; active notification producers; missing required
  providers or configuration; and a direct action or documentation reference.
- **FR-5.2** It never exposes secrets, tokens, credentials, stack traces, or private data.
- **FR-5.3** It does not replace any user workflow.

---

## 4. Non-functional requirements

- **NFR-1** Pure domain logic is WordPress-free and unit-tested; WordPress access stays at the
  boundary (`Wp*` classes), consistent with the existing architecture.
- **NFR-2** No new hard dependency on an optional add-on. Absent Forms / Newsletter degrades to an
  honest reduced state.
- **NFR-3** Every list query stays bounded; every `$wpdb` statement is prepared.
- **NFR-4** No hardcoded colours, sizes, or fonts. Tokens only, logical CSS properties.
- **NFR-5** The form catalog adds no unbounded query: submission counts are batched or reported
  unavailable.

## 5. Security and permission boundaries

- **SEC-1** Every REST route keeps its capability check and nonce verification. No route is loosened.
- **SEC-2** Import and migration commits require the source's declared ability, a confirmation token,
  and record an activity event with the actor.
- **SEC-3** Production mode keeps its warning and explicit confirmation for destructive operations.
- **SEC-4** `resolve` stays `canManage`-gated; the UI hides what the actor may not do, and the server
  still rejects it if called directly.
- **SEC-5** Write adapters accept only explicitly declared writable fields. Undeclared columns are
  never written, whatever the CSV contains.
- **SEC-6** Third-party catalog providers are untrusted input: normalised, escaped, and dropped when
  malformed.
- **SEC-7** Diagnostics output is allow-listed; no secret-bearing key can reach it.

## 6. Accessibility and RTL

- **A11Y-1** WCAG 2.2 AA. Severity and state are conveyed by text, not colour alone.
- **A11Y-2** Every control is reachable and operable by keyboard, with a visible focus ring.
- **A11Y-3** Timestamps use `<time datetime>`; relative time has an accessible exact value.
- **A11Y-4** Lists and view switches carry correct roles, names, and `aria-current`.
- **A11Y-5** Destructive actions confirm before acting.
- **A11Y-6** Logical CSS properties throughout; verified in Arabic/RTL.
- **A11Y-7** No horizontal overflow at narrow widths or 200% zoom.

## 7. Tests

Unit (Pest): catalog merge · slug precedence · malformed-provider rejection · unavailable submission
count · `ManagedTable` declaration validation · derived capability flags · notification status
derivation (read vs resolved vs dismissed vs snoozed vs expired) · producer copy.

Integration: Perego-style `FormRegistry` registration with **no** filter hook present · legacy
`corex_submission_filter_options` still applied · filtering and CSV export by both `corex_flow_id`
and `corex_form_slug` · managed-model dry run → mapping → rejections → commit → audit · migration
preview → apply → history → rollback · permission denial · production-mode confirmation · failure
states.

Jest: tab eligibility · notification item states · view/filter behaviour · catalog rendering.

Playwright: Forms & Flows catalog · inbox heading rhythm (desktop / 360px / 200% zoom, LTR + RTL) ·
hidden-vs-shown Data tabs · notification action center across empty, loading, REST failure, unread,
read-unresolved, resolved, dismissed, snoozed, reopened, deduplicated, no-action, and authorised vs
unauthorised resolve · drawer ↔ screen parity · keyboard navigation · screen-reader names.

## 8. Definition of Done

1. Every FR above implemented and covered by a test.
2. Full gate green: PHP lint · Pest unit · WordPress integration · Jest · production builds · CSS
   lint · repository guards · token inventory reproduces · Playwright · CodeQL.
3. Guard Gate clean on the diff: `clean-code-guard`, `wp-guard`, `test-guard`, `docs-guard`.
4. Browser acceptance on the real install in dark and light, English/LTR and Arabic/RTL, desktop and
   narrow, keyboard-only, and 200% zoom — with no horizontal overflow, console error, failed REST
   request, uncaught JavaScript error, or blank React mount.
5. `evidence/after/` captures referenced from this file.
6. `PROGRESS.md`, `ROADMAP.md`, `DECISIONS.md`, `CHANGELOG.md`, and the affected docs updated.
7. No placeholder, "coming soon", dead-end tab, or non-functional control remains in the touched
   areas.

## 9. Evidence

Captured on the real install (`corex.local`) with `tests/e2e/render-admin.mjs`, dark and light at
1440×900 unless noted. `evidence/before/` is the state at the 073 merge (`9b5939f`).

**The four defects, before and after**

| Defect | Before | After |
| --- | --- | --- |
| D1 · code-registered forms invisible | `before/forms-dark.png` | `after/forms-dark.png`, `after/forms-light.png` |
| D2 · Import / Migrations dead ends | `before/data-import-dark.png`, `before/data-migrations-dark.png` | `after/data-import-dryrun-dark.png`, `after/data-import-committed-dark.png`, `after/data-migration-preview-dark.png`, `after/data-migration-queued-dark.png`, `after/data-migration-history-dark.png` |
| D3 · inbox heading collision | `before/submissions-dark.png` | `after/inbox-heading-dark.png`, `after/submissions-dark.png` |
| D4 · read conflated with resolved | `before/notifications-dark.png` | `after/notifications-dark.png`, `after/notification-item-dark.png` |

**FR-5 · capability diagnostics** — `after/data-models-dark.png`, `after/data-models-light.png`.
The panel shows the two live gaps on this install (a captcha driver selected with no keys, no update
endpoint), the code-registered `contact` form discovered with **no filter hook present** (FR-1), and
`corex_subscribers` as the one model declaring Read · Write · Import · Export · Migrations · Rollback
(FR-3).

**Browser acceptance (DoD item 4)** — RTL, 360 px, and 200 % zoom for both surfaces this spec
changed: `after/{data-models,notifications}-{rtl,narrow,zoom200}.png`.

- **No horizontal overflow** in any of the six conditions; `scrollWidth` equals `clientWidth` at
  1440 (RTL), 360, and 720@2× on both screens.
- **RTL mirrors from logical properties alone** — the gap accent edge, the capability lists, and the
  notification item's leading edge all flip with no direction-specific rule. (The rail artefact at
  the top of `data-models-rtl.png` is the capture tool setting `dir` after load, not a layout
  defect; the asserted RTL behaviour is in `tests/e2e/notification-center.spec.js`.)
- **No console error, uncaught JavaScript error, failed `/wp-json/` request, or blank React mount**
  on Data Models, Data → Import, Data → Migrations, Notifications, Forms & Flows, or Submissions.

## 10. Explicit exclusions

- **Company Site Kit** — not started, not extended.
- **Client or company website work** — prohibited; no recommendation to begin one.
- **Perego** — inspected read-only as downstream evidence. Not modified.
- **Blog Pro** — Spec 075.
- **A full Capability Inspector / System Map** — recorded as a roadmap candidate only (ROADMAP §17).
- **Notification email/channel delivery** — `NotificationChannelPolicy` stays unbuilt; there is still
  no delivery channel to guard.
- **Major dependency migrations** (Astro 7, Pest 4, `@wordpress/scripts` 33, `@wordpress/components`
  37) — independent future work, never bundled with product closure.
- **Making submissions importable** — explicitly rejected, not deferred.
