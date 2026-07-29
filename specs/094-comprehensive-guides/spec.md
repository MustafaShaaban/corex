# Feature Specification: A guide for somebody who knows nothing about this

**Feature Branch**: `spec/094-comprehensive-guides`

**Created**: 2026-07-29

**Status**: Draft

**Input**: Owner direction — *"it should be a comprehensive user guide for the user who doesn't know
anything about the framework and the addons and the dashboard how it works and what is the flows,
what is settings do and it's fields. every single detail and input should be descripted."*

## Why this spec exists

Spec 084 built the guide registry and shipped four guides — 6 topics, 13 steps — covering four of
about fourteen admin screens. They are good guides. They also all assume the reader already knows
which screen they want.

Nothing documented Settings at all: **nine sections, 42 fields**, explained only by help text written
for somebody who already knows what the field is for. Nothing documented Add-ons, the Setup Wizard,
the Forms builder, or the Overview screen. Somebody handed a finished site met a menu of thirteen
unfamiliar words with no way in.

### The three things a screen cannot say about itself

Settings is where "every input described" earns its keep, and the useful content is not a restatement
of each label:

- **A password field that looks empty may not be.** Secrets are write-only; submitting a blank one
  keeps the stored value. Somebody assuming blank means unset either re-pastes the key (harmless) or
  concludes it never saved (not).
- **Six captcha fields appear and disappear** with the chosen driver. A reader hunting for "Site key"
  on Honeypot concludes the screen is broken.
- **Advanced stores nothing.** It reads the server back to you.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Somebody who has never seen CoreX finds their way (Priority: P1)

**Independent Test**: open the Guides screen as a new user and answer, from the guides alone, what
each menu item is for and what to do first.

### User Story 2 — Every settings field is explained (Priority: P1)

**Independent Test**: for each of the 42 fields, find it named in the Settings guide. Asserted, not
sampled — see FR-004.

### User Story 3 — A contributor is not shown instructions for screens they cannot open (Priority: P2)

**Independent Test**: as an editor, the guides whose abilities they hold render and the rest are
absent.

### Edge Cases

- **The reader with the fewest permissions needs orientation most.** The orientation guide is gated
  on `read`, not on a CoreX ability — refusing it to somebody who cannot manage settings would be
  refusing help to exactly the person asking.
- **A field added later.** A hand-written guide decays silently: the guide renders, the field saves,
  and nothing says they disagree. FR-004 is the defence.

## Requirements *(mandatory)*

- **FR-001**: An orientation guide MUST explain what CoreX is, what each menu item does, and what to
  do first, gated no higher than `read`.
- **FR-002**: Every settings section MUST be documented, and every field named as it appears on
  screen.
- **FR-003**: The guide MUST explain write-only secrets, conditional captcha fields, and that
  Advanced stores nothing.
- **FR-004**: A test MUST fail when a settings field is undocumented, matching on the **label** — a
  reader looks for "Company name", not `brand.company_name`.
- **FR-005**: Each guide MUST name the ability its screen enforces.
- **FR-006**: No new registry API. The seam spec 084 built is used as-is.

## Success Criteria *(mandatory)*

- **SC-001**: 10 guides, 23 topics, covering orientation, Settings, Overview, Add-ons, Forms & Flows
  and the Setup Wizard in addition to the original four.
- **SC-002**: All 42 settings fields named; the coverage test passes and fails when one is removed.
- **SC-003**: The browser suite still passes, including the gating assertion.

## Out of scope, and honestly so

- **The specialist screens** — Data Models, Access & Abilities, Operations & Security, Blog Pro,
  Insights, Notifications. Six more guides, and the plan named this seam in advance: orientation plus
  the everyday screens first. They are the screens somebody is *sent to* when they need them, not
  ones they browse; shipping the everyday path complete beats shipping fourteen thin guides.
- **Screenshots.** Two exist for what is now 23 topics. `capture-guide-screenshots.mjs` exits
  non-zero for any id it cannot capture, so a half-finished set breaks the script for everybody.
  Guides render fine without images — a missing file is omitted, never shown broken.
