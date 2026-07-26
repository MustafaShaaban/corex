# Spec 073 — Admin polish & correctness

**Status:** IMPLEMENTATION-COMPLETE
**Branch:** `spec/073-admin-polish-and-correctness`
**Governing rule:** the approved current design is the functional contract; a required control may not
remain inert, and a screen may not silently drop content (Spec 068 rule, ROADMAP §17). DECISIONS #141
(every admin selection control is `CorexSelect`) applies to the dropdown items below.

## Why

A pass over the shipped CoreX admin found controls that were present but not honest: a mode selector
that applied nothing, a readiness badge that read "Ready" over listed blockers, a records modal that
showed em dashes where a submission's answers should be, one screen that had silently lost every inline
icon, dropdowns still drawing the OS menu, and a toolbar entry that printed its unread count twice. Each
was reproduced on `corex.local` before any code changed. None changes what the admin *can* do — each
makes an existing surface tell the truth.

## FR-001 — The Add-ons grid can be narrowed by state, truthfully

**The defect.** The Add-ons screen listed the whole catalog with no way to narrow it, and had no
language for "this view is empty" distinct from "no add-ons exist".

**The fix.** `AddonCatalogService::filter()` and `counts()` split the catalog into mutually exclusive,
exhaustive buckets — All / Active / Inactive / Not installed — keyed on the same `AddonView::status()`
that prints each card's badge, so filtering by *Active* returns exactly the cards badged Active. *Inactive*
is deliberately *installed but not running* (an actionable state); a package not on disk is *Not installed*,
a different problem, so it gets its own bucket. `AddonsScreen` renders the buckets through the shared
`AdminPage::tabs()` strip (plain query-arg links — works without JS, bookmarkable), each carrying its real
count. An empty bucket shows a distinct "No add-ons in this view" state, and an unknown `tab` query value
falls back to All rather than rendering an empty grid that reads as "this site has no add-ons".

## FR-002 — The Data Models screen keeps its icons

**The defect.** The Data Models screen alone rendered an empty notification bell and lost its brand mark
and rail icons — an apparent missing-icon bug.

**The fix.** `DataModelsScreen::render()` echoed the shell through `wp_kses_post()`, whose allowed-tags
list excludes `<svg>`, so it deleted every inline glyph in the trusted CoreX chrome. `AdminPage` escapes
each dynamic value at the point it interpolates it (`esc_attr`/`esc_html`/`esc_url`) and its header/rail
contributors escape their own content, so its return value is trusted markup; re-filtering it removed
correct output rather than adding safety. The screen now echoes it directly, as every other CoreX screen
does. (DECISIONS #155.)

## FR-003 — Operations & Security has one mode control, and its readiness badge is truthful

**The defect.** The Operations & Security page carried two mode controls stacked vertically: a real,
nonce- and capability-gated server form (`OperationsSecurityScreen::modeCard()`) and, above it, a
client-side "mode preview" (target-mode selector, a *Type PRODUCTION* box, a maintenance checkbox) that
applied nothing. Worse, the "Production readiness" badge read its blocker count from `modeActionState`,
which reports zero for every mode except Production — so the header claimed **Ready** while real blockers
were listed directly beneath it, unless the preview happened to be set to Production.

**The fix.** The inert preview and every piece of client state that existed only to serve it
(`selectedMode`, `productionPhrase`, `maintenanceConfirmed`, three reducer cases, `modeActionState`,
the `MODES` UI list) are removed; the server `modeCard()` is the only mode control, placed after the
readiness evidence it gates. The badge now reads the readiness snapshot (`state.readiness.blockingCount`)
directly — which is what it was always describing. (DECISIONS #156.)

## FR-004 — The record detail modal shows the record's real content

**The defect.** The Data explorer's detail modal rendered the source *schema* and read
`record[field.key]` off the top level. That works for table sources (a flat row) but not for submissions,
whose `record()` returns `{ id, date, form, fields: [ { label, value } ] }` — every answer nested under
`fields`. Those schema keys are not top-level properties, so each answer rendered as an em dash and the
submitted content — the reason for opening the record — never appeared.

**The fix.** A new `recordRows(record, schemaFields)` makes the record the authority on what it contains
and uses the schema only for order and labels. Three passes in display order: declared fields the record
actually carries (a declared-but-absent field is skipped, not shown empty), the nested `fields` pairs, then
any undeclared keys the record still holds — the last pass keeps a new field visible without anyone
remembering to declare it. Objects render as JSON (not `[object Object]`); empty/absent values render as an
em dash, but `false` renders as `false` (an answer, not an absence).

## FR-005 — Admin dropdowns are the approved control, with translated labels

**The defect.** The Settings dropdowns and the Notifications severity filter were still native `<select>`
elements, whose open menu the OS draws and no CSS can reach (DECISIONS #141), and the severity filter
printed raw English severity keys on an otherwise translated screen.

**The fix.** `SettingsForm::select()` emits `data-corex-select` (the upgrade script is already enqueued on
every CoreX screen); `NotificationsApp` uses `CorexSelect` with translated severity labels.

## FR-006 — The notification toolbar entry states its count once

**The defect.** The admin-bar entry used the counted label for both the visible text and the badge,
rendering "Notifications, 7 unread 7" — the same number twice in one node.

**The fix.** The visible label is the plain word; the count lives in the badge. The counted phrasing stays
on the accessible `meta.title` for assistive technology and the tooltip.

## FR-007 — Un-styled admin prose has a consistent baseline rhythm

**The defect.** Text that no component styles fell back to browser defaults, so the same element was
spaced differently depending on the screen (paragraphs at 14px on Operations & Security and Access, 13px on
Insights, beside tokenized text at token spacing).

**The fix.** Zero-specificity `:where()` rules in `corex-admin-shell.css` give paragraphs, headings, and
lists a tokenized baseline margin that any component rule still overrides, plus an outer margin for a
`.corex-state` placed directly between sections. A notification nav icon (`nav-notifications.svg`) is added
so the rail entry and the bell read as one place.

## Out of scope

No new capabilities. No feature-flag, config, or speculative surface. The `corex-notifications` page
identity/label registration in `AdminPage` and the `render-admin.mjs` notifications screen are supporting
wiring for FR-006/FR-007, not new behavior.

## Verification

- Unit (Pest) **1457/0** (6315 assertions) — includes the new Add-ons bucket/exhaustiveness cases and the
  toolbar single-count regression.
- JS (Jest) **311/0** across 58 suites — includes the six `recordRows` cases and the trimmed security state
  suite.
- Guards clean on the diff: `wp-guard`, `clean-code-guard`, `test-guard`.
- `scripts/generate-token-inventory.mjs` reproduces the committed inventory with no further diff.
