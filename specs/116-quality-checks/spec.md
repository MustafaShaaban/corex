# Feature Specification: Quality checks on every release

**Feature Branch**: `claude/spec-116-quality-checks-a779c0`

**Created**: 2026-10-21

**Status**: Draft

**Input**: The owner approved, on 2026-10-20, the quality stories of the CoreX revamp. This spec is release R1.1. It
carries eight of them as its acceptance criteria: US-NFR-01, US-NFR-03, US-NFR-04, US-NFR-05, US-NFR-06, US-NFR-07,
US-NFR-08 and US-NFR-09. The stories, their test cases and the verification behind them are kept in the owner's private
planning workspace. The owner delegated the open choices to the orchestrating session on 2026-10-21. Where this spec
takes one, it takes the workspace's recommended answer and records it in DECISIONS as "decided under the owner's
2026-10-21 mandate".

## Why this spec exists

The definition of done every revamp story must meet asks for dark and light, both directions, phone width, WCAG 2.2 AA,
four screen states, translatable words, a performance budget, a check against the client sites and a development site
free of test leftovers. Today almost none of it is checked on every pull request:

- the right-to-left check opens 6 of 16 admin screens, in light, at 375px only;
- nothing renders an admin screen in dark;
- there is no general accessibility scan and no target-size check;
- no screen's empty or failed state is forced;
- PHP strings are not linted for translation;
- the product completion audit does not run in CI;
- the front end has no performance budget;
- nothing merges a release into a client repository before the tag;
- the browser suite leaves its submissions on the developer's site.

This release ships **no product change**. It builds the checks, so every later release is measured the same way
(decision NFR-D1). A defect a check finds today is not fixed here. It is recorded in a dated known-failures list, with
the story that will fix it, and the check fails only on a failure not on that list.

## Clarifications

### Session 2026-10-21 (decided under the owner's 2026-10-21 mandate)

- Q: Which accessibility scanner (NFR-D2)? → A: **axe-core through `@axe-core/playwright`**, as a development
  dependency only, never in a shipped package. axe's `target-size` rule is WCAG 2.2's 2.5.8 and applies the spacing
  exception. CoreX adds what axe does not do: the keyboard walk, the 44px phone controls and the reduced-motion check.
- Q: When do the checks run, and against what site? → A: **In a new CI job on every pull request**, on a WordPress of
  its own that CI provisions and seeds, with the Company kit applied at Full. It runs beside the existing browser job,
  so it does not lengthen the run, and other specs cannot leave data that changes what it measures.
- Q: How is a known failure set aside? → A: **One committed list per kind of check.** Each entry names the screen, the
  check, the rule, a signature of the element that does not change with row counts, the date and the story that fixes
  it. A failure that is not on the list fails the check. An entry that no longer matches is reported in the job's
  output and does not fail the build: browser results can change with code a pull request did not touch, and the list
  is shrunk by the pull request that fixes the screen. The static checks (admin contrast, PHP translation lint) fail on
  a stale entry, because only the file they read can make it stale.
- Q: The client repositories are private, and CoreX's CI cannot read them. How is a release candidate merged into each
  one? → A: **`scripts/client-merge-check.mjs` merges a candidate into a throwaway clone of a client repository**,
  reports conflicts against the client's recorded local edits and runs the client's suites. CI proves it on every pull
  request against a client site it generates. Before a tag, it is run against Muva and Perego by the person cutting the
  release, and its report goes into the release record. A token-backed run in CI can come later.
- Q: Some acceptance criteria need a person, not a machine: the screen-reader pass with NVDA, the review of the matrix
  screenshots, the release notes printing the known failures. → A: **They become steps of the release procedure in
  `CONTRIBUTING.md`, with what to record.** The machine half (screenshots kept, the list printed by a script) is built
  here.
- Q: The stories ask that each screen's spec carry a state table. Fifteen screens have specs written before the rule.
  → A: **One committed state inventory, `tests/e2e/quality/screen-states.json`, holds the table for every screen.** A
  new screen without an entry fails the check. A screen's own spec may point at its entry.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Every screen is checked in dark and light, left to right and right to left (US-NFR-01, Priority: P1)

A site owner who reads the admin in Arabic with the dark appearance gets the screens somebody looked at. Every CoreX
admin screen, and every theme template with the Company kit applied, is opened in eight cells on every pull request.
The cells are 375 and 1280px, light and dark, left to right and right to left. A failure names the screen, the cell and
the element.

