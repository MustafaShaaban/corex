# Feature Specification: Tokens and type

**Feature Branch**: `spec/121-tokens-and-type`

**Created**: 2026-10-23

**Status**: Draft

**Input**: The owner approved the brand, quality and docs stories of the CoreX revamp on 2026-10-20 and 2026-10-21. This
spec is release R2.1, the first release of stage 2. It carries seven of them as its acceptance criteria: US-BRD-02,
US-BRD-03, US-BRD-04, US-BRD-09, US-BRD-08, US-NFR-02 and US-DOC-07 (added by change request CR-010). The stories,
their test cases and the verification behind them are kept in the owner's private planning workspace. The owner
delegated the open choices to the orchestrating session on 2026-10-21. Where this spec takes one, it takes the
workspace's recommended answer and records it in DECISIONS as "decided under the owner's 2026-10-21 mandate"
(DECISIONS #460 to #468).

## Why this spec exists

CoreX's brand lives in four hand-kept copies that disagree, and its theme does not draw what it writes:

- the theme (navy and cyan), the admin tokens (brass and graphite), the email palette and the logo files are kept by
  hand, and nothing checks that they agree;
- inside the admin tokens, choosing **Light** keeps the dark shadows, overlay and login backdrop, while **System** on a
  light computer gets navy-tinted ones and a different login card;
- the theme's spacing renders WordPress's own sizes (16px where the theme says 24px), it sets no heading sizes (the
  front page's first heading is the browser's 32px), and WordPress's own font sizes and shadows are printed beside the
  theme's; radius and motion are not the design's;
- the theme registers IBM Plex Sans Arabic and never uses it: an Arabic page reads in the system font with Latin line
  spacing;
- the design shows Space Grotesk for interface labels; buttons, tabs and form labels are in the system font, and the
  four font files are shipped twice with only one copy counted;
- the retired navy and cyan logo is still in the plugin, a test pins its colours, and spec 111 names it as CoreX's
  public detection icon;
- there is no Arabic catalogue: set to Arabic, WordPress's admin turns Arabic and every CoreX screen stays English, and
  every right-to-left check so far forced the direction onto English text;
- the Foundations docs page describes an admin adapter scoped to `.wrap` with a dark override, and the navy-era tokens.

The theme's palette, shadows and logo set change in R2.2 (US-BRD-01, US-BRD-05, US-BRD-07). This release makes the
copies checkable, the scales real, the type right and the product readable in Arabic.

## Clarifications

### Session 2026-10-23 (decided under the owner's 2026-10-21 mandate)

- Q: Which values are the brand (BRD-D3)? → A: **The shipped admin set.** One contract file in the repository lists
  every brand colour (light and dark), shadow, radius and motion value, taken from `corex-admin-tokens.css` as
  shipped, and says which copy holds each one. The theme's colours and shadows are bound to it by US-BRD-01 (R2.2),
  which moves the theme to graphite and brass; its radius and motion are bound now. (#460)
- Q: Light shadows: the System block's navy tint, or the dark ones Light keeps today? → A: **Neither: the design's
  graphite.** Light and System on a light computer get one identical set, with the design's neutral shadow colour.
  (#461)
- Q: The theme has eight spacing slugs and the design nine steps. → A: **Every slug keeps its name and its written
  value; a new slug `25` adds the 12px step**, and WordPress's own sizes are turned off so the written values render.
  Font size slugs keep their names and take the design's sizes, named by the design's roles; `3xl` and `display` are
  added. Radius keys are aligned with the admin's (`xs` 3, `sm` 6, `md` 10, `lg` 16, `full`), and buttons use `sm`,
  the 6px CoreX radius. (#462)
- Q: How does the public site know a page is Arabic? → A: **By its language**, `:lang(ar)` and the other languages
  written in Arabic script, not by direction alone, and the Arabic faces declare the Arabic character range, so a
  page loads the files only when it has Arabic text to draw. (#463)
- Q: Which body face (BRD-D2)? → A: **Space Grotesk for headings and interface labels, the system font for long
  body text**, on the site, the admin and the login; emails keep the system font. The two font copies stay (the theme
  must work without the plugin) and the test counts both and requires them identical. (#464)
- Q: Which icon does spec 111's detection rule send? → A: **`corex-symbol.svg`**, the bare Core X mark, which reads at
  16 to 32px. (#465)
- Q: Who translates the Arabic catalogue, and how does it ship (NFR-D3)? → A: **CoreX ships `corex-ar.po` and
  `corex-ar.mo` in `corex-core/languages`**, translated from the template CI generates; the script translations are
  written from the catalogue when the bundles are built. The first catalogue is drafted in this release; the review
  by a native Arabic speaker the owner names is owed before R2.1 ships and is recorded in the catalogue's review file.
  (#466)
- Q: How does the Arabic proof know a string was not translated, without mistaking the site's own data for one? → A:
  **It asks the translation functions.** A test fixture records every CoreX string a page asked for that the
  catalogue did not answer; the proof fails naming the string and the screen. (#467)
- Q: The docs site has no Arabic locale yet. → A: **The Foundations page is direction-neutral now** (logical
  properties in its examples, no left or right in its words); reading it right to left in Arabic is proven when the
  Arabic docs locale lands (US-DOC-02). (#468)

## User Scenarios & Testing *(mandatory)*

### User Story 1 - The theme, the admin and the emails agree on the brand (US-BRD-02, Priority: P1)

As the owner of the CoreX brand, I want one written list of the brand's colours and a test that every copy matches it,
so that the theme, the admin, the emails and the logos can never drift apart again.

**Why this priority**: every later brand change (R2.2's palette) is safe only once the copies are checked.

**Independent Test**: change one admin token, one email colour or one theme radius; the unit suite fails naming it.

**Acceptance Scenarios**:

1. **Given** the contract, **When** one admin token value is changed so it differs, **Then** a test fails and names
   the token (TC-BRD-02-01).
2. **Given** the contract, **When** one email palette value is changed, **Then** a test fails (TC-BRD-02-02).
3. **Given** admin appearance Light, **When** a dialog and the login are opened, **Then** shadows, overlay, login card
   and backdrop are the light ones (TC-BRD-02-03); **and** with System on a light computer they are identical
   (TC-BRD-02-04).
4. **Given** the admin in Arabic, **When** every CoreX screen is opened, **Then** the Arabic face applies on each,
   including screens with the admin-screen body class (TC-BRD-02-05).
5. **Given** wp-admin, **When** the loaded stylesheets are listed, **Then** no front-end theme stylesheet is among them
   (TC-BRD-02-06).

---

### User Story 2 - The theme renders its own scales (US-BRD-03, Priority: P1)

As a designer working with the CoreX theme, I want the spacing, type, radius and motion I see on the page to be the
theme's own, matching the design, so that the design and the site measure the same.

**Independent Test**: on the front page read the spacing, font size and shadow presets and the computed heading sizes.

**Acceptance Scenarios**:

1. **Given** the front page, **When** `--wp--preset--spacing--*` is read, **Then** the theme's slugs are 4, 8, 12, 16,
   24, 32, 48, 64 and 96px, and no WordPress default spacing size is printed (TC-BRD-03-01).
2. **Given** a page with h1 to h6, **When** their computed size, weight and letter spacing are read at 1280px, **Then**
   they match the design's scale (TC-BRD-03-02).
3. **Given** the front page, **When** the font-size and shadow presets are listed, **Then** only the theme's are
   printed (TC-BRD-03-03).
4. **Given** a button and a card, **When** their radius is read and a hover transition runs, **Then** the button has
   the 6px CoreX radius and the transition takes its duration from the motion tokens (TC-BRD-03-04).
5. **Given** every template, **When** before and after screenshots are compared, **Then** every difference is listed
   in the release notes (TC-BRD-03-05).

---

### User Story 3 - Arabic text uses the Arabic typeface on the public site (US-BRD-04, Priority: P1)

As an Arabic reader, I want the site's text in IBM Plex Sans Arabic with comfortable line spacing, so that Arabic
reads as a first language, not a fallback.

**Acceptance Scenarios**:

1. **Given** site language Arabic, **When** the front page is opened, **Then** a paragraph and an h2 compute to IBM
   Plex Sans Arabic with line height 1.8 and 1.3 (TC-BRD-04-01), and the Arabic font files load (TC-BRD-04-02).
2. **Given** site language English, **When** the front page loads, **Then** the Arabic font files do not
   (TC-BRD-04-03).
3. **Given** an Arabic paragraph with an English product name and numbers, **When** it is viewed, **Then** each
   script is in its own face with no broken spacing (TC-BRD-04-04).
4. **Given** the admin in Arabic, **When** a CoreX screen's paragraph line height is read, **Then** it is 1.8
   (TC-BRD-04-05).

---

### User Story 4 - Body text uses one chosen typeface everywhere (US-BRD-09, Priority: P2)

As a reader, I want body text set the same way on the site, the admin, the login and in emails, so that CoreX reads as
one product.

**Acceptance Scenarios**:

1. **Given** the front page, an admin screen and the login, **When** a paragraph's, a button's and a heading's
   computed font is read, **Then** the paragraph is the system font and the button and heading Space Grotesk, on all
   three (TC-BRD-09-01).
2. **Given** a captured email, **When** its body font is read, **Then** it is the system font stack (TC-BRD-09-02).
3. **Given** the repository, **When** WOFF2 files are counted across the theme and the core plugin, **Then** the
   limit test counts both folders (TC-BRD-09-03).

---

### User Story 5 - The old navy and cyan logo is retired everywhere (US-BRD-08, Priority: P2)

As the CoreX brand owner, I want the retired navy and cyan logo gone from the product and its specifications, so that
it is never published as CoreX's mark again.

**Acceptance Scenarios**:

1. **Given** the repository, **When** it is searched for `corex-logo.svg`, and for `#0B1F3B` with `#00C2FF` in tests
   and specs, **Then** none is found apart from the decision record (TC-BRD-08-01).
2. **Given** spec 111's detection contract, **When** its icon line is read, **Then** it names the Core X symbol
   (TC-BRD-08-02).
3. **Given** the file removed, **When** the test suite runs, **Then** it passes (TC-BRD-08-03).

---

### User Story 6 - CoreX is proven in real Arabic, not English turned around (US-NFR-02, Priority: P1)

As an Arabic reader, I want every CoreX screen, form and email to be shown and checked in Arabic text, so that letters
join, punctuation sits on the right side and nothing falls back to a Latin font.

**Acceptance Scenarios**:

1. **Given** a scratch site in Arabic with the CoreX catalogue, **When** every CoreX admin screen is opened, **Then**
   every CoreX string is Arabic except the glossary's names, and the Arabic face is used (TC-NFR-02-01).
2. **Given** the same site, **When** the Contact page's default form is submitted empty, **Then** the error summary
   and field messages are Arabic, right to left (TC-NFR-02-02).
3. **Given** the inbox with a submission from `user@example.test` about "CoreX 0.46.1", **When** it is opened,
   **Then** the address and the version read in the right order inside Arabic text (TC-NFR-02-03).
4. **Given** the catalogue removed, **When** the proof runs, **Then** it fails and says the catalogue is missing
   (TC-NFR-02-04).
5. **Given** a new admin string with no Arabic translation, **When** the proof runs, **Then** it fails naming the
   string and its screen (TC-NFR-02-05).
6. **Given** Perego's repository with the release merged, **When** its Arabic contact form is submitted with an
   error, **Then** Perego's own wording still shows for its mapped strings (TC-NFR-02-06; Perego's session records
   it).
7. **Given** the proof screenshots, **When** an Arabic reader goes through the written checklist, **Then** the result
   is recorded and `PROJECT-STATUS.md` cites it (TC-NFR-02-07; owed to the named reviewer).

---

### User Story 7 - The Foundations page describes the tokens CoreX ships (US-DOC-07, Priority: P2)

As a developer styling a client site, I want the Foundations docs page to list the tokens CoreX actually ships, so that
I do not build on a scope or a palette that no longer exists.

**Acceptance Scenarios**:

1. **Given** the docs site, **When** Foundations is opened, **Then** the adapter is described as scoped to
   `.corex-admin`, dark by default, with the light override (TC-DOC-07-01).
2. **Given** the same page, **When** each token row is compared with theme.json and the admin token stylesheet,
   **Then** every slug and value matches (TC-DOC-07-02).
3. **Given** one token value changed without regenerating the page, **When** the docs are built or the tests run,
   **Then** the check fails naming the token (TC-DOC-07-03).
4. **Given** the page, **When** it is searched for the old navy values and names, **Then** none is found
   (TC-DOC-07-04).

### Edge Cases

- A `brand.json` overlay keeps overriding the theme only; it never reaches the admin, the login or the emails, and the
  release notes say so.
- A page in Arabic that quotes English (a product name, an address, a version) keeps the Latin text in the Latin face
  and in the right order.
- An English page that contains one Arabic word (a language switcher) loads the Arabic file for that word only; an
  English page without Arabic loads none.
- A client that maps CoreX strings through `gettext_corex` (Perego) keeps its own wording: its filter runs after the
  catalogue.
- A translation whose placeholders differ from its original would break `sprintf`: the catalogue check refuses it.
- A string added after this release has no Arabic: the proof names it, and the release step says the catalogue is
  updated before a tag.

## Requirements *(mandatory)*

### Functional Requirements

**Brand contract (US-BRD-02)**

- **FR-001**: The repository MUST hold one token contract listing every brand colour (light and dark value), shadow
  (light and dark), radius and motion value, taken from the shipped admin set, with the copy (admin custom property,
  email palette member, theme key) that holds each.
- **FR-002**: A unit test MUST fail, naming the token and the copy, when the admin tokens, the email palette or a
  bound theme value differs from the contract.
- **FR-003**: Admin appearance Light and System on a light computer MUST resolve to the same values for every token,
  including shadows, overlay, login card, login field and login backdrop; a test MUST compare the two blocks.
- **FR-004**: The right-to-left font swap MUST cover `.corex-admin`, the login body and `body.corex-admin-screen`.
- **FR-005**: The admin MUST stay scoped to its own variables; no front-end theme stylesheet is loaded in wp-admin.
- **FR-006**: The release notes MUST say that `brand.json` overrides the theme only.

**Theme scales (US-BRD-03)**

- **FR-007**: WordPress's default spacing sizes, font sizes and shadows MUST be off for the CoreX theme.
- **FR-008**: Spacing MUST render 4, 8, 12, 16, 24, 32, 48, 64 and 96px on the theme's slugs; every existing slug
  keeps its name and written value.
- **FR-009**: h1 to h6 MUST follow the design's type scale (H1 4rem, H2 3rem, H3 2.25rem, title 1.75rem, lead
  1.375rem, body 1rem) with its line heights, weight 600 and negative letter spacing; display, lead, small and
  caption sizes MUST exist as presets; large sizes scale down fluidly to a phone and reach the design's value at the
  theme's wide size.
- **FR-010**: Radius MUST be 3, 6 (the CoreX radius, used by buttons), 10, 16 and full; motion 80, 140, 200 and 320ms
  with the design's standard and emphasized easings; the admin MUST use the same motion values.
- **FR-011**: Every template MUST be captured before and after at 1280 and 375px, and every visible change listed in
  the release notes.

**Arabic face (US-BRD-04)**

- **FR-012**: On a page whose language is written in Arabic script, body text and headings MUST use IBM Plex Sans
  Arabic, with line height 1.8 for body and 1.3 for headings; Latin text keeps 1.6 and its heading line heights.
- **FR-013**: The Arabic font files MUST load only on pages that draw Arabic text.
- **FR-014**: Mixed text MUST keep each script in its own face.
- **FR-015**: The admin's and the login's right-to-left rules MUST use the same line heights.

**Typeface roles (US-BRD-09)**

- **FR-016**: Headings and interface labels (buttons, tabs, navigation, form labels) MUST use Space Grotesk on the
  site, the admin and the login; long body text MUST use the system font there and in emails.
- **FR-017**: The four-file font limit MUST be tested across every copy, and the copies MUST be identical; no font
  file is added.

**Old logo (US-BRD-08)**

- **FR-018**: `corex-logo.svg` MUST be removed; the test that pinned its colours MUST check the current lockup or go;
  the manifest and the plugin README MUST not mention it; spec 111's contract MUST name `corex-symbol.svg`.

**Arabic catalogue and proof (US-NFR-02)**

- **FR-019**: CoreX MUST ship an Arabic catalogue for the `corex` text domain covering every string on the admin
  screens, the account block, the default form and its messages, and the emails CoreX sends, with the script
  translations the admin bundles need; its review by a native Arabic speaker MUST be recorded before R2.1 ships.
- **FR-020**: A catalogue check MUST refuse a translation whose placeholders or markup differ from its original, and
  MUST report every string of the template that has no Arabic.
- **FR-021**: An Arabic proof run MUST open every CoreX admin screen, the Company kit pages, the default form with
  errors and each email preview, at 375 and 1280px, light and dark, in Arabic; it MUST fail naming the string and the
  screen for every CoreX string the catalogue did not answer, apart from names the glossary keeps in English, and MUST
  fail saying so when the catalogue is missing or not loaded.
- **FR-022**: The proof MUST keep its screenshots, and a written checklist (joined letters, punctuation side,
  numerals, line spacing, truncation) MUST say what the reader checks and where the result is recorded.

**Foundations page (US-DOC-07)**

- **FR-023**: The Foundations page MUST describe the admin adapter as shipped: scoped to `.corex-admin`, dark by
  default, with the light override and System following the computer.
- **FR-024**: Its token tables MUST be generated from theme.json and the admin token stylesheet, and a check run by
  the tests and by the docs build MUST fail naming the token when the page and the files disagree.
- **FR-025**: The page MUST hold no navy-era token or value, show light and dark values, and use only
  direction-neutral wording and logical properties.

### Key Entities

- **Token contract**: the brand's values, by role and mode, and which copy holds each.
- **Arabic catalogue**: `corex-ar.po`, its compiled `.mo`, and the script translations written from it at build.
- **Arabic proof record**: per screen and cell, the strings the catalogue did not answer and a screenshot.

## Success Criteria *(mandatory)*

- **SC-001**: Changing any one bound brand value in any copy fails the unit suite with the token's name.
- **SC-002**: Light and System-light admin screens are pixel-identical in shadows, overlay and login.
- **SC-003**: The front page's spacing, heading sizes, radius and motion read the design's values in the browser.
- **SC-004**: An Arabic page computes to the Arabic face at 1.8 / 1.3; an English page downloads no Arabic file.
- **SC-005**: With the catalogue installed, the Arabic proof finds no CoreX string left in English on any admin screen;
  with it removed, the proof fails.
- **SC-006**: The Foundations page and the shipped files agree, checked on every pull request.
- **SC-007**: No test, spec or plugin file names the retired logo or its colours, apart from the decision record.

## Assumptions

- The theme's palette, shadows, selection colours and logo set change in R2.2; this release does not move the theme to
  brass.
- The Arabic catalogue is drafted in this release and reviewed by the native Arabic speaker the owner names before R2.1
  ships (NFR-D3); the review is owed, not done.
- WordPress core's own Arabic pack is installed on the proof's site where the network allows; CoreX's proof checks
  CoreX strings only.
- Client sites: the admin and login styling, the motion values and the Arabic catalogue reach Muva and Perego (plugin
  code). The theme changes reach neither (both run standalone themes). Mizzey is not on this release.
