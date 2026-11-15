# Feature Specification: The CoreX theme in CoreX colours

**Feature Branch**: `spec/134-theme-graphite-brass`

**Created**: 2026-11-15, under the CoreX loop (constitution v2.0.0). This file is the whole spec: it ends in
slices, and there is no separate plan or task file.

**Status**: Draft, **on hold since 2026-11-15**. The owner rejected a design that only recolours today's layout and
asked for the design system to become a real component kit first (foundations, components, patterns, blocks,
templates), enough to build a whole site in one identity. That work is under way in the design system (its Version 6
holds the foundations and 30 visitor components). This spec is revised once the kit is approved. Two things below
are already superseded by the design system: the main button is brass with a graphite label, not graphite; and the
theme gains more custom colour tokens than `onAccent` (a brass hover, a brass tint and four status tints). Slices 1, 2 and 3 each wait for the owner's approval of the design (`design/handoffs/spec-134-theme-graphite-brass.md`, written once he approves).

**The design system is the source.** A change to the look starts in the CoreX design system in Claude Design, then
reaches the code through `theme.json` (`AGENTS.md`, "UI, UX and front end", step 7). So this release runs in that
order:

1. The design system's visitor colours and shadows were moved to graphite and brass on 2026-11-15 (its Version 5,
   private to the owner): https://claude.ai/artifact/DExY8maEuxwM6JwdZ1jw5c . They hold the values of "The palette"
   below. **The owner's approval of the look is given there first.**
2. The screens are drawn on that design system: 32 artboards, canvas Version 7,
   https://claude.ai/artifact/RgpHR931N7vqqnF6whkNyz . They were drawn on the same values before the design system
   was updated; once the look is approved the design system is installed on the canvas and each artboard is checked
   against it. Not approved yet. The screens keep today's layout and shapes and change colours only.
3. `theme/theme.json` and `theme/styles/dark.json` follow the design system (slice 1). Where they would differ, the
   design system is changed first.

