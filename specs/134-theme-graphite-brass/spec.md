# Feature Specification: The CoreX theme in CoreX colours

**Feature Branch**: `spec/134-theme-graphite-brass`

**Created**: 2026-11-15, under the CoreX loop (constitution v2.0.0). **Rewritten** the same day, after the owner
rejected a design that only recoloured today's layout and approved a full visitor kit instead. This file is the
whole spec: it ends in slices, and there is no separate plan or task file.

**Status**: Ready for slices 1 and 2. Slice 3 waits for its own design approval (the logo set). Nothing is built.

**Design**: the approved visitor kit, `design/handoffs/visitor-kit.md` (approved by the owner on 2026-11-15 at
Version 8 of the CoreX design system, https://claude.ai/artifact/DExY8maEuxwM6JwdZ1jw5c, private to him). The
design system is the source: a change to the look starts there and reaches the code through `theme.json`
(`AGENTS.md`, "UI, UX and front end", step 7). Every colour value this spec names is copied from the hand-over's
"Design tokens"; where the two differ, the hand-over is right and this file is corrected.

**Input**: Release R2.2 of the CoreX revamp, three stories approved under the owner's 2026-10-21 mandate: US-BRD-01
(the CoreX theme wears the CoreX brand), US-BRD-05 (the logo set is complete and reads on every background) and
US-BRD-07 (the theme's preview shows the theme). The stories, their sixteen test cases and the reading of the code
behind them are kept in the owner's private planning workspace. It builds on spec 121 (R2.1) and finishes what
spec 121 left for this release: FR-025, TC-DOC-07-04 and SC-007 there (DECISIONS #468).

## Why this spec exists

CoreX's admin, login and emails are graphite and brass. The CoreX theme is still navy and cyan, a palette the owner
rejected on 2026-11-15. He then approved a visitor kit in four layers (foundations, 30 components, 28 blocks, 13
templates) as the look CoreX's public side is built on.

A kit that size is not one release. This spec builds its **first layer**, the foundations every later release
stands on:

- the kit's colours, shadows and custom tokens in `theme.json` and its Dark variation, held to the brand contract;
- the base elements the theme itself draws: the button, the link, headings, selected text, the focus ring;
- the section styles (quiet band, dark band, brass band), and the two shipped patterns that draw a coloured
  section, moved onto them;
- the logo set, so the login shows the whole mark;
- the theme's preview image.

The 30 components, the 28 blocks and the templates are built by later specs, one per release, each from the same
hand-over. After this release the theme has the kit's colours and section styles on today's layout; it does not yet
have the kit's pages.

## Clarifications

### Session 2026-11-15 (decided by the owner, and under his mandate)

Decided by the owner on 2026-11-15: direction A (white, graphite, brass) is the look for the public theme; every
slug keeps its name; the theme's colours join the brand contract; **the design system is a component kit and comes
first** (DECISIONS #639); the kit as approved. Where the stories leave a choice open, the recommended answer was
taken under his mandate (DECISIONS #633 to #640).

- Q: Which value does each theme slug take? → A: **The hand-over's palette table.** Every value is one the brand
  contract lists, apart from `success` in light (`#287a4e`: the admin's `#2f8f5b` is 4.04:1 as text on white).
- Q: Is the main button brass or graphite? → A: **Brass, with a graphite label** (`accent` behind,
  `custom.color.onAccent` on it, `custom.color.accentHover` on hover). It is what the approved kit draws and what
  the owner's October design package drew. White on the light brass is 3.35:1, so the label is graphite in both
  modes. *Changed on 2026-11-15:* the first version of this spec kept the button graphite.
- Q: What does a `brand.json` that sets only the palette get? → A: **Its `accent` as the button, with the kit's
  graphite label.** A brand whose `accent` is dark sets `custom.color.onAccent` to a light value; the branding guide
  and the release notes say so. `primary` keeps driving headings.
- Q: How is a dark section drawn? → A: **As the dark band**: a section style that is dark in both themes and
  re-points the palette tokens to the `band-*` tokens for everything inside it (DECISIONS #640). Filling a section
  with `primary` is retired: `primary` is light in the Dark variation, so such a section turned light there.
- Q: And a brass section? → A: **The brass band**: `accent` behind, graphite text, a graphite button. Once a page.
- Q: What happens to the band under a client's theme that has no `band-*` tokens? → A: **It draws as today.** The
  section style's rules name the band tokens with the palette slugs as fallbacks (`band` falls back to `primary`,
  `band-ink` to `surface`, and so on), so a band under a client theme is today's `primary` section.
- Q: The dark variation: chosen by the site owner, or automatic? → A: **Chosen, as today.**
- Q: The "editorial" variation? → A: **Removed** (DECISIONS #636).
- Q: Do the shadows take the contract's geometry or only its colours? → A: **The contract's three shadows whole.**
- Q: Where do the favicon files live? → A: **In the logo folder and in the theme**, with a test that holds the
  copies equal (DECISIONS #637).
- Q: How does the login show the lockup? → A: **A fixed-colour lockup for its card**, by admin appearance.
- Q: How does a test know the preview is older than the palette? → A: **A fingerprint written with the capture**
  (DECISIONS #638).
- Q: Does this release restyle the form block's fields and Send button? → A: **No.** The kit designs them; they are
  built with the form components. This release changes only the colours they already read.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - The CoreX theme wears the CoreX brand (US-BRD-01, Priority: P1)

As someone who installs the CoreX theme, I want it to look like CoreX (white and graphite surfaces, a brass accent,
light and dark), so that the product and the theme read as one.

**Why this priority**: it is the release, and every later release of the kit stands on it.

**Independent Test**: on a scratch site running the CoreX theme with the Company kit applied, read the computed
colours and contrast on the front page, a content page and the Contact page, in the default style and the Dark
variation, in English and Arabic.

**Acceptance Scenarios**:

1. **Given** the default style, **When** `--wp--preset--color--accent`, `surface` and `ink` are read on the front
   page, **Then** they are `#ad8643`, `#ffffff` and `#14151a` (TC-BRD-01-01).
2. **Given** the Dark variation, **When** the same are read, **Then** they are `#c9a25e`, `#16181d` and `#f4f5f7`
   (TC-BRD-01-02).
3. **Given** every template in both modes, **When** the contrast check runs, **Then** every documented text and
   control pair passes AA (TC-BRD-01-03).
4. **Given** the hero, the call-to-action band and any section with a section style, **When** each heading's and
   button's colours are read against the section, in English and Arabic, in both modes, **Then** none is the colour
   of its background and each pair passes (TC-BRD-01-04).
5. **Given** a paragraph, **When** part of it is selected, **Then** the selection is drawn in the selection tokens
   (TC-BRD-01-05).
6. **Given** a `brand.json` that sets `primary`, **When** it is active, **Then** headings take it (TC-BRD-01-06).
7. **Given** `theme.json` and the variations, **When** they are searched for `0B1F3B`, `007A9E`, `00C2FF` and
   `rgba(11, 31, 59`, **Then** none is found (TC-BRD-01-07).
8. **Given** the hero in the Dark variation, **When** it is drawn, **Then** it is a dark band, not a light one.
9. **Given** a button, a link and a card placed inside a dark band, **When** they are drawn, **Then** each takes
   the band's colours with no rule of its own: the failure path the story names, a heading or a button the colour
   of its background, cannot be written.

### User Story 2 - The CoreX logo set is complete and reads on every background (US-BRD-05, Priority: P1)

As a site owner, and as the CoreX brand owner, I want the login to show the whole CoreX mark on its card, and the
logo set to include a stacked lockup, a single-ink version and favicons, so that the brand is never drawn
half-missing.

**Why this priority**: Muva's live sign-in shows the broken mark today.

**Independent Test**: open `/wp-login.php` with the admin appearance Dark, then Light, and sample the pixels of the
corner modules; list the logo folder against its manifest; load the front page under the CoreX theme and under
another theme and read the icon links.

**Acceptance Scenarios**:

1. **Given** no custom logo and the admin appearance Dark, **When** the login is opened, **Then** all four modules,
   the centre, the divider and the word are visible, "Core" in the neutral colour and the "x" in brass
   (TC-BRD-05-01).
2. **Given** the admin appearance Light, **When** the login is opened, **Then** the same, readable on the light
   card (TC-BRD-05-02).
3. **Given** the logo folder and its manifest, **When** they are listed, **Then** they hold a stacked lockup, a
   knockout on brass, a lockup for light and a lockup for dark backgrounds, and a favicon as SVG and at 16, 32, 180
   and 512px, each in the manifest (TC-BRD-05-03).
4. **Given** the CoreX theme and no site icon, **When** the front page loads, **Then** its head links the CoreX
   favicon (TC-BRD-05-04).
5. **Given** another theme, **When** the front page loads, **Then** CoreX adds no favicon (TC-BRD-05-05).
6. **Given** a logo set in CoreX Settings, Branding, **When** the login is opened, **Then** it shows the site's
   logo, not CoreX's (TC-BRD-05-06).
7. **Given** a site icon set in WordPress, **When** the front page loads under the CoreX theme, **Then** only the
   site's icon is linked.

### User Story 3 - The theme's preview shows the theme (US-BRD-07, Priority: P2)

As someone choosing a theme, I want the CoreX theme's preview image to show what the theme looks like when
activated, so that I am not shown a different product.

**Why this priority**: it ships after the palette, and it is a single image.

**Independent Test**: compare the card in Appearance, Themes with the activated front page; read the image's size;
change one palette value and run the tests.

**Acceptance Scenarios**:

1. **Given** Appearance, Themes, **When** the CoreX card is compared with the activated front page, **Then** they
   show the same palette, layout and content (TC-BRD-07-01).
2. **Given** the preview image, **When** it is read, **Then** it is 1200 by 900 and holds no CoreX product copy
   (TC-BRD-07-02).
3. **Given** a palette value changed without a new capture, **When** the tests run, **Then** one fails and names
   the command that takes the capture (TC-BRD-07-03).

### Edge Cases

- **A site that already chose Dark or Editorial.** WordPress stores a chosen variation's values with the site's own
  styles, so that site keeps the values it chose. Nothing is broken and nothing is rewritten. The release notes say
  that choosing Dark again takes the new colours.
- **A `brand.json`.** It replaces the palette whole and must name the thirteen roles it always had. It keeps
  working; its `accent` becomes the button's colour (see Clarifications).
- **Content already saved with the old hero or call-to-action markup** names `primary` or `accent` as its
  background and `surface` as its text. It keeps drawing: graphite with white text for the hero in light, and in the
  Dark variation the hero is light with dark text, as it is today. Only the patterns as newly inserted, and the
  front-page template (which draws them by reference), take the section styles.
- **A band under a client's theme.** It falls back to the palette slugs and draws as today's section.
- **A field on a quiet band.** `border` is 3.21:1 on white and 2.91:1 on `surface-alt` in light, as today's grey is.
  A field's own background is white, so its outline is read against white.
- **The form's shapes.** The form block sets no outline, corner or typeface on its fields and its button, so they
  are the browser's own today. This release changes their colours only.
- **System admin appearance on the login.** The lockup follows the computer, as the card does.

## Requirements *(mandatory)*

### Functional Requirements

**Tokens (US-BRD-01)**

- **FR-001**: `theme/theme.json` and `theme/styles/dark.json` MUST hold the palette of the hand-over, and every
  existing slug MUST keep its name.
- **FR-002**: Both files MUST define the hand-over's custom colour tokens under `settings.custom.color`: `onAccent`,
  `accentHover`, `accentSubtle`, the four `*Soft` tokens and the ten `band*` tokens.
- **FR-003**: The theme's three shadows MUST be the brand contract's, in light and in dark.
- **FR-004**: `design/tokens/brand-contract.json` MUST list every theme palette slug, every custom colour token and
  the three shadows with their light and dark values, and `tests/Unit/Theme/BrandContractTest.php` MUST fail, naming
  the token, the mode and the file, when `theme.json` or `dark.json` holds another value.
- **FR-005**: Every theme colour value MUST be a value the contract lists for the brand, apart from the theme
  values the contract names with a reason (one today: `success` in light).
- **FR-006**: The contrast matrix MUST hold the pairs the theme draws in both modes, including the label on the
  brass button, text on the dark band and on the brass band, selected text, and `border` on `surface`. Every pair
  for use MUST pass AA.
- **FR-007**: Where a CoreX block draws text, or a mark that carries meaning, in `accent` on a page surface, it MUST
  draw it in `accent-dark` (one place is known: the form block's required mark). Slice 1 lists the rest from the
  built stylesheets before it changes a value.
- **FR-008**: The "editorial" variation MUST be removed, with the sentence in the branding guide that names it.
- **FR-009**: No shipped file, test, fixture or docs page MUST name the navy-era values, apart from `DECISIONS.md`,
  `CHANGELOG.md` and earlier specs. Example copy that showed a navy value MUST show a neutral one, in English and
  in the Arabic catalogue.
- **FR-010**: The Foundations page's theme tables and spec 057's generated inventories MUST be regenerated, which
  completes spec 121's FR-025, TC-DOC-07-04 and SC-007.

**Base elements and section styles (US-BRD-01)**

- **FR-011**: The theme's button (`elements.button`) MUST be `accent` behind with `custom.color.onAccent` on it,
  `custom.color.accentHover` on hover, the kit's corner and height, and a disabled state. The existing block styles
  (secondary, ghost) MUST take the kit's look. The other button styles of the kit are a later release.
- **FR-012**: Links MUST be `accent-dark`, underlined in running text, turning `primary` on hover. The focus ring
  MUST be 2px `accent`, 2px away. Selected text MUST be drawn with `selection` behind and `selection-text` on it.
- **FR-013**: The theme MUST register three section styles for the Group block: the quiet band, the dark band and
  the brass band. The dark band MUST re-point the palette tokens to the `band-*` tokens for its descendants; the
  brass band MUST give its descendants graphite text and a graphite button.
- **FR-014**: A heading with no colour of its own inside a section MUST take that section's text colour.
- **FR-015**: The hero pattern MUST use the dark band and the call-to-action pattern the brass band, and neither
  may name a text colour for a heading or a button. Under a theme with no `band-*` tokens each MUST draw as it does
  today.
- **FR-016**: `HeroContrastTest` MUST keep its thresholds (heading 4.5:1, button against its section 3:1, label
  4.5:1) and pass for both patterns in both palettes.
- **FR-017**: `DECISIONS #41` MUST be superseded for the theme by a recorded decision.

**Logo set (US-BRD-05)**

- **FR-018**: The lockup MUST draw "Core" in the neutral colour and only the terminal "x" in brass.
- **FR-019**: The logo set MUST gain a lockup with fixed colours for dark backgrounds, one for light backgrounds, a
  stacked lockup in both, and a single-ink knockout on a brass tile.
- **FR-020**: The login MUST draw the fixed-colour lockup that suits its card: dark when the admin appearance is
  Dark, light when Light, by the computer's setting when System. A logo set in CoreX Settings MUST still replace it.
- **FR-021**: A favicon set MUST exist as SVG and as PNG at 16, 32, 180 and 512px, written by the logo generator.
  The raster sizes are an explicit exception to the "no raster set" rule.
- **FR-022**: The CoreX theme MUST link the CoreX favicon when the site has no site icon, from files inside the
  theme, and MUST link nothing when a site icon is set. No plugin may add a favicon under another theme.
- **FR-023**: `corex-mark.svg` MUST be removed and its generator MUST stop writing it.
- **FR-024**: The logo manifest MUST list every file with its use, its colours and its hash, and the logo contract
  test MUST fail when a file and the manifest disagree, or when the theme's favicon copies differ from the logo
  folder's.

**Theme preview (US-BRD-07)**

- **FR-025**: `theme/screenshot.png` MUST be a 1200 by 900 capture of the CoreX theme's front page in the default
  style with the Company kit applied, with neutral sample content and no CoreX product copy.
- **FR-026**: One documented command MUST take the capture on a scratch site and write, beside the image, a
  fingerprint of the palettes, custom colours and shadows of `theme.json` and `dark.json`.
- **FR-027**: A test MUST fail, naming that command, when the fingerprint differs from the files.

**Release**

- **FR-028**: The release notes MUST list each visible change with before and after captures of every template in
  light and dark, and MUST state the effect on Muva and Perego.

### Key Entities

- **Theme palette and custom tokens**: the colour values of `theme.json` and `dark.json`, bound to the contract.
- **Section style**: a named look of the Group block: quiet band, dark band, brass band.
- **Logo set**: the files of `plugins/corex-config/assets/brand/`, their manifest and their generator.
- **Preview fingerprint**: a hash of the colour values, written when the preview is captured.

## Success Criteria *(mandatory)*

- **SC-001**: On the front page, a content page and the Contact page, in both modes and both directions, at 1280px
  and 375px, every colour read in the browser is the hand-over's, and `npm run test:quality` passes.
- **SC-002**: Changing one theme colour, custom colour or shadow in `theme.json` or `dark.json` fails the unit
  suite with the token's name.
- **SC-003**: The contrast matrix and the hero contrast test pass in both palettes with their thresholds unchanged.
- **SC-004**: A search of the shipped files, tests, fixtures and docs pages for the navy-era values finds none.
- **SC-005**: The front-page hero is a dark band in both modes, measured, and its button is brass with a graphite
  label in both.
- **SC-006**: On the login, in Dark and in Light, a pixel sampled inside each of the four corner modules differs
  from the card behind it by at least 3:1.
- **SC-007**: The CoreX theme with no site icon links a CoreX favicon; another theme links none.
- **SC-008**: The preview is 1200 by 900, and a colour change without a new capture fails a test.
- **SC-009**: Muva's and Perego's own suites pass on a copy of each repository with this release merged in
  (US-NFR-08's check), and a hero and a call-to-action pattern inserted under each client's theme draw as before.

## What is measured

| What | Where | How |
|---|---|---|
| Token values per mode | front page, content page, Contact | computed custom properties in Chromium |
| Text and control contrast | every template, both modes | `ContrastMatrixTest`, `HeroContrastTest`, the quality suite's accessibility run |
| Bands | hero, call-to-action, a hand-made dark band holding a button, a link and a card | computed colours against the band, English and Arabic, both modes |
| Button, link, focus, selection | a content page | computed styles at rest, on hover, on focus, selected |
| Layout unchanged | every template, 1280px and 375px | before and after captures; block positions and sizes equal |
| Client themes | a hero and a call-to-action under Muva's and Perego's themes | captures before and after, equal |
| Login mark | `/wp-login.php`, Dark, Light, System | pixel samples of the four modules, the centre, the divider, the word |
| Icon links | front page under the CoreX theme and another | the head's icon links |
| Preview | `theme/screenshot.png` | size, fingerprint, comparison with the front page |

## Client impact

- **Muva**: its sign-in shows the whole CoreX mark after the update, with "Core" neutral and the "x" brass. The
  theme does not reach it: Muva runs its own theme. The inactive CoreX theme's card image changes in Appearance,
  Themes. `corex-mark.svg` disappears from its plugin folder; nothing used it.
- **Perego**: shows its own logo on sign-in and is unchanged there. The theme does not reach it. The card image
  changes. Perego runs corex-ui: a hero or a call-to-action band inserted after the update names a section style;
  under Perego's theme it falls back and draws as before (SC-009 proves it). Bands already on its pages do not
  change. The form's required mark turns from the theme's `accent` to its `accent-dark` on both clients' forms.
  The example colour in a settings help text changes in both admins.
- **Mizzey**: not on this release.
- **A site running the CoreX theme**: its colours, its button and its dark sections change on update. A site that
  chose Dark keeps the navy dark values until Dark is chosen again. Editorial is no longer offered.
- No upgrade step, no stored data changed, no client file edited.

## Assumptions

- Neither client theme inherits the CoreX theme's `theme.json` (the workspace's kit verification, section A). Slice
  1 confirms it with the client-repository check before it merges.
- R2.1's scales, faces and Arabic rules are unchanged: this release adds colour tokens and section styles only.
- The capture in slice 4 shows the front page as this release leaves it: the kit's colours on today's layout. The
  releases that build the kit's blocks each take a new capture.
- The kit's components, blocks and templates need their own specs. Six blocks are new to CoreX and have no story
  yet (the hand-over lists them); they enter through the planning workspace's change control.
- The native Arabic review of R2.1's catalogue is still owed before R2.1 is tagged. The neutral example colour
  changes six catalogue entries; they join that review.

## Slices

Each slice is one pull request through the CoreX loop: built test first, Guard Gate, `corex-reviewer`, the
two-model review, CI green. A slice a person sees is built only from the approved design, then checked by
`corex-ui-reviewer` against it, with one recorded round for the owner.

| Slice | What | FRs | Waits on | Design |
|---|---|---|---|---|
| 1 | Tokens: the palette, the custom colours and the shadows in both files, bound to the contract; the contrast matrix; brass text moved to `accent-dark`; Editorial removed; navy values and example copy gone; Foundations and inventories regenerated | FR-001 to FR-010 | nothing | approved: `design/handoffs/visitor-kit.md` |
| 2 | Base elements and section styles: the brass button, links, focus, selection; the three section styles; the hero and call-to-action patterns on them, with the fallback under a client theme | FR-011 to FR-017 | 1 | approved: the same |
| 3 | The logo set, the login's lockup by appearance, the theme's favicon | FR-018 to FR-024 | its own design approval | **Design first** |
| 4 | The theme preview: the capture command, the fingerprint and its test, the new image | FR-025 to FR-027 | 2 | approved: the HomePage template shows the look |
| 5 | Release notes with before and after captures, the branding guide, the client impact, PROGRESS | FR-028 | 1 to 4 | no |

Slice 3 shares no file with slices 1 and 2 and may be built beside them once its design is approved.

**What each slice proves.** Slice 1: SC-001 (colours), SC-002, SC-003, SC-004, with the layout captures equal before
and after. Slice 2: SC-005 and SC-009, and the bands measured in both modes and both directions. Slice 3: SC-006
and SC-007, the manifest test, and Muva's sign-in on a copy of its repository. Slice 4: SC-008. Slice 5: every
visible change listed, and spec 121's three held criteria marked complete.

**Principles touched.** I (the favicon is presentation and stays in the theme), V (tokens in `theme.json`, read at
runtime; the band re-points tokens with tokens, never a raw value; the contract stays a test fixture), VI (no new
global asset; a section style's rules load with the theme's own styles), VIII (logical properties; every screen
checked right to left), IX (the band falls back under a theme that lacks its tokens), X (this spec before code).
