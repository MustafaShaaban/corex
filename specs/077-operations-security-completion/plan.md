# Implementation Plan: Operations & Security UX and Safety Completion

**Branch**: `spec/077-operations-security-completion` | **Date**: 2026-07-27 | **Spec**: [spec.md](spec.md)

## Summary

Five areas become five sections of one screen, the mode form asks only what the chosen mode needs,
and two truthfulness defects get fixed. Almost no new domain logic — the work is arrangement,
disclosure, and two small guards.

Two decisions shape everything else.

**Sections are links, not an ARIA tablist.** `AdminPage::tabs()` already exists and already renders
`?tab=` links with `aria-current="page"` — Add-ons uses it. Real links give FR-002 and FR-003 for
nothing: the address reflects the section, Back and Forward work, a PRG redirect can land on the
right one, keyboard navigation is the browser's, and it all works with JavaScript off. An ARIA
`role="tablist"` would mean in-page panels, `aria-selected`, roving tabindex and focus management —
more code, more to break, and it cannot satisfy FR-013 without duplicating the server render anyway.

**Progressive disclosure degrades to a second step rather than to a wrong form.** The mode form is
a plain server POST today with no client behaviour at all, so "show only what this mode needs" has
to work in a world where the browser cannot react to the select. It resolves as: the server renders
the disclosure for a *proposed* mode carried in the URL, defaulting to the current one. With
JavaScript the select swaps the block inline, one step. Without it, submitting a mode that needs a
confirmation comes back — via PRG — with that mode proposed and its confirmation shown. Two steps,
never a wrong one.

## Technical Context

**Language/Version**: PHP 8.3, JavaScript (ES2020, `@wordpress/scripts`), CSS

**Primary Dependencies**: WordPress 7.0+, the existing `AdminPage`, `OperationsMode*`,
`ReadinessSnapshot`, `LoginSlug`, `MaintenanceGuard`. No new runtime dependency.

**Storage**: Unchanged. One behavioural guard added to an existing write.

**Testing**: Pest (unit + integration), Jest, Playwright.

**Target Platform**: wp-admin, server-rendered plus one React island, LTR + RTL.

**Constraints**: every state-changing control works with JavaScript disabled; no new capability; no
control without a real implementation behind it; no empty section.

**Scale/Scope**: 1 screen (443 lines today), 1 controller, 1 store guard, 1 new availability
service, ~350 lines of CSS reworked.

## Constitution Check

- [x] **I. Theme is a skin** — plugin-side admin only.
- [x] **II. Plugins boot themselves** — no new bootstrapping; existing providers.
- [x] **III. Thin controllers, fat services** — the no-op guard belongs to the store (the invariant
      is "the log records changes"), not to the controller. Slug availability is a service.
- [x] **IV. Everything injected** — the new availability service resolves through the container.
- [x] **V. Runtime tokens** — the spacing rework is tokens only; no raw values.
- [x] **VI. Conditional assets** — the disclosure script enqueues on this screen alone, through the
      existing `maybeEnqueue()` hook gate.
- [x] **VII. Declarative security** — no new route. The existing nonce + capability gate stays, and
      server-side validation remains the only authority (FR-010).
- [x] **VIII. RTL-first** — logical properties throughout the spacing rework.
- [x] **IX. No optional dep is hard** — none involved.
- [x] **X. Spec is source of truth** — traces to spec.md; the scoped-out areas are listed there.
- [x] **Guard Gate + DoD** — `wp-guard`, `clean-code-guard`, `test-guard`, `docs-guard`.

**No violations.**

## Project Structure

```text
plugins/corex-config/src/Security/
├── OperationsSecurityScreen.php        # MODIFIED: five sections, one active at a time
└── Sections/                           # NEW: one renderer per section, so the screen stops
    ├── OverviewSection.php             #      being a 443-line method bag
    ├── EnvironmentSection.php
    ├── LoginProtectionSection.php
    ├── HardeningSection.php
    └── ActivitySection.php

plugins/corex-config/src/Operations/
├── OperationsModeStore.php             # MODIFIED: a no-change is not a change (FR-009)
├── OperationsModeController.php        # MODIFIED: PRG keeps the section; proposes a mode
└── ModeDisclosure.php                  # NEW: what each mode requires and warns about (FR-006/7)

plugins/corex-config/src/Security/LoginProtection/
└── LoginSlugAvailability.php           # NEW: the collisions a pattern cannot see (FR-018)

plugins/corex-config/assets/
├── operations-security.css             # MODIFIED: one container, token gaps (FR-020)
└── operations-mode.js                  # NEW: the disclosure enhancement, ~40 lines
```