**Input**: Release R2.2 of the CoreX revamp, three stories approved under the owner's 2026-10-21 mandate: US-BRD-01
(the CoreX theme wears the CoreX brand), US-BRD-05 (the logo set is complete and reads on every background) and
US-BRD-07 (the theme's preview shows the theme). The stories, their sixteen test cases and the reading of the code
behind them are kept in the owner's private planning workspace. It builds on spec 121 (R2.1: one brand contract, the
theme's own scales, the Arabic face) and finishes what spec 121 left for this release: FR-025, TC-DOC-07-04 and
SC-007 there (DECISIONS #468).

## Why this spec exists

CoreX's admin, login and emails are graphite and brass. The CoreX theme is still navy and cyan: a palette the owner
rejected on 2026-11-15, when he chose "direction A" (white, graphite, brass) for the public theme as well as the
admin. So the product and its own theme read as two products.

Three more things follow from the same gap:

- the brand contract (`design/tokens/brand-contract.json`) holds the admin and the emails to one set of values, and
  leaves the theme's colours and shadows unbound until the theme moves;
- the login draws the CoreX lockup as a background image, where its four corner modules and its divider turn black
  on the dark card, so only the brass centre and the word show; the whole word is brass where only the "x" should
  be; and there is no stacked lockup, no single-ink version, no favicon;
- the theme's preview image is a brass marketing page in another typeface, with a product menu and sign-in buttons.
  The activated theme is a white page with a navy hero.

This release changes colours, the logo files and the preview. It does not change the theme's layout: the header,
the footer and what the kit pages say are R2.3 and R2.4.

## Clarifications

### Session 2026-11-15 (decided under the owner's mandate)

Settled before this session, by the owner on 2026-11-15: direction A is the look for the public theme too; every
slug keeps its name and only values change; the theme's colours join the brand contract. The owner delegates
trade-offs: where the stories leave a choice open, the options were laid out, the recommended one taken, and each is
recorded as decided under the owner's mandate (DECISIONS #633 to #638). What a person sees is drawn first and waits
for his approval of the design.

- Q: Which brand value does each theme slug take? → A: **The table under "The palette" below.** Every value is one
  the brand contract already lists, with one exception that the contract now records (`success` in light). The page
  is white, text and headings graphite, brass the accent.
- Q: Is the main button brass or graphite? → A: **Graphite, as wired today** (`primary` behind, `inverse` on it).
  White on the light brass `#ad8643` is 3.35:1, so a brass button would need graphite text, and a `brand.json` that
  sets `primary` would stop reaching the buttons. Brass is the accent: links, focus, selection, the call-to-action
  band, the mark. The design shows the brass button as an alternative the owner can ask for.
- Q: What is the text colour on a brass background? → A: **Graphite `#14151a` in both modes** (5.44:1 on `#ad8643`,
  7.65:1 on `#c9a25e`). No existing slug is dark in both modes, so the theme gains one custom token,
  `settings.custom.color.onAccent`. It is not a palette slug: `brand.json` and the slug list client themes follow
  are unchanged.
- Q: How do headings and buttons behave inside a coloured section? → A: **A heading with no colour of its own takes
  the section's text colour. A button with no colours of its own inside a section that sets a text colour is drawn
  as an outline in that colour.** A pattern may still give a button its own two colours.
- Q: The call-to-action band is brass with white text today. → A: **It stays brass and its text becomes graphite**:
  the pattern stops naming `surface` for the band's text and heading, and the theme gives brass backgrounds their
  graphite text. Its button keeps `surface` behind and `accent-dark` on it. Not chosen: moving the band to
  `surface-alt` (the page would show brass only in links); a new palette slug named by the pattern (a client theme
  that lacks the slug would draw a button with no background).
- Q: The dark variation: chosen by the site owner, or automatic? → A: **Chosen, as today** (`styles/dark.json` in
  the Site Editor). Following the visitor's computer is not in this release.
- Q: A section on `primary` in the dark variation? → A: **It inverts, as today**: `primary` is the heading colour,
  so it is light in dark mode and the hero becomes a light band with dark text.
- Q: The "editorial" variation: kept or removed? → A: **Removed.** It is a terracotta palette nothing documents
  beyond one sentence, the owner chose one look, and every variation kept is a palette to hold to AA on every
  release. A site that already chose it keeps its colours (see Edge cases).
- Q: Do the shadows take the contract's geometry or only its colours? → A: **The contract's three shadows whole**,
  light and dark, so one test holds one scale. The medium and large shadows get softer and wider.
- Q: Where do the favicon files live? → A: **Written by the logo generator into the logo folder and into the
  theme**, with a test that holds the two copies equal. The theme must not need a plugin's folder to draw its icon
  (constitution I and II).
- Q: How does the login show the lockup? → A: **Two lockup files with fixed colours, one for the dark card and one
  for the light card**, chosen by the admin appearance (System follows the computer). `currentColor` stays only in
  files meant to be drawn inline.
- Q: How does a test know the preview is older than the palette? → A: **The capture script writes a fingerprint of
  the palette beside the image; a test recomputes it.** File dates do not survive a checkout.

### The palette

Light is `theme/theme.json`; dark is `theme/styles/dark.json`. "Contract" names the brand-contract token the value
comes from.

| Slug | Light | Dark | Contract | Used for |
|---|---|---|---|---|
| `primary` | `#14151a` | `#f4f5f7` | text | headings, the main button, the hero band |
| `primary-dark` | `#0d0e11` | `#c2c8d1` | shell (dark value), border-strong (light value) | main button, hover |
| `accent` | `#ad8643` | `#c9a25e` | accent | focus ring, selection, the call-to-action band |
| `accent-dark` | `#76521d` | `#e2c188` | link | links, brass text |
| `surface` | `#ffffff` | `#16181d` | surface-raised (light), canvas (dark) | the page |
| `surface-alt` | `#f3f4f6` | `#1a1d23` | surface-alt | quiet bands, cards |
| `surface-raised` | `#ffffff` | `#1c1f26` | surface-raised | raised cards |
| `surface-strong` | `#e2e5ea` | `#262a32` | border | strong bands, hairlines |
| `border` | `#8b9099` | `#646b78` | text-subtle | field and card outlines (3:1) |
| `ink` | `#14151a` | `#f4f5f7` | text | body text |
| `ink-soft` | `#5b616d` | `#9aa0ab` | text-muted | secondary text |
| `success` | `#287a4e` | `#5fb787` | success (dark); theme value (light) | success text |
| `warning` | `#8a5b13` | `#e0a33a` | warning | warning text |
| `error` | `#b23530` | `#e0645c` | error | error text |
| `info` | `#176b96` | `#6e9be6` | info | information text |
| `inverse` | `#ffffff` | `#14151a` | accent-ink (dark), surface-raised (light) | text on `primary` |
| `overlay` | `rgb(15 17 22 / 54%)` | `rgb(7 9 12 / 72%)` | overlay | behind a dialog |
| `selection` | `#ad8643` | `#c9a25e` | accent | selected text, behind |
| `selection-text` | `#14151a` | `#14151a` | text (light) | selected text |
| custom `color.onAccent` | `#14151a` | `#14151a` | text (light) | text on brass |

The compatibility slugs follow their role, as today: `background` is `surface`, `foreground` is `ink`, `danger` and
`corex-danger` are `error`, `corex-info`, `corex-primary`, `corex-success` and `corex-warning` are their roles, and
`white` is `inverse`.

`success` in light is the one value the admin set does not hold: the admin's `#2f8f5b` is 4.04:1 as text on white,
under the 4.5:1 the theme's contrast test asks. The theme takes `#287a4e` (5.27:1 on white, 4.79:1 on
`surface-alt`), and the contract records it as the theme's value with this reason. Whether the admin's light
success passes where the admin uses it is a question for the admin redesign (spec 133), not this spec.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - The CoreX theme wears the CoreX brand (US-BRD-01, Priority: P1)

As someone who installs the CoreX theme, I want it to look like CoreX (white and graphite surfaces, a brass accent,
light and dark), so that the product and the theme read as one.

**Why this priority**: it is the release. The logo and the preview follow from it.

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
4. **Given** the hero, the call-to-action band and any section with a background colour, **When** each heading's
   and button's colours are read against the section, in English and Arabic, **Then** none is the colour of its
   background and each pair passes (TC-BRD-01-04).
5. **Given** a paragraph, **When** part of it is selected, **Then** the selection is drawn in the selection tokens
   (TC-BRD-01-05).
6. **Given** a `brand.json` that sets `primary`, **When** it is active, **Then** headings and the main button take
   it (TC-BRD-01-06).
7. **Given** `theme.json` and the variations, **When** they are searched for `0B1F3B`, `007A9E`, `00C2FF` and
   `rgba(11, 31, 59`, **Then** none is found (TC-BRD-01-07).
8. **Given** a group block with a background and a text colour that holds a heading and a button with no colours of
   their own, **When** it is drawn, **Then** the heading is the section's text colour and the button is an outline
   in it. This is the failure path the story names: no heading is ever the colour of its background.

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
  styles, so that site keeps the values it chose: navy for Dark, terracotta for Editorial. Nothing is broken and
  nothing is rewritten. The release notes say that choosing Dark again takes the new colours.
- **A `brand.json`.** It replaces the palette whole and must name the thirteen roles it always had. It keeps
  working. A brand whose `accent` is dark gets graphite text on it unless it sets `custom.color.onAccent`; the
  release notes and the branding guide say so.
- **Content already saved with the old call-to-action markup** names `surface` for its heading. On the CoreX theme
  that heading is white on brass, 3.35:1 in light: it passes AA only as large text, which a second-level heading
  is. The front-page template draws the pattern by reference, so it takes the new markup at once.
- **The call-to-action pattern under a client's theme.** A newly inserted band takes that theme's own text and
  heading colours on its accent, where it named `surface` before. Bands already on a page do not change.
- **Brass on the inverted hero in Dark.** `accent` on `primary` is 2.18:1 there. No shipped pattern draws it, and
  the contrast matrix records the pair as not for use.
- **A field on a `surface-alt` band.** `border` is 3.21:1 on white and 2.91:1 on `surface-alt` in light, as today's
  grey is. A field's own background is white, so its outline is read against white.
- **The form's shapes.** The form block sets no outline, corner or typeface on its fields and its button, so they
  are the browser's own today (measured on the development site: square fields with a grey inset line, a square
  button in the browser's font). This release changes their colours only and the design draws them as they are.
  Their shape belongs to the visitor forms release, unless the owner asks for it here.
- **System admin appearance on the login.** The lockup follows the computer, as the card does.
- **A site icon is set, then removed.** The CoreX favicon returns under the CoreX theme only.

## Requirements *(mandatory)*

### Functional Requirements

**Palette and contract (US-BRD-01)**

- **FR-001**: `theme/theme.json` and `theme/styles/dark.json` MUST hold the values of "The palette" above, which are
  the values of the CoreX design system as the owner approves them, and every existing slug MUST keep its name.
- **FR-002**: The theme's three shadows MUST be the contract's, in light (`theme.json`) and in dark (`dark.json`).
- **FR-003**: `design/tokens/brand-contract.json` MUST list every theme palette slug, the `onAccent` token and the
  three shadows with their light and dark values, and `tests/Unit/Theme/BrandContractTest.php` MUST fail, naming the
  slug, the mode and the file, when `theme.json` or `dark.json` holds another value.
- **FR-004**: Every theme colour value MUST be a value the contract lists for the brand, apart from the theme
  values the contract names with a reason (one today: `success` in light).
- **FR-005**: The contrast matrix MUST hold the pairs the theme draws in both modes, including text on brass
  (`onAccent` on `accent`), selected text, the call-to-action button and its label, and `border` on `surface`, and
  MUST record pairs that are not for use (`accent` on `primary` in dark). Every pair for use MUST pass AA.
- **FR-005a**: Where a CoreX block draws text, or a mark that carries meaning, in `accent` on a page surface, it
  MUST draw it in `accent-dark`. Brass `#ad8643` is 3.35:1 on white: enough for a focus ring or a band, not for
  text. One place is known, the required mark of the form block (`.corex-form__required`, corex-forms); slice 1
  lists the rest from the built stylesheets before it changes a value.
- **FR-006**: Selected text MUST be drawn with `selection` behind and `selection-text` on it, on every surface.
- **FR-007**: The "editorial" variation MUST be removed, with the sentence in the branding guide that names it.
- **FR-008**: No shipped file, test, fixture or docs page MUST name `#0B1F3B`, `#007A9E`, `#00C2FF` or the other
  navy-era values, apart from `DECISIONS.md`, `CHANGELOG.md` and earlier specs. Example copy that showed a navy
  value ("such as #0B1F3B") MUST show a neutral one, in English and in the Arabic catalogue.
- **FR-009**: The Foundations page's theme tables and spec 057's generated inventories MUST be regenerated from the
  new files, which completes spec 121's FR-025, TC-DOC-07-04 and SC-007.
- **FR-010**: `DECISIONS #41` MUST be superseded for the theme by a recorded decision.

**Coloured sections (US-BRD-01)**

- **FR-011**: Under the CoreX theme, a heading with no colour of its own inside a block that sets a text colour or a
  background MUST take that block's text colour.
- **FR-012**: A button with no colours of its own inside a block that sets a text colour MUST be drawn as an outline
  in that colour, with its label in that colour, in every state (rest, hover, focus).
- **FR-013**: A block on the `accent` background that sets no text colour MUST take `custom.color.onAccent`.
- **FR-014**: The call-to-action pattern MUST stop naming a text colour for its band and its heading. Its button
  keeps its own two colours. No CoreX pattern may name a palette slug that the theme did not have before this spec.
- **FR-015**: `HeroContrastTest` MUST keep its thresholds (heading 4.5:1, button against its section 3:1, label
  4.5:1) and pass for the hero and the call-to-action band in both palettes.

**Logo set (US-BRD-05)**

- **FR-016**: The lockup MUST draw "Core" in the neutral colour and only the terminal "x" in brass.
- **FR-017**: The logo set MUST gain a lockup with fixed colours for dark backgrounds, one for light backgrounds, a
  stacked lockup in both, and a single-ink knockout on a brass tile. Brass is `#c9a25e` on dark and `#ad8643` on
  light.
- **FR-018**: The login MUST draw the fixed-colour lockup that suits its card: the dark one when the admin
  appearance is Dark, the light one when Light, and by the computer's setting when System. A logo set in CoreX
  Settings MUST still replace it.
- **FR-019**: A favicon set MUST exist as SVG and as PNG at 16, 32, 180 and 512px, written by the logo generator.
  The raster sizes are an explicit exception to the "no raster set" rule, recorded in the decision.
- **FR-020**: The CoreX theme MUST link the CoreX favicon when the site has no site icon, from files inside the
  theme, and MUST link nothing when a site icon is set. No plugin may add a favicon under another theme.
- **FR-021**: `corex-mark.svg` MUST be removed and its generator MUST stop writing it.
- **FR-022**: The logo manifest MUST list every file with its use, its colours and its hash, and the logo contract
  test MUST fail when a file and the manifest disagree, or when the theme's favicon copies differ from the logo
  folder's.

**Theme preview (US-BRD-07)**

- **FR-023**: `theme/screenshot.png` MUST be a 1200 by 900 capture of the CoreX theme's front page in the default
  style with the Company kit applied, with neutral sample content and no CoreX product copy.
- **FR-024**: One documented command MUST take the capture on a scratch site and write, beside the image, a
  fingerprint of the palettes and shadows of `theme.json` and `dark.json`.
- **FR-025**: A test MUST fail, naming that command, when the fingerprint differs from the files.

**Release**

- **FR-026**: The release notes MUST list each visible change with before and after captures of every template in
  light and dark, and MUST state the effect on Muva and Perego.

### Key Entities

- **Theme palette**: the slugs of `theme.json` and `dark.json`, bound to the brand contract.
- **Coloured section**: a block that sets a background or a text colour and holds other blocks.
- **Logo set**: the files of `plugins/corex-config/assets/brand/`, their manifest and the generator that writes them.
- **Preview fingerprint**: a hash of the palettes and shadows, written when the preview is captured.

## Success Criteria *(mandatory)*

- **SC-001**: On the front page, a content page and the Contact page, in both modes and both directions, at 1280px
  and 375px, every colour read in the browser is the palette's, and `npm run test:quality` passes.
- **SC-002**: Changing one theme colour or shadow in `theme.json` or `dark.json` fails the unit suite with the
  slug's name.
- **SC-003**: The contrast matrix and the hero contrast test pass in both palettes with their thresholds unchanged.
- **SC-004**: A search of the shipped files, tests, fixtures and docs pages for the navy-era values finds none.
- **SC-005**: On the login, in Dark and in Light, a pixel sampled inside each of the four corner modules differs
  from the card behind it by at least 3:1.
- **SC-006**: The CoreX theme with no site icon links a CoreX favicon; another theme links none.
- **SC-007**: The preview is 1200 by 900, and a palette change without a new capture fails a test.
- **SC-008**: Muva's and Perego's own suites pass on a copy of each repository with this release merged in
  (US-NFR-08's check).

## What is measured

| What | Where | How |
|---|---|---|
| Token values per mode | front page, content page, Contact | computed custom properties in Chromium |
| Text and control contrast | every template, both modes | `ContrastMatrixTest`, `HeroContrastTest`, the quality suite's accessibility run |
| Coloured sections | hero, call-to-action band, a hand-made section | computed heading, button and label colours against the section, English and Arabic |
| Selection | a paragraph on each surface | computed `::selection` colours |
| Layout unchanged | every template, 1280px and 375px | before and after captures; block positions and sizes equal |
| Login mark | `/wp-login.php`, Dark, Light, System | pixel samples of the four modules, the centre, the divider, the word |
| Icon links | front page under the CoreX theme and another | the head's `rel="icon"` and `apple-touch-icon` links |
| Preview | `theme/screenshot.png` | size, fingerprint, comparison with the front page |
| Clients | copies of Muva and Perego | their own suites with this release merged in |

## Client impact

- **Muva**: its sign-in shows the whole CoreX mark after the update, with "Core" neutral and the "x" brass (the fix
  is in corex-config and corex-core). The theme palette does not reach it: Muva runs its own theme. The inactive
  CoreX theme's card image changes in Appearance, Themes. `corex-mark.svg` disappears from its plugin folder;
  nothing used it.
- **Perego**: shows its own logo on sign-in and is unchanged there. The theme palette does not reach it. The card
  image changes. Perego runs corex-ui: a call-to-action band inserted after the update takes Perego's own text and
  heading colours where it named `surface` before; bands already on its pages do not change. The example colour in
  a settings help text changes in both admins.
- **Mizzey**: not on this release.
- **A site running the CoreX theme**: its colours change on update. A site that chose Dark keeps the navy dark
  values until Dark is chosen again. A site that chose Editorial keeps them, and the variation is no longer offered.
- No upgrade step, no stored data changed, no client file edited.

## Assumptions

- Neither client theme inherits the CoreX theme's `theme.json` (the workspace's kit verification, section A). Slice
  1 confirms it with the client-repository check before it merges.
- R2.1's scales, faces and Arabic rules are unchanged: this release touches colours and shadows only.
- The capture in slice 3 shows the front page as R2.2 leaves it. R2.3 and R2.4 change what that page holds and each
  takes a new capture as part of its own work; the fingerprint covers the palette only, as the story asks.
- The CoreX design system in Claude Design was made on 2026-11-15 from the code as it was before R2.1. This spec
  moves its visitor colours and shadows to the new look and nothing else: its visitor scales (spacing, radius,
  motion, sizes, the heading weight, the Arabic face) still record the state before R2.1 and say so on their first
  page. Remaking the whole system is DT-0, after this release.
- The native Arabic review of R2.1's catalogue is still owed before R2.1 is tagged. The neutral example colour
  changes six catalogue entries; they join that review.

## Slices

Each slice is one pull request through the CoreX loop: built test first, Guard Gate, `corex-reviewer`, the
two-model review, CI green. A slice a person sees (marked **Design first**) is built only from the approved design,
then checked by `corex-ui-reviewer` against it, with one recorded round for the owner.

| Slice | What | FRs | Waits on | Design |
|---|---|---|---|---|
| 1 | The palette and shadows in both files, bound to the contract; the contrast matrix; brass text moved to `accent-dark`; coloured sections and the call-to-action band; selection; Editorial removed; navy values and example copy gone; Foundations and inventories regenerated | FR-001 to FR-015, FR-005a | the approved design | Design first |
| 2 | The logo set: the corrected lockup, the fixed-colour and stacked lockups, the knockout, the favicon set, the manifest; the login's lockup by appearance; the theme's favicon | FR-016 to FR-022 | the approved design | Design first |
| 3 | The theme preview: the capture command, the fingerprint and its test, the new image | FR-023 to FR-025 | 1 | Design first |
| 4 | Release notes with before and after captures, the branding guide, the client impact, PROGRESS | FR-026 | 1, 2, 3 | no |

Slices 1 and 2 share no file and may be built at the same time.

**What each slice proves.** Slice 1: SC-001 to SC-004 and SC-008, with the layout captures equal before and after.
Slice 2: SC-005 and SC-006, the manifest test, and Muva's sign-in on a copy of its repository. Slice 3: SC-007.
Slice 4: every visible change listed, and spec 121's three held criteria marked complete.

**Principles touched.** I (the favicon is presentation and stays in the theme), V (tokens in `theme.json`, read at
runtime; the contract stays a test fixture, nothing is generated from it), VI (no new global asset), VIII (logical
properties; every screen checked right to left), X (this spec before code).
