# Implementation Plan: CoreX Admin Date & Time Foundation

**Branch**: `spec/076-admin-datetime-foundation` | **Date**: 2026-07-27 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/076-admin-datetime-foundation/spec.md`

## Summary

One presenter in PHP, one formatter in JS, one config boundary between them, then convert the
twelve surfaces that currently lie about time and the three that merely disagree.

The design decision that shapes everything else: **PHP is the sole source of the localized words.**
The browser is given the month names, the period markers and the connector pattern — already
translated — through a single localized object, and composes with them. It never translates a date
itself and never asks the platform for a month name.

That is what makes FR-001 parity structural rather than aspirational. The obvious alternative —
`Intl.DateTimeFormat` with the site timezone and locale — gets the timezone right and the *words*
wrong: `Intl` draws month names from CLDR while WordPress draws them from the `corex` translation
files, and in Arabic those disagree (`أغسطس` vs `آب`). Two implementations reading two different
dictionaries cannot be made character-identical by testing; they can only be made identical by
having one dictionary. JS still uses `Intl` — but only to extract *numeric* parts in the site
timezone, which is exactly the job it does unambiguously.

## Technical Context

**Language/Version**: PHP 8.3, JavaScript (ES2020, `@wordpress/scripts` build)

**Primary Dependencies**: WordPress 7.0+ (`wp_date`, `wp_timezone`, `get_option('timezone_string')`
/ `gmt_offset`), `@wordpress/i18n`, `@wordpress/element`. No new runtime dependency.

**Storage**: Unchanged. Persistence stays `gmdate( DATE_ATOM )` / canonical UTC (FR-014).

**Testing**: Pest (unit + integration), Jest, Playwright.

**Target Platform**: wp-admin, server-rendered screens and React screens, LTR + RTL.

**Project Type**: WordPress framework — `plugins/corex-core` (contract), `plugins/corex-config`
(admin surfaces).

**Constraints**: No new npm dependency; no change to stored or transported values; no CoreX screen
may carry its own copy of the timezone config (FR-008); output must survive 200% zoom and 375px in
both directions.

**Scale/Scope**: 1 PHP presenter, 1 JS formatter, 1 shared config boundary, ~15 call sites across
9 admin screens.

## Constitution Check

- [x] **I. Theme is a skin** — nothing added to the theme; this is plugin-side presentation.
- [x] **II. Plugins boot themselves** — the presenter is registered in the container by the core
      provider and works in CLI/REST/admin/cron; it reads WordPress options, not screen state.
- [x] **III. Thin controllers, fat services** — the presenter is a service; screens and presenters
      call it. No formatting logic in a controller or a template.
- [x] **IV. Everything injected** — resolved from the PSR-11 container. The one deliberate
      exception is documented under Complexity Tracking.
- [x] **V. Runtime tokens** — no styling introduced beyond a token-based secondary line for
      relative time (FR-013); no raw hex/size/font.
- [x] **VI. Conditional assets** — no new bundle. The config attaches to `corex-runtime`, which all
      nine screen bundles already depend on, and which is registered globally and enqueued only on
      CoreX screens.
- [x] **VII. Declarative security** — no route, capability or input introduced. The config exposes
      the site timezone and locale, both already public in page markup.
- [x] **VIII. RTL-first** — the connector is a translatable pattern, not concatenation; the
      relative-time secondary line uses logical properties.
- [x] **IX. No optional dep is hard** — no optional plugin involved.
- [x] **X. Spec is source of truth** — traces to `spec.md`; the one departure it introduces
      (CoreX stops following Settings → General) is recorded in the spec's Assumptions and
      confirmed by the owner.
- [x] **Guard Gate + Definition of Done** acknowledged: `wp-guard` + `clean-code-guard` on
      production code, `test-guard` on tests, i18n, RTL, WCAG 2.2 AA, docs + PROGRESS.

## Project Structure

### Documentation (this feature)

```text
specs/076-admin-datetime-foundation/
├── spec.md
├── plan.md              # this file
├── tasks.md             # /speckit-tasks
├── checklists/
│   └── requirements.md
└── evidence/
    ├── before/          # captured pre-change: the ISO strings and browser-locale renders
    └── after/