**Structure Decision**: the screen's five areas become five renderers under `Sections/`. Today one
class holds `statusNotice`, `modeCard`, `auditCard`, `checksCard`, four payload builders and three
helpers — and it is the file this spec has to restructure most. Splitting by section makes "render
only the active one" a dispatch rather than a set of conditionals, and it means a future section
(078's Cache & Performance) is a new file rather than another method.

## Approach

### 1. Five sections, one at a time

`render()` resolves `?tab=` through an allow-list, defaults to `overview`, falls back to `overview`
for anything unknown, and renders the strip plus that one section. Everything else is unchanged
capability and nonce handling.

| Section | Holds |
|---|---|
| `overview` | the real status summary of FR-005, each item linking to its section |
| `environment` | environment vs mode, the mode form, readiness, maintenance, mode history |
| `login` | the login-protection policy, the live login URL, lockouts, recovery |
| `hardening` | the hardening checks, grouped |
| `activity` | security activity |

The React island (`#corex-security-app`) currently renders readiness, login policy, lockouts,
recovery and activity in one mount. Those belong to three different sections now, so the app is
mounted per section with the section named on the mount node, and it renders only that section's
panels. That keeps one app rather than three, and keeps its state module untouched.

### 2. The mode form asks only what applies

`ModeDisclosure` answers one question per mode: what does this mode mean, what does it warn about,
and what must the operator confirm. It is a pure description — no capability logic, no readiness
evaluation — so it is unit-testable without WordPress and so the same answer drives the rendered
form, the client swap, and the server validation.

| Mode | Confirmation | Also shows |
|---|---|---|
| Development | none | public-site warning, debug status |
| Staging | none | indexing recommendation, external-service warning |
| Production | typed `PRODUCTION` | readiness result, blockers, warnings, resolution links |
| Maintenance | acknowledgement | visitor 503, admin passthrough, REST/AJAX/cron behaviour, recovery |

The server renders the block for the **proposed** mode — `?mode=` when present and valid, otherwise
the current mode. `operations-mode.js` swaps blocks on `change` (all four are rendered, inactive
ones `hidden`), so with JavaScript it is one step. Without JavaScript, choosing Production and
submitting fails validation, and the controller redirects back to `?tab=environment&mode=production`
where the confirmation is now shown, with a notice saying so. Two steps, and the second one is
correct — rather than one step that showed the wrong fields.

Rendering all four blocks and hiding three is deliberate: the alternative is fetching a block over
REST on change, which adds a route, a failure mode and a spinner to a form that has none.

### 3. The two truthfulness fixes

**A no-change is not a change (FR-009).** `OperationsModeStore::set()` returns early when
`$from === $to`: no `update_option`, no log entry. The guard belongs here rather than in the
controller because the invariant is about the log — *the history records changes* — and a guard in
the controller only protects the one caller that goes through it.

The controller then distinguishes "applied" from "already in that mode" and its PRG notice says so.
The Apply button is also disabled client-side when the selection matches the current mode (FR-008),
but that is a courtesy; the store is the guarantee.

**Environment and mode are both shown (FR-014).** The environment section leads with the two values
side by side and a warning when they differ, in words that never imply the mode changes hosting.
`OperationsModeStore::isDeclared()` already distinguishes "declared" from "inherited", which is what
makes the warning honest: an inherited mode is not a conflict.

### 4. Slug collisions a pattern cannot see (FR-018)

`LoginSlug` stays exactly as it is — a pure value object with no WordPress dependency, which is why
it is testable and why it has held. `LoginSlugAvailability` is the WordPress-aware companion:
`get_page_by_path()` for a published page at that path, and the rewrite rules for an endpoint. It
returns a typed reason, matching `LoginSlug`'s existing vocabulary, and the settings controller
consults both.

### 5. Spacing (FR-020)

One container owns the rhythm — `.corex-opsec__sections` with a token `gap` — and the per-section
`margin-block-end: 24px` declarations come out. The measured evidence shows sibling gaps already at
24px, so this is about *where the number lives*, not about changing it: a gap on the container
cannot double, cannot collapse, and cannot disagree between two siblings.

### 6. What is not touched

`LoginSlug`'s rules, `MaintenanceGuard`, `ReadinessSnapshot`'s states, the security REST controller,
and the date rendering (spec 076 owns it — verified here by test, not re-implemented). No unlock
control is added: the audit found no real, audited unlock path, and offering one that does not exist
is the defect this project keeps removing.

## Complexity Tracking

No violations.

The one judgement worth recording: splitting the screen into five renderer classes is more files
than the change strictly needs. It is justified by what the file is today — 443 lines holding every
section's markup, four payload builders and the PRG notice — and by 078, which adds a sixth section
to this same screen. Adding it as another method on an already-overloaded class is how this screen
reached its current state.