**Independent Test**: run the screen matrix on CI's WordPress. Then run it on a branch that adds 2px of sideways
overflow to one screen: the build fails and names that screen, the width and the direction.

**Acceptance Scenarios**:

1. **Given** this branch, **When** CI runs the matrix, **Then** every admin screen and tab that WordPress's menu lists
   under CoreX, and every theme template, is opened in eight cells and passes, apart from the known failures.
2. **Given** a screen that overflows by 2px in right to left at 375px, **When** the matrix runs, **Then** the cell
   fails and names the screen, 375, rtl and the width.
3. **Given** the dark admin's muted text set to `#4a4f5c`, **When** the unit tests run, **Then** the admin contrast
   test fails and names the pair, before any browser opens.
4. **Given** a newly registered CoreX admin page that the check does not mention, **When** the matrix runs, **Then**
   the page appears in the report with its eight cells.
5. **Given** a screen that answers with an error page in one cell, **When** the matrix runs, **Then** that cell fails.
   It is not skipped.
6. **Given** the matrix has run, **Then** four screenshots per screen (375 rtl dark, 375 ltr light, 1280 rtl light,
   1280 ltr dark) are kept in the job's artifacts for a person to review.

### User Story 2 - Every screen meets WCAG 2.2 AA, checked on every release (US-NFR-03, Priority: P1)

A keyboard or screen-reader user can use every screen, not only the ones somebody tested by hand.

**Independent Test**: remove the label from the inbox search field on a branch: the scan fails and names the field and
the rule.

**Acceptance Scenarios**:

1. **Given** this branch, **When** the accessibility scan runs over every cell of the matrix, **Then** it passes or
   lists only known failures. The scan fails on any WCAG 2.0, 2.1 or 2.2 A or AA rule axe can detect.
2. **Given** a 16 by 16px icon button with no spacing around it, **When** the scan runs, **Then** it fails on
   `target-size` and names the button.
3. **Given** any screen, **When** the keyboard walk tabs through it, **Then** each stop is visible and shows a focus
   indicator with at least 3:1 contrast against what surrounds it. Escape closes what an expanding control opened and
   returns focus to that control.
4. **Given** the public contact form, **When** it is submitted with two errors, **Then** focus moves to an error
   summary whose links move focus to each field.
5. **Given** reduced motion is requested, **When** each screen is opened, **Then** no animation runs except an opacity
   change.
6. **Given** a release candidate, **When** the release record is read, **Then** it records a screen-reader pass (NVDA
   with Chrome) for each screen the release changed. This is a release step, not an automated check.

### User Story 3 - Every screen works on a phone (US-NFR-04, Priority: P1)

A site owner on a phone can read every table and press every primary control.

**Acceptance Scenarios**:

1. **Given** 375px, **When** the matrix runs, **Then** no table or list is wider than the viewport, and a failure names
   the table and its width.
2. **Given** 375px, **When** the matrix measures the primary touch controls (row actions, pagination, menu and rail
   toggles, dialog close, form submit), **Then** each is at least 44 by 44px, and a failure names it.
3. **Given** 375px, **When** a screen opens, **Then** at most one CoreX rail control is visible before the screen's
   heading.
4. **Given** 375px with text at 200%, **When** each screen opens in both directions, **Then** no text is clipped and
   no text overlaps other text.

### User Story 4 - Every screen says what is happening when it is empty, waiting, failing or refused (US-NFR-05, Priority: P2)

A site owner never stares at a blank box, a raw error code or a spinner that never ends.

**Acceptance Scenarios**:

1. **Given** the state inventory, **When** the state check lists the screens WordPress's menu registers, **Then** a
   screen with no entry fails, by name.
2. **Given** each screen, **When** each of its data requests is held back, **Then** nothing says "nothing here" before
   the answer arrives, and something visible says it is loading.
3. **Given** each screen, **When** each of its data requests answers 500, **Then** the screen says so in words, shows
   no raw error code and no endless loading indicator.
4. **Given** each screen, **When** each of its list requests answers an empty list, **Then** the screen shows an empty
   state, not a blank area.
5. **Given** a subscriber, **When** they open each CoreX screen's address, **Then** they get the CoreX access-denied
   page, not WordPress's white box.