```

### Source Code

```text
plugins/corex-core/src/Support/DateTime/
├── AdminDateTime.php            # the contract: format(instant, kind) -> Formatted
├── AdminDateTimeFormatter.php   # the implementation, over wp_date + wp_timezone
├── Formatted.php                # { human, machine, isPresent } — one value object
└── Instant.php                  # parses int | 'Y-m-d H:i:s' | ISO(Z|offset) -> DateTimeImmutable|null

plugins/corex-core/src/Support/DateTime/AdminDateTimeConfig.php
                                 # the payload handed to the browser (names, markers, patterns, tz)

plugins/corex-config/src/AdminUi/CorexAdminAssets.php
                                 # MODIFIED: localizes that payload onto `corex-runtime`, once

plugins/corex-config/src/admin/adminDateTime.js
                                 # the JS formatter — same five kinds, same output
plugins/corex-config/src/admin/components/CorexTime.js
                                 # <time datetime=…>human</time>, plus the relative variant

tests/Unit/Support/DateTime/…    # Pest: the format matrix, timezone matrix, parse matrix
tests/Integration/…              # Pest: real WordPress options drive real output
plugins/corex-config/src/admin/__tests__/adminDateTime.test.js
tests/parity/…                   # the PHP/JS parity fixture — see below
tests/e2e/admin-datetime.spec.js # browser acceptance across the surface list
```

**Structure Decision**: The contract lives in `corex-core/src/Support/` because it is framework
infrastructure with no admin dependency — `Support/` already holds `Uuid` and `Config`, which is the
same kind of thing. The JS formatter lives in `corex-config/src/admin/` beside `CorexSelect` and the
other shared admin components, because that is where every admin bundle already imports shared
pieces from.

## Approach

### 1. The five presentations, and the format strings

`AdminDateTime` exposes exactly the five kinds in FR-002. Each is a translatable *pattern*, not a
concatenation, so a translator can reorder every part:

| Kind | English result | Composed from |
|---|---|---|
| `FULL` | `1 August 2026 at 10:20 PM` | `DATE` and `TIME`, joined by the connector pattern |
| `DATE` | `1 August 2026` | `_x( 'j F Y', 'CoreX admin date format', 'corex' )` |
| `TIME` | `10:20 PM` | `_x( 'g:i A', 'CoreX admin time format', 'corex' )` |
| `RELATIVE` | `2 hours ago` | existing plural strings, moved to the contract |
| `EXACT` | `1 August 2026 at 10:20:24 PM` | `DATE` + `_x( 'g:i:s A', … )` — FR-004, diagnostics only |

The connector is `_x( '%1$s at %2$s', 'CoreX admin date and time, e.g. 1 August 2026 at 10:20 PM',
'corex' )` with a translator comment. This is why the connector is a `sprintf` pattern rather than
`\a\t` escaped inside the date format: a translator who needs `2026 年 8 月 1 日 10:20` can reorder
the two halves, and nobody has to know that `a` and `t` are PHP format characters.

`Formatted` carries `human` and `machine` together, so no caller can render one without having the
other to hand — which is how FR-012 stops being something each call site has to remember.

### 2. Timezone resolution, in both languages

PHP uses `wp_timezone()`, which already resolves `timezone_string` first and synthesises a
`DateTimeZone` from `gmt_offset` when the site is configured by offset. Nothing to invent.

JS is the interesting half. The config carries `timezone` as either `{ name: 'Africa/Cairo' }` or
`{ offsetMinutes: 180 }`, and the formatter branches:

- **Named zone** — `Intl.DateTimeFormat( 'en-US', { timeZone, … } ).formatToParts()` with
  **numeric** options only (`year`, `month: 'numeric'`, `day`, `hour`, `minute`, `hour12: false`).
  This asks `Intl` only for arithmetic, which it does correctly including DST, and never for a word.
- **Bare offset** — shift the epoch by the offset and read UTC parts. There is no DST to apply,
  because a site configured by offset has no DST rules; that is a property of the configuration, not
  a shortcut.

Month name, period marker and connector then come from the config, so the words are WordPress's.

### 3. The single config boundary

`CorexAdminAssets::enqueue()` already runs on every CoreX admin screen — its `SCREEN_PATTERN`
matches all of them — and every one of the nine screen bundles declares `corex-runtime` as a
dependency. So one `wp_localize_script( 'corex-runtime', 'corexDateTime', … )` there is available to
every admin app before it executes, and satisfies FR-008 with one call.

This is worth stating plainly because the alternative is what the codebase does today: **eight
screens each call `wp_localize_script` with their own object** (`corexAccess`, `corexBlogPro`,
`corexDataModels`, `corexEmailStudio`, `corexFlows`, `corexInsights`, `corexSecurity`,
`corexSubmissions`). Adding timezone config to eight payloads is precisely the duplication FR-008
forbids, and it is the shape the code would drift into without a decision recorded here.

The payload is: timezone, locale, `months[12]`, `monthsShort[12]`, `periods { am, pm }`, the three
format patterns, the connector pattern, and the relative-time strings. All already-translated text;
no secrets; nothing not already inferable from the page.

### 4. Parity, tested rather than asserted

A fixture file of instants — the FR/edge-case matrix — is the single input to both suites. The Pest
test formats each and writes the expected output; the Jest test formats the same instants with the
same config and asserts the same strings. The fixture is committed, so a change to either
implementation that breaks parity fails a test rather than reaching a screen.

Instants in the fixture: the FR-003 example, midnight, noon, an AM and a PM value, month/year/leap
boundaries, a UTC-stored value, positive and negative offsets, a named zone, both DST transitions, a
site on `gmt_offset`, a Unix integer, ISO with `Z`, ISO with an offset, and the two invalid cases.

### 5. Converting the surfaces

Server-rendered screens call the presenter and echo `Formatted`. React screens import `CorexTime`.
The three PHP sites that build their own format from `get_option` (`OverviewRenderer.php:255`,
`OperationsSecurityScreen.php:222` and `:332`) lose that construction, including the `format('c')`
fallback that could emit raw ISO.

Twelve JS sites change: six `toLocaleString()` calls and six raw-ISO renders, listed in the spec.
`NotificationItem` additionally gains a non-hover route to its exact value (FR-013) — a visually
present secondary line rather than a `title` attribute, since `title` is neither touch-reachable nor
reliably announced.

**Sorting needs verification, not change.** No client-side date sort exists — the Submissions inbox
and the Data explorer both sort server-side on the stored column, so FR-016 is already satisfied by
construction. The task is a test that proves it across a month and a year boundary, plus a check
that missing values group at one end.

### 6. What is deliberately not touched

`gmdate( DATE_ATOM )` in persistence and REST responses; CSV export columns; log files; debug
payloads. One inconsistency found during the audit is recorded rather than fixed here:
`OperationsSecurityScreen.php:406` emits `wp_date( DATE_ATOM, … )` — a valid ISO 8601 string built
in site-local time where everything else uses UTC `gmdate`. It is a transport question, not a
presentation one, and changing a wire format inside a presentation spec is how scope creeps.

## Complexity Tracking

**No violations.** The one entry planned here turned out not to be one.

The static accessor for view code was written up as an exception to constitution IV (everything
injected). It is not: the framework already sanctions exactly this under **FR-008a**, and already
ships two of them — `Corex\Support\Facades\Config` and `Corex\Support\Facades\Corex`, both of which
resolve from the container rather than constructing. `Facades\AdminDate` follows that established
boundary verbatim, so there is one shared instance, it is still replaceable in a test, and screens
with a constructor still inject `AdminDateTime` directly.

Recorded rather than deleted, because the plan claimed a violation before checking whether the
convention already existed — and the answer was in `src/Support/Facades/` the whole time.