6. **Given** a settings form whose save answers 500, **When** a value is typed and saved, **Then** an error says the
   save failed and the typed value is still in the field.

### User Story 5 - Screens speak to people, and every word can be translated (US-NFR-06, Priority: P1)

A site owner who is not a developer never reads an internal key, and every word can be translated.

**Acceptance Scenarios**:

1. **Given** this branch, **When** the raw-key scan reads every cell's visible text, **Then** it fails on snake_case
   words, dotted event keys, a key followed by an empty value and machine timestamps, except for known failures and
   text shown on purpose as code (`code`, `kbd`, `samp`, `pre`).
2. **Given** a table cell printing `corex_owner_key`, **When** the scan runs, **Then** it fails and names the screen
   and the text.
3. **Given** a PHP string echoed without a translation function, **When** the PHP translation lint runs, **Then** it
   fails with the file and line. The lint also fails a translation call with no text domain or a domain other than
   `corex`.
4. **Given** the pseudo-locale run, **When** every screen is opened, **Then** every CoreX string carries the
   pseudo-locale marks, and visible CoreX text without them fails, naming the text and the screen.
5. **Given** any pull request, **When** its CI artifacts are read, **Then** a freshly generated `corex.pot` is among
   them. A published release has it attached.
6. **Given** the word "planned" added to a shipped admin label, **When** CI runs the product completion audit, **Then**
   it fails and names the file and line.

### User Story 6 - Pages stay fast and still, and the admin stays calm (US-NFR-07, Priority: P2)

A visitor on a phone gets pages that do not jump and do not fetch what they will never see.

**Acceptance Scenarios**:

1. **Given** CI's WordPress with the Company kit at Full, **When** the front-end budget runs at 375 and 1280px,
   **Then** every page has cumulative layout shift at or under 0.05, every image has a width and height or an aspect
   ratio, images below the first screen are lazy and the first screen's main image is not.
2. **Given** a hero block printing an image without width and height, **When** the budget runs, **Then** it fails and
   names the page and the image.
3. **Given** 60KB added to a block's front-end script, **When** the budget runs, **Then** the pages using that block
   fail their byte budget and a page without it is unchanged.
4. **Given** a page without a block, **When** it loads, **Then** none of that block's script or style loads.
5. **Given** each admin screen at rest, **When** its running animations are read, **Then** none repeats forever except
   a loading indicator while a request is open. With reduced motion, none moves but by opacity.
6. **Given** this branch, **When** the performance suite runs, **Then** the admin read and form acceptance budgets
   still pass.

### User Story 7 - No release breaks a running client site (US-NFR-08, Priority: P1)

The owner of a client site knows what a release changes for them, and the release has been tried on their code.

**Acceptance Scenarios**:

1. **Given** a changelog Client impact section that names no client site, **When** the changelog test runs, **Then**
   it fails, naming the release section. Every section from the next release on names Muva, Perego and Mizzey, or says
   that no client site is affected.
2. **Given** a release candidate and a client repository, **When** the merge check runs, **Then** it merges the
   candidate into a throwaway copy of the repository, runs the client's suites and writes a report for the release
   record.
3. **Given** a candidate that edits a file the client records as a local edit, **When** the merge check runs, **Then**
   the conflict is listed in the report as a recorded local edit, and the check exits non-zero.
4. **Given** a branch that changes a file under `sites/`, **When** the repository ownership test runs, **Then** it
   fails. This is the existing test, which stays.
5. **Given** any pull request, **When** CI runs, **Then** the merge check is proved against a generated client site.

### User Story 8 - Reviews of the development site see the product, not test leftovers (US-NFR-09, Priority: P2)

Somebody reviewing CoreX on its development site sees what a person made.

**Acceptance Scenarios**:

1. **Given** a scratch site, **When** the browser suite runs against it, **Then** the submissions it made are gone at
   the end, and a final check compares the counts before and after.
2. **Given** a run stopped halfway, **When** the suite runs again, **Then** its setup removes what the stopped run
   left. Every record a browser test makes carries a marker.
3. **Given** a development site with residue, **When** the clean-up script runs, **Then** it lists what it will remove,
   asks, and removes it. Without `--yes` and an answer it removes nothing.
4. **Given** a site whose operations mode or environment type is production, **When** the clean-up runs, **Then** it
   refuses and says why.
5. **Given** the fixture plug-ins are installed, **When** Guides is opened, **Then** the fixture guide says "Test
   fixture".
6. **Given** `COREX_BASE_URL` pointing at a site in production mode, **When** the browser suite starts, **Then** it
   prints the address and refuses to run.
7. **Given** a test that creates a submission and does not remove it or mark it, **When** the suite ends, **Then** the
   before-and-after check fails and names the record.

### Edge Cases

- A screen that WordPress lists but does not let the admin open (an add-on that is off): the cell fails with what the
  page said. It is not skipped.
- A screen whose content depends on how many rows exist (the inbox): signatures leave out digits and row positions, so
  ten checkboxes are one finding, not ten.
- The matrix runs in a client repository's CI: the provisioning action links only `plugins/` and `addons/`, so the
  screens are the framework's and the same known failures apply.
- A local run against a developer's site with residue: findings may differ from CI's. The check prints the site it ran
  against. CI's run is the one the list is kept against.

## Requirements *(mandatory)*

### Functional Requirements

**Screen matrix (US-NFR-01, US-NFR-04)**

- **FR-001**: The list of admin screens MUST be read at run time from WordPress's admin menu (every `admin.php?page=`
  link under the CoreX menu), with each screen's tabs read from its own tab links. The front-end pages MUST be built
  from the theme's `templates/` folder through a committed map from template to address. A template the map does not
  cover MUST fail the check.
- **FR-002**: Each screen MUST be opened in eight cells: 375 and 1280px, light and dark (the browser's colour scheme,
  the admin's `system` appearance), left to right and right to left (`dir` on the document).
- **FR-003**: In every cell the page MUST answer below 400, MUST render the CoreX shell (admin) or the theme's
  document (front end), and MUST NOT scroll sideways.
- **FR-004**: At 375px no `table` or element with role `table`, `grid` or `list` MAY be wider than the viewport. At
  most one CoreX rail control MAY be visible before the screen's heading. Primary touch controls MUST be at least 44 by
  44px.
- **FR-005**: The matrix MUST keep four screenshots per screen as a CI artifact.

**Accessibility (US-NFR-03)**

- **FR-006**: axe-core MUST scan every cell with the tags `wcag2a`, `wcag2aa`, `wcag21a`, `wcag21aa` and `wcag22aa`,
  which include colour contrast and target size.
- **FR-007**: A keyboard walk on every screen (1280px, left to right, light and dark) MUST tab through up to 200 stops.
  Each stop MUST be visible and MUST show a focus indicator (outline or box shadow) with at least 3:1 contrast against
  the background behind the control. For each control with `aria-expanded="false"`, the walk MUST open it, press
  Escape and require that it closed and focus returned to it.
- **FR-008**: The public contact form MUST give every field a programmatic label and, on a failed submit, MUST move
  focus to an error summary that links to each field in error.
- **FR-009**: With reduced motion requested, no animation other than one of `opacity` MAY run on any screen.
- **FR-010**: `tests/Unit/Theme/ContrastMatrixTest.php` MUST also check the admin token set, dark and light (the
  system and the pinned light block), from `corex-admin-tokens.css`. It MUST fail when the system and pinned light
  blocks disagree.

**States (US-NFR-05)**

- **FR-011**: `tests/e2e/quality/screen-states.json` MUST hold, for every screen, its panels and what each shows when
  empty, loading, failed and refused. A screen the menu lists without an entry MUST fail.
- **FR-012**: For each screen, every `GET` to `/wp-json/corex/` that the screen makes on load MUST in turn be held
  back, answered 500 and answered with an empty list. The checks of US-NFR-05 scenarios 2 to 4 MUST hold.
- **FR-013**: A subscriber opening each screen's address MUST get the CoreX access-denied page.
- **FR-014**: The Settings screen's save, answered 500, MUST keep the typed value and say the save failed.

**Words (US-NFR-06)**

- **FR-015**: The raw-key scan MUST read the visible text of every matrix cell, outside `code`, `kbd`, `samp` and `pre`.
- **FR-016**: A pseudo-locale fixture MUST be able to mark every `corex` string, PHP and JavaScript, for a request that
  carries its cookie. Other requests are left alone. The pseudo-locale run MUST fail on visible text inside the CoreX
  shell that has letters and no mark, except text from the site's data (the seeded records, the site title, user
  names) and known failures.
- **FR-017**: `scripts/i18n-lint.php` MUST fail on PHP in `plugins/`, `addons/` and `packages/` that outputs a literal
  with words through `echo`, `print`, `printf` or an `esc_html`/`esc_attr` call without translating it. It MUST also
  fail on a translation call whose text domain is missing or is not `corex`. Existing hits are a committed baseline
  keyed by file and text, not line.
- **FR-018**: CI MUST generate `corex.pot` with `wp i18n make-pot` on every pull request and upload it. A published
  GitHub release MUST get it attached.
- **FR-019**: `npm run audit:completion` MUST run the product completion audit in CI and fail on any finding.

**Performance (US-NFR-07)**

- **FR-020**: The front-end budget MUST load every Company kit page and template address at 375 and 1280px and check
  layout shift (at most 0.05), image dimensions, lazy loading below the first screen and not on the first screen's main
  image.
- **FR-021**: It MUST record CoreX's script and style bytes per page (files under `/wp-content/plugins/corex-*`, the
  addons and the theme) against `tests/e2e/quality/byte-budget.json`, and fail a page more than 10% over its budget.
  Raising a budget is a recorded decision.
- **FR-022**: A CoreX block's built script or style MUST NOT load on a page that does not contain that block.
- **FR-023**: No admin screen at rest MAY run an animation with infinite iterations outside an element that is busy
  (`aria-busy="true"` or a loading state).
- **FR-024**: `tests/Performance/` MUST keep running in CI.

**Client sites (US-NFR-08)**

- **FR-025**: The changelog test MUST require each Client impact section, from the release after 0.47.0 and under
  `[Unreleased]` once it has content, to name Muva, Perego and Mizzey, or to say "No client site is affected".
- **FR-026**: `scripts/client-merge-check.mjs --client <path-or-url> --candidate <ref>` MUST clone the client
  repository into a temporary folder and merge the candidate. It MUST report every conflict, and mark the ones in a
  file the client's `corex-baseline.json` records as a local edit. It MUST then run the client's suites and write a
  Markdown report. It exits non-zero on a conflict or a failing suite. It MUST NOT write to the repository it was given.
- **FR-027**: CI MUST run the merge check on every pull request against a client site generated from the base branch,
  with the pull request's head as the candidate.

**Test residue (US-NFR-09)**

- **FR-028**: The browser suite's setup MUST print the site it will write to. It MUST refuse a site whose CoreX
  operations mode is declared `production`, or, with no mode declared, whose `WP_ENVIRONMENT_TYPE` is set to
  `production`. WordPress answers `production` for a site that sets nothing, which is every fresh install and CI's, so
  the default alone is not a refusal. There is no override.
- **FR-029**: The suite's setup and teardown MUST remove the records earlier and current runs made, recognised by
  their marker: submissions to flows whose slug carries `e2e`, and submissions whose attribution says `playwright`.
  The teardown MUST then count submissions. If there are more than setup counted, it MUST fail and name each record
  left.
- **FR-030**: `seedSubmission()` MUST stop leaving an unmarked submission. What it submits carries the marker.
- **FR-031**: `scripts/clean-test-residue.php`, run with `wp eval-file`, MUST list what it would remove, ask, and
  remove only with `--yes` or a typed answer. It MUST refuse production as FR-028 does.
- **FR-032**: The fixture plug-ins MUST say "Test fixture" on what a person sees.

### Key Entities

- **Known failure**: check, screen (or file), rule, signature, `since` date, story that fixes it, note.
- **Screen**: slug, title, tab, as WordPress's menu lists it.
- **Cell**: screen, width, appearance, direction.
- **Finding**: check, cell, rule, signature, description, the elements involved.

## Success Criteria *(mandatory)*

- **SC-001**: Every CoreX admin screen and theme template is opened in eight cells on every pull request, and the new
  job ends within the time the existing browser job takes (about 8 minutes).
- **SC-002**: Each check fails on its planted defect, and the run is shown in the pull request.
- **SC-003**: The known-failures lists record every failure found on `main` on 2026-10-21, each with a date and a
  story, and the release notes can print them with one command.
- **SC-004**: A browser suite run against a scratch site leaves no submission it made.

## Assumptions

- CI's WordPress is the reference. Local runs are for development; their results may differ on a site with residue.
- Arabic text proof (US-NFR-02) is stage 2 work and not in this release.
- The story builder of the private planning workspace (TC-NFR-08-05) is changed in that workspace, not here.
