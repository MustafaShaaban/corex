# Feature Specification: The header system

**Feature Branch**: `spec/137-header-system` (this file), then one branch per slice.

**Created**: 2026-11-21, under the CoreX loop (constitution v2.0.0). This file is the whole spec: it ends in
slices, and there is no separate plan or task file.

**Status**: Spec merged (PR #493, `4d0a2b8d`). Designs approved by the owner on 2026-11-22 (`design/handoffs/header-system.md`,
DECISIONS #696). No slice built. Added on 2026-11-23: the toggle form of the language switcher (FR-032a,
G-054, DECISIONS #697), whose drawing the owner approved the same day (DECISIONS #702), so slice 2 builds it,
and slice 6, the header's scroll distance
(FR-046 to FR-048, DECISIONS #698, from #701).

**Closes**: G-013 (High) of the Blackstone gap programme, wave 1 (DECISIONS #651, #652; the table in
`PROGRESS.md`, "The Blackstone gap programme"). Also G-054 (Medium), the toggle form, and the header scroll
distance a site can set (DECISIONS #701).

**Design**: approved. The bar, the mega panel and the plain phone menu: `design/handoffs/navigation-footer.md`
(2026-06-20) and the visitor kit's SiteHeader, MegaMenu, Navigation and Dropdown
(`design/handoffs/visitor-kit.md`, 2026-11-15, DECISIONS #639). The phone menu holding mega panels as groups,
the utility menu, the language switcher in its two forms, the header search and the bar at the foot of a phone
screen: `design/handoffs/header-system.md`, approved on 2026-11-22 in three steps (DECISIONS #696). The rules
the kit lacked are in `specs/137-header-system/design/header-system.css.txt`; the boards stay on the canvas.

**Reviewed**: by `corex-reviewer` on 2026-11-21; its ten findings and its notes are answered in this text.

**Input**: the gap row G-013 and decisions 6, 9 and 10 of the Blackstone grill of 2026-11-21 (in that site's
repository, which CoreX sessions only read), and five stories of the revamp's NAV epic, approved under the owner's
2026-10-21 mandate and kept in his private planning workspace: US-NAV-11, US-NAV-02, US-NAV-04, US-NAV-10 and
US-NAV-05. Their acceptance criteria are copied below as written.

## Why this spec exists

A CoreX header today is a WordPress Navigation block with other things beside it. Five things are wrong with that:

1. **A mega menu is not in the menu.** The mega patterns are `wp:details` blocks placed next to the Navigation
   block, with links written by hand (`theme/patterns/header-saas.php`, `megamenu-*.php`). The site has two lists
   of its pages, and on a phone the panels stay in the bar while the menu opens without them.
2. **Three widths decide the same thing.** WordPress folds its menu at 600px, `corex-navigation.css` switches its
   panels at `48em` (768px), and the token `custom.nav.breakpoint` says 782px and is read by nothing.
3. **The utility bar is two frozen lines.** The corporate header prints the brand email and phone as they were on
   the day the pattern was inserted. It cannot hold links.
4. **There is no place for a language switch.** Spec 058 promised a slot; no pattern has one.
5. **Search is raw HTML** in two header patterns, so an editor cannot change it as a block.

The Blackstone site needs a two-tier header with three mega panels on six menu items, one phone menu that holds
the panels as groups with the utility links and the language switch, and a switch to the phone form near 1024px.
None of that can be built from CoreX today without a hand-written header and a site script.

This spec builds the parts a site composes such a header from. It is not a header builder and not a mega menu
builder (`ROADMAP.md` section 15 defers both): nothing here adds a screen where a header is assembled. An owner
still edits the header in the Site Editor, with one new item to add to a menu.

## Clarifications

### Session 2026-11-21 (settled from the files, under the owner's delegation; DECISIONS #690 to #695)

- Q: Which NAV stories does this spec take? → A: **Five, whole.** US-NAV-11 (one width), US-NAV-02 (a mega menu
  inside the menu), US-NAV-04 (hover, focus and arrow keys), US-NAV-10 (the phone menu holds everything) and
  US-NAV-05 (the language switcher). G-013 cannot close without each. US-NAV-04 is taken because a mega item
  that opens only on a click would be a step back from what G-013 asks for.
- Q: Which stay with their release? → A: US-NAV-14, 07, 01 and 13 (R2.4): landmarks, the sticky header, the default
  header's call to action, the footers. US-NAV-03 and 09 (R4.1): the icon, description and badge anatomy of a
  panel entry, which waits for the front-end icon set, and the one search overlay with its keyboard shortcut.
  US-NAV-06, 08 and 12 (R4.2), and US-NAV-15. This spec must not make any of them harder, and names where it
  touches their ground (FR-034, FR-040).
- Q: Where does it go past the stories? → A: **In two places.** (1) A utility menu: no story has one, and
  Blackstone's header (HDR-1, HDR-6) has three utility links and the language switch above the bar and again
  inside the phone menu. (2) US-NAV-11 says the token decides the width; this spec adds that a child theme's
  own `theme.json` sets it with no copied file, because a client theme is a child of the CoreX theme from spec
  135 on (DECISIONS #655).
- Q: NAV-D2 says "Core overlay now; drawer later". Does one phone menu that holds panels break that? → A: **No.**
  The phone menu stays WordPress's own overlay, with its focus handling and its Escape. What it shows is a
  template part of WordPress's "navigation overlay" area, which WordPress 7.0, the oldest version CoreX supports,
  already has (`render_block_core_navigation_overlay_close` is marked `@since 7.0.0`). No drawer is built; it is
  recorded as later work, as the story says.
- Q: Is a panel's content inner blocks of the menu item, or a template part? → A: **A template part**, as the gap
  row asks. A panel is as wide as the page and is edited on its own canvas; the same part is shown under the bar
  and inside the phone menu; a child theme ships a panel as a file; a translation plugin translates a template
  part. The item in the menu holds a label and the name of its panel, nothing else.
- Q: Which plugin carries what? → A: The mega menu item is in the **`corex-ui` add-on** (US-NAV-02: "ships in an
  add-on"). The language switcher block and the language source are in **`corex-blocks`**, a core plugin, so a
  header keeps its language slot with the add-on off. The breakpoint is written by **`corex-core`**'s
  `NavigationServiceProvider`, which already attaches the theme's navigation assets. The theme holds the
  patterns, the parts and the styles (constitution I).
- Q: Does a multilingual plugin get detected now? → A: **No** (US-NAV-05, DECISIONS #657). The block asks a
  `LanguageSource` for the site's languages. The source that ships reads WordPress alone, which has one
  language, so the block prints nothing. Polylang and WPML sources are spec 148's.
- Q: What happens to today's headers? → A: **Nothing, unless a site opts in.** A header already saved on a site
  keeps its `wp:details` panels, its raw search and its bar: the script and the styles that serve them stay.
  The one change every CoreX header gets is the single width (US-NAV-11), which is a repair.

## User Scenarios & Testing *(mandatory)*

The criteria under each story are the planning workspace's, copied as written. Where a criterion names another
story that this spec does not take, the note under it says what this spec does.

### User Story 1 - The header switches to its mobile form at one width (US-NAV-11, Priority: P1)

As a visitor on a tablet or a narrow window, I want the header to be either its desktop form or its mobile form,
never a mix, so that the menu does not wrap onto three rows or open a menu that pushes the page down.

**Acceptance criteria**

* The header, its menu, mega panels and search all switch between desktop and mobile at one width, the value of the
  theme's navigation breakpoint token
* Changing the token changes the switch point for all of them
* An automated test fails if the token, the CSS and the collapse point disagree
* An automated test fails if a WordPress update changes its own collapse point in a way that breaks the override
* Spec 058's research note is corrected

**Added by this spec (G-013, decision 10 of the Blackstone grill):** a child theme that sets the token in its own
`theme.json` moves the switch for the header it inherits, with no file copied from the CoreX theme.

### User Story 2 - A mega menu sits inside the menu (US-NAV-02, Priority: P1)

As a site owner, I want to add a mega menu as an item of my navigation menu, so that it sits with the other links,
works on a phone and is announced as part of the site's navigation.

**Acceptance criteria**

* In the Site Editor, a "Mega menu item" can be added inside the Navigation block next to ordinary links, and moved
  and removed like them
* On the page the mega item is inside the navigation landmark and its list
* On a phone it appears inside the opened menu as a section that expands in place, with the same items
* On desktop the panel opens as an overlay below the header and does not change the header's height
* The mega patterns are rebuilt as panels of the new item; each pattern's description says how to put it on a mega
  menu item, and what it says is true
* The item ships in an add-on, so it appears in the editor only on sites that turn that add-on on
* DECISIONS #103 point 1 ("mega menus are the native details element") is superseded by a recorded decision

**Corrected in the planning workspace first (2026-11-21, under CR-049), then copied here.** The fifth criterion
read "The mega patterns are rebuilt on the new item; the instruction 'Insert into a header's navigation area'
becomes true". The gap row asks for the panel as a template part, and a pattern cannot carry a template part, so
a mega pattern is now the content of a panel and is chosen on an item, not inserted into the menu. The CoreX
theme therefore ships no header with a mega item in place: an owner adds the item and picks a panel
(`corex/header-saas` stops saying it has a features mega menu).

### User Story 3 - Menus open on hover, close when focus leaves, and move with arrow keys (US-NAV-04, Priority: P1)

As a visitor using a mouse or a keyboard, I want mega menus to open when I rest on them, close when I move away, and
let me move between items with the arrow keys, so that the menu feels like the rest of the web.

**Acceptance criteria**

* Resting the pointer on a trigger for about 150ms opens its panel; leaving the trigger and panel closes it after a
  short delay; a click still opens and closes it
* Moving keyboard focus out of an open panel closes it
* Down and Up move between the panel's items; Home and End go to the first and last; Escape closes the panel and
  returns focus to its trigger
* Each trigger shows a caret that turns when open and is mirrored in right-to-left pages
* The trigger names the panel it controls, and the panel has an accessible name
* On touch devices hover is not required: a tap opens and closes
* With reduced motion set, panels appear without animation

### User Story 4 - The mobile menu holds everything the header holds (US-NAV-10, Priority: P1)

As a visitor on a phone, I want the menu, its sub-sections, the search and the main action all inside the mobile menu,
so that I do not have to look in two places.

**Acceptance criteria**

* The opened mobile menu contains the menu, the call to action and the search
* Sub-sections are collapsed and open one at a time, with a chevron that turns and mirrors in right-to-left
* Focus stays inside the menu while it is open; Escape and the close button close it and return focus to the menu
  button
* An optional sticky call-to-action bar can be added at the bottom of the screen on phones, clear of the phone's
  safe areas
* If WordPress 7.0 lacks the navigation overlay template part, the story says how sites on 7.0 get the same result,
  or CoreX's minimum WordPress version is raised by decision
* The slide-in drawer from the start edge is recorded as later work, not part of this story

**Added by this spec (G-013, HDR-6):** the opened menu also holds the utility menu and the language switcher of a
header that has them.

### User Story 5 - The language switcher works or is not there (US-NAV-05, Priority: P1)

As a visitor on a site in more than one language, I want a language switcher that takes me to this page in my
language, and on a site in one language I want no switcher at all, so that nothing in the header is a dead control.

**Acceptance criteria**

* The dead "EN" link is removed from every pattern (shipped with US-SAFE-14 if that lands first)
* A language switcher block shows the current language and lists the others available on the site, each linking to
  the same page in that language when it exists, otherwise to that language's home page
* With only one language available, the block renders nothing
* Its visible text is part of its accessible name
* The block reads the site's languages from WordPress through one replaceable source, so a later story can add an
  optional Polylang or WPML source without changing the block; no multilingual plugin is required or detected now
* In right-to-left languages, pages set `dir="rtl"` and Arabic text uses the Arabic font token
* The framework document and DECISIONS no longer describe the `I18nHandler` as existing, or it exists

**Past the story** (G-054, DECISIONS #697): a third form, the **toggle**, for a site with exactly two languages:
one link naming the other language (FR-032a). The criteria above are unchanged.

### User Story 6 - A header has a utility menu (US-NAV-16, added under CR-049; G-013, Priority: P1)

As a site owner, I want a row above the bar that holds a short menu of secondary links and the language switch,
which I edit as a menu, so that careers, support and the language are within reach without crowding the main menu.

**Acceptance criteria** (written with this spec and held in the planning workspace as US-NAV-16)

* A header pattern has a utility row above the bar holding a Navigation block named "Utility" and the language
  switcher
* The utility menu is one list: the row above the bar and the phone menu show the same template part, so a link
  added once appears in both
* Until an owner edits it, the utility menu shows the Brand settings' phone and email as they are today, not as
  they were when the pattern was inserted; with neither set and one language, the row is not shown and takes no
  space
* On a phone the row is not shown in the bar; its links and the language switch are inside the opened menu
* A second navigation on the page has its own name, so the two are told apart by a screen reader

### User Story 7 - Search in a header is a block (G-013, Priority: P2)

As a site owner, I want the search in a header to be WordPress's Search block, so that I can change or remove it in
the editor like anything else.

**Acceptance criteria**

* No shipped header pattern holds a raw HTML block
* A header with search shows it as the Search block in the bar on desktop and inside the opened menu on a phone
* A header saved earlier with the raw search keeps working as it does today

### User Story 8 - The header changes state at a distance the theme sets (DECISIONS #701, Priority: P2)

As a site builder, I want to set in my theme how far a page scrolls before a sticky or transparent header takes
its scrolled look, so that the header matches my design without copying the header script.

**Acceptance criteria** (no NAV story holds this; they are this spec's own)

* A site that sets nothing sees no change: the header takes its scrolled state past 8 pixels, as today
* A child theme that sets the distance in its own `theme.json` moves the change to that distance, with no copied
  file
* A value the script cannot use is read as 8 pixels, and nothing breaks

### Edge Cases

- **A mega item whose panel is missing** (none chosen, or its part was deleted): the item is not rendered on the
  page, so there is never a trigger that opens nothing. In the editor it shows a notice that says to choose a panel.
- **A panel that holds the menu it belongs to**: the panel is rendered once; the item inside it is skipped.
- **Two copies of one menu on a page** (the bar and the phone menu): every trigger and panel has its own id, and
  each trigger controls its own panel.
- **No script**: on desktop a panel opens while the pointer or the focus is inside its item. A language switcher
  shows its languages as a plain list.
- **A token value that is not a length** (empty, a word, a percentage): the default 782px is used and nothing a
  theme wrote reaches the page as CSS.
- **A token below 600px**: read as 600px, WordPress's own width. A narrower switch is not supported.
- **Escape inside an open group of the phone menu**: closes the menu, as WordPress does, and focus returns to the
  menu button. On desktop, Escape closes the open panel only.
- **A child theme that ships a panel part while `corex-ui` is off**: WordPress raises its notice for a part in an
  area nobody registered. The client site docs say a panel part belongs only on a site with the add-on on.
- **A header from another theme** (Muva's, Perego's): the breakpoint writer does nothing, because it only acts
  where the active theme, or its parent, ships the CoreX navigation styles.
- **The `corex-ui` add-on is off**: no mega item exists in the editor; a header pattern never prints one; a menu
  that holds one shows the rest of its links.
- **A long label, six items and a button at a width just above the breakpoint**: the bar does not wrap; a site
  whose menu does not fit raises its token (Blackstone: near 1024px).
- **Right to left**: carets, chevrons and the side a panel aligns to mirror by logical properties, with no rule
  written for one direction.

## Requirements *(mandatory)*

### Functional Requirements

**One width (User Story 1). Slice 1.**

- **FR-001**: The value of `settings.custom.nav.breakpoint` in the active theme's merged `theme.json` MUST be the
  one width at which a CoreX header changes between its desktop and its phone form. The CoreX theme's value stays
  `782px`.
- **FR-002**: WordPress's own collapse of the Navigation block at 600px MUST be overridden at that width, for
  Navigation blocks inside a `.corex-header` only. Between 600px and the token the menu is folded behind its
  button; from the token up it is inline.
- **FR-003**: Every rule of the theme's navigation styles that depends on the width MUST live in one stylesheet,
  `theme/assets/css/corex-navigation-collapse.css`, written with the default width. The two `48em` conditions in
  `corex-navigation.css` MUST go. No other CoreX stylesheet or script may name a navigation width.
- **FR-004**: `Corex\Theme\NavigationServiceProvider` MUST serve that stylesheet as a file when the token is the
  default, and otherwise print it inline with the token's width in place of the default, in the editor's canvas as
  on the page. It MUST accept only a whole or decimal number of `px`, fall back to the default for anything else,
  and read a value below 600px as 600px.
- **FR-005**: A child theme of the CoreX theme that sets the token MUST get the switch at its own width with no file
  of the parent copied or edited. A fixture child theme proves it in the browser at 1024px.
- **FR-006**: The provider MUST do nothing when neither the active theme nor its parent ships the collapse
  stylesheet.
- **FR-007**: Tests MUST fail when (a) the token and the width written in the collapse stylesheet differ, (b) any
  other CoreX navigation stylesheet or script names a width, (c) in a browser, the menu button and the inline menu
  are not exchanged exactly at the token, for the default and for the fixture's 1024px, and (d) WordPress's
  navigation stylesheet no longer holds the two rules the override answers.
- **FR-008**: `specs/058-header-mobile-navigation/research.md` (R6) MUST say that WordPress folds at 600px, not at
  782px, and point here.

**The mega menu item (User Stories 2 and 3). Slice 3, with its phone form in slice 4.**

- **FR-009**: The `corex-ui` add-on MUST register a block `corex/mega-menu`, titled "Mega menu item", whose only
  parent is `core/navigation`, and MUST add it to the blocks the Navigation block accepts. WordPress reads that
  list from the block's settings in the editor, so the add-on's editor script adds it there
  (`blocks.registerBlockType`); a server filter alone never reaches the editor. With the add-on off neither
  exists.
- **FR-010**: The item MUST hold a label and the slug of one template part, its panel. The add-on MUST register a
  template part area `mega-menu` ("Mega menu panels") so panels are listed apart from headers and footers.
- **FR-011**: On the page the item MUST be a list item of the navigation's list, holding a button and the panel.
  The button states whether the panel is open and which panel it controls; the panel is a named group, named by
  its button. Ids are unique for every copy on a page.
- **FR-012**: The item MUST NOT be rendered when its panel is missing or empty, and MUST NOT render a panel that
  is already being rendered.
- **FR-013**: In the editor the label MUST be typed in place in the menu, and the panel chosen from the parts of
  the `mega-menu` area, with a link that opens the chosen panel for editing and a way to start a new one from a
  panel pattern. An item with no panel shows a notice. These controls are WordPress's own editor components as
  they come, under the standard spec 136 sets; nothing here is a CoreX-styled surface.
- **FR-014**: On desktop the panel MUST open below the header as an overlay, without changing the header's height,
  and MUST keep inside the window in both directions of writing. In a CoreX header it is as wide as
  `.corex-header__inner`. In any other header it is as wide as the Navigation block, and a theme widens it by
  making an ancestor the panel's anchor, which the block's style documents.
- **FR-015**: A view script that loads only where the item renders MUST give the behaviour of User Story 3:
  opening after about 150ms of rest and closing after a short delay, a click or a tap that opens and closes,
  closing when focus leaves, Down, Up, Home and End among the panel's links and buttons, Escape that closes and
  returns focus, one panel open at a time. Hover applies to a mouse only.
- **FR-016**: Without the script a panel MUST open while the pointer or the focus is inside its item.
- **FR-017**: The trigger MUST show a caret that turns when open and mirrors in right-to-left pages. Under reduced
  motion nothing animates.
- **FR-018**: Whether a panel is drawn as an overlay or as a section in place MUST follow where the item is (in
  the bar, or inside the opened phone menu), never a width of its own, so the breakpoint stays in one place.
  Slice 3 builds the overlay. Inside an opened menu, slice 3 shows the panel in place under its label, open, the
  way WordPress shows a sub-menu there today; the collapsing group is slice 4's, from the approved drawing. No
  release carries slice 3 without slice 4.
- **FR-019**: The four mega patterns (`corex/megamenu-simple`, `-services`, `-product`, `-docs`) MUST be rebuilt
  as panel patterns for the `mega-menu` area: the panel's content only, under the same slugs. Their descriptions
  MUST say how a panel is put on a menu item, and that sentence MUST be true.
- **FR-020**: No shipped header pattern may place a mega panel beside the Navigation block. The script and the
  styles that serve `details.corex-mega` MUST stay, so a header saved earlier keeps working.
- **FR-021**: The two blocks this spec adds (`corex/mega-menu`, `corex/language-switcher`) MUST follow the editor
  standard that spec 136 sets for a block that renders on the server (DECISIONS #656). Their renderers print
  `get_block_wrapper_attributes()`, the one rule #656 already fixes. If spec 136's shared base for renderers is on
  `main` when a slice is built, the slice uses it; if not, the block is moved onto it by whichever lands second.

**The phone menu (User Story 4). Slice 4.**

- **FR-022**: The theme MUST ship three phone menu template parts in WordPress's `navigation-overlay` area:
  `mobile-menu` (the close button, the menu, the language switcher, the header's actions), `mobile-menu-search`
  (the same with the Search block first) and `mobile-menu-utility` (the same as the first with the utility menu
  before the actions). Each part file holds its Navigation block directly, with `overlayMenu` set to `never` and
  the class `corex-mobile-menu__nav`: WordPress switches off the menu button of a nested Navigation block only
  when it finds the block in the part's own markup, never through a pattern or a second part. A part file holds
  no words; anything with words or Brand values in it is a pattern the part names.
- **FR-023**: The header's actions MUST be one template part, `header-actions`, that names a pattern printing the
  Brand call to action (the Brand label, or "Get started" translated when none is set; the Brand link; nothing
  when no link is set). The bar of every header with a menu and every phone menu show that part, so the bar and
  the menu hold the same action by construction. The default header part and every such header pattern MUST name
  its phone menu and carry the class `corex-header--menu-holds-actions`. Below the breakpoint such a header shows
  the logo and the menu button only, on one row. `corex/header-saas` loses its "Sign in" button: the account link
  is US-NAV-06's, and it will sit in the same part.
- **FR-024**: `Corex\Theme\NavigationServiceProvider` MUST make a phone menu show the header's menu. While a
  Navigation block that has the class `corex-header__nav`, an `overlay` and a `ref` is being rendered, a
  Navigation block that has the class `corex-mobile-menu__nav`, no `ref` and no inner blocks takes that `ref`. A
  Navigation block without those classes is never touched, and a test proves it. A header whose Navigation block
  holds its links inline has no menu to share; no shipped pattern does that.
- **FR-025**: Inside the opened menu, mega items and WordPress's own sub-menus MUST start collapsed and open one at
  a time, each with a chevron that turns and mirrors, and each row a target of at least 44px. WordPress's
  sub-menus are set to open on a click and are collapsed by WordPress itself; the theme's navigation script closes
  the other open group of either kind, so it works with the add-on off. The mega item's own script opens and
  closes its panel.
- **FR-025a**: The phone menu and its focus handling MUST be proven once on WordPress 7.0, the oldest version CoreX
  supports, since CI installs the newest. If 7.0 fails it, the minimum version is raised by a recorded decision
  before the slice merges.
- **FR-026**: Focus MUST stay inside the opened menu; Escape and the close button MUST close it and return focus to
  the menu button. This is WordPress's own behaviour and MUST be proven in a browser, not rebuilt.
- **FR-027**: A pattern `corex/mobile-action-bar` MUST add a bar fixed to the foot of the screen below the
  breakpoint, holding the Brand call to action, clear of the device's safe areas, never covering the last content
  of the page, and absent when no call-to-action link is set.
- **FR-028**: The slide-in drawer MUST be recorded as later work in `ROADMAP.md`.

**The language switcher (User Story 5). Slice 2.**

- **FR-029**: `corex-blocks` MUST define `LanguageSource`: one method that returns the site's languages for the
  current request, each with its code, its name in its own language, its direction, the address of this page in
  that language or else of that language's home page, and whether it is the current one. It MUST be bound in the
  container, so a site or a later spec replaces it without touching the block.
- **FR-030**: The source that ships MUST read WordPress alone and return the site's one language. No function of
  Polylang or WPML is called or looked for.
- **FR-031**: A block `corex/language-switcher` MUST print nothing, not even its wrapper, when the source returns
  fewer than two languages. In the editor it then shows WordPress's placeholder with one sentence saying it
  appears once the site has a second language.
- **FR-032**: With two or more it MUST show them in one of two forms an editor chooses: a **menu** (a button that
  names the current language and opens a list of every language, the current one ticked and not a link, the
  kit's Dropdown) or a **list** (every language as
  a link in a row, the current one marked and not a link). Each name is marked with its own `lang` and `dir`. The
  visible text is part of the accessible name. Without script the menu form is the list.
- **FR-032a** (G-054, DECISIONS #697): A third form an editor can choose, the **toggle**. With exactly two
  languages it prints one link: the language that is not the current one, named in its own language and
  script, with its own `lang`, `dir` and `hreflang`, leading to the address the source gave for it. Its visible
  text is its whole accessible name, so the link reads "العربية" on an English page and "English" on an Arabic
  one. It needs no script. With fewer than two languages it prints nothing (FR-031 stands). With three or more
  it prints the list form, and the editor's choice says so in one sentence of help under it: a single link would
  leave a language with no way in, and a site that adds a third language must not lose its switcher or need an
  edit. It is built in slice 2 if the owner approves its drawing before slice 2 is built, and otherwise in
  slice 7.
- **FR-033**: The block is placed beside a Navigation block, never inside one (an item of a menu's list must be a
  list item, and the Navigation inserter of Muva and Perego does not change). Its script and style MUST load only
  where it renders something.
- **FR-034**: A test MUST prove no shipped pattern or part holds a language control that goes nowhere, and that a
  page in a right-to-left language carries `dir="rtl"` and draws its text in the Arabic font token. The slice
  reads `main` first: what already holds gets a test that keeps it, what does not is made to hold.
- **FR-035**: `docs/internal/COREX-FRAMEWORK.md` MUST stop describing an `I18nHandler` as existing and describe the
  `LanguageSource`. `docs/internal/COREX-EMAIL-ADDON.md` MUST say that an email template per language is not
  built and belongs to spec 148 (G-042); the source does not pick a recipient's language. The rest of G-045 stays
  with spec 152.

**The utility menu, search and the patterns (User Stories 6 and 7). Slice 5.**

- **FR-036**: The theme MUST ship a template part `utility-menu` that names a pattern holding a Navigation block
  named "Utility", with `overlayMenu` set to `never` (it never folds: on a phone it is inside the phone menu).
  Until an owner edits the part, its links are the Brand settings' phone and email, read on each request; with
  neither set the pattern prints nothing.
- **FR-037**: `corex/header-corporate` MUST be rebuilt: a utility row holding the `utility-menu` part and the
  language switcher, above the bar. WordPress always prints a part's wrapper, so the row cannot be left out by
  the server: a rule of the theme's navigation styles hides a utility row that holds no link and no button, and
  a browser test proves it takes no space.
- **FR-038**: Every header pattern with actions, and the default header part, MUST hold the language switcher in
  its actions, where the kit's SiteHeader draws it.
- **FR-039**: `corex/header-saas` and `corex/header-sticky` MUST hold WordPress's Search block where they hold raw
  HTML today, and `corex/header-saas` MUST lose its `wp:details` panel. No shipped pattern or part may hold a
  `wp:html` block. The script and styles that serve the raw search MUST stay for headers saved earlier.
- **FR-040**: Nothing here changes a landmark, a navigation's name, the sticky behaviour or a footer: those are
  US-NAV-14, 07 and 13. The new second navigation is named, so US-NAV-14's rule that every navigation has its own
  name holds for what this spec adds.

**The header's scroll distance (User Story 8; DECISIONS #698, from #701). Slice 6.**

- **FR-046**: The theme MUST define the token `settings.custom.header.scrollDistance`, `8px` by default, which
  WordPress prints as `--wp--custom--header--scroll-distance`. A child theme sets it in its own `theme.json`.
- **FR-047**: `theme/assets/js/corex-navigation.js` MUST read the distance from that property on the page's
  root element when it starts, and swap a transparent or sticky header to its scrolled state once the page has
  scrolled past it, where it uses the fixed `SCROLL_THRESHOLD` of 8 today. Pixels only: a missing value (a theme
  at `v0.48.1`, which has no such token), another unit, a negative number or anything that is not a number is
  read as 8 pixels (FR-043). Nothing else in the script changes.
- **FR-048**: Unit tests in jsdom MUST cover the default, a set value, and each refused value; a browser test
  MUST prove a child theme's value moves the change and the default does not. The token is documented where the
  theme's tokens are, and `node scripts/generate-token-inventory.mjs` is run after.

**For every slice.**

- **FR-041**: The planning workspace MUST be brought into step as its intake of 2026-11-21 asks: the spec number
  added to the notes of the five stories, and a story for the utility menu raised through change control. No
  acceptance criterion is edited to make work pass.
- **FR-042**: Every string a person reads MUST go through the translation functions with the plugin's or the
  theme's text domain. Nothing this spec adds finds a page, a term or a menu by its slug, and no new helper takes
  one. The helpers in `theme/inc/pattern-links.php` that do so today stay as they are in the patterns this spec
  rebuilds: replacing them in every pattern at once is G-042's work (spec 148). A block, a setting or a part this
  spec adds is written so a `wpml-config.xml` can name its strings (DECISIONS #657).
- **FR-043**: Every style MUST read `theme.json` tokens and logical properties only. A token that `v0.48.1` does
  not have MUST be written with a fallback to one it has, because the slices are carried to a line cut at that
  tag (DECISIONS #651).
- **FR-044**: A script or a style MUST load only on a page that renders the block or the header that needs it
  (constitution VI), and nothing may shift when a script loads.
- **FR-045**: Each slice MUST write its `### Client impact` lines in `CHANGELOG.md` under `[Unreleased]`, in the
  sub-list "Gap programme, wave 1", naming Muva, Perego and Mizzey, and MUST say in its pull request whether it
  rests on anything `main` gained after `v0.48.1`.

### Key Entities

- **Navigation breakpoint**: one length, `settings.custom.nav.breakpoint`. Owned by the theme, or by its child.
- **Mega menu item**: a block inside a menu. A label and the slug of its panel.
- **Panel**: a template part of the `mega-menu` area. Any blocks.
- **Phone menu**: a template part of WordPress's `navigation-overlay` area, named by a header's Navigation block.
- **Utility menu**: a template part holding a second Navigation block, shown above the bar and in the phone menu.
- **Language**: a code, a name in that language, a direction, an address, and whether it is the current one.
  Supplied by a `LanguageSource`.
- **Scroll distance**: one length in pixels, `settings.custom.header.scrollDistance`. Owned by the theme, or by
  its child.

## Success Criteria *(mandatory)*

- **SC-001**: At 781px a CoreX header shows its menu button and at 782px its inline menu, and nothing in the
  header is in the other form at either width. With a child theme whose token is `1024px` the same holds at 1023
  and 1024.
- **SC-002**: A menu with three mega items among six items renders one navigation landmark with one list of six
  items; each of the three opens its own panel, by mouse, by keyboard and by touch.
- **SC-003**: At 375px the opened menu holds, in order, the menu with its groups collapsed, the language switcher
  when the site has two languages, the utility links when the header has them, and the call to action. The bar is
  one row.
- **SC-004**: A site with one language shows no language control anywhere; the same site with a source that
  returns English and Arabic shows it in the bar and in the phone menu, and each name leads to an address the
  source gave.
- **SC-005**: A link added to the utility menu once is shown above the bar and inside the phone menu.
- **SC-006**: `grep` finds no `wp:html` and no `wp:details` in `theme/patterns/header-*.php` and `theme/parts/`.
- **SC-007**: A header saved from `corex/header-saas` at `v0.48.1` still opens its panel and its search.
- **SC-008**: The quality suite (`npm run test:quality`) passes on every header pattern at 375, 782 and 1280px,
  light and dark, left to right and right to left, with no new known failure.
- **SC-009**: Muva's and Perego's pages render the same markup before and after, with the same scripts and styles.
- **SC-010**: With a source that returns English (current) and Arabic, the toggle prints one link, "العربية",
  with `lang="ar"` and `dir="rtl"`, whose accessible name is that text; with three languages it prints the list;
  with one, nothing.
- **SC-011**: With no token set a sticky header is in its scrolled state at 9 pixels and not at 8; with a child
  theme's `120px`, at 121 and not at 120.

## What is measured

In a browser, kept as assertions: the two forms at one pixel either side of the token; the bar's height before and
after a panel opens; a panel's edges against the window at 782 and 1280px in both directions; every target in the
opened menu at 44px or more; focus order and the return of focus; the absence of a sideways scroll at 375px; the
gaps the approved design gives. In unit tests: the breakpoint writer (the default, a child's value, each refused
value); the renderers (missing panel, repeated panel, unique ids, one language); the view scripts in jsdom.

## Client impact

- **Muva**: none on its pages. It runs its own theme, so the breakpoint writer does nothing there. `corex-blocks`
  adds one block, "Language switcher", to its editor; it prints nothing unless placed on a site with two languages.
  No upgrade step.
- **Perego**: none on its pages. It has `corex-ui` on, so its editor gains "Mega menu item" inside the Navigation
  block and the area "Mega menu panels" in the Site Editor; both do nothing until used. Its own header and its own
  language toggle are untouched. No upgrade step.
- **Every site, slice 6**: none at the default. The header script changes state past 8 pixels as it does today;
  only a theme that sets the new token sees a different distance.
- **Mizzey**: not on this release line.
- **A site on the CoreX theme**: its header folds at 782px instead of at 600px for the menu and 768px for the
  panels. A header saved earlier is otherwise unchanged. **A site that never edited the default header part gets
  the new part on update, without opting in**: the same bar, with the language slot (empty for one language) and
  the phone menu in place of WordPress's default one. Headers inserted after the update get the phone menu, the
  language slot and the Search block. No client runs the CoreX theme.

## Assumptions

- The Blackstone site turns `corex-ui` on (its header needs the mega item) and ships its panels, its phone menu
  and its utility menu as parts of its child theme, with its token near 1024px.
- Spec 135 lands the child theme scaffold; this spec proves the child path with its own fixture and does not edit
  `packages/cli/stubs/site/`.
- Spec 136 owns the renderers and editor files that exist in `addons/corex-ui` today. This spec adds new files
  there and one registration line, and follows the base it lands.
- The owner approved the drawings on 2026-11-22, and the toggle's addendum (FR-032a) on 2026-11-23 (DECISIONS
  #702). Slice 6 shows nothing new and needs no drawing.

## Slices

Each slice is one pull request through the CoreX loop: built test first, Guard Gate, `corex-reviewer`, the
two-model review, CI green, merged into `main` by its session. A slice a person sees is built only from the
approved design, then checked by `corex-ui-reviewer` against it, with the accessibility and copy reviews and one
recorded round for the owner.

| Slice | What | FRs | Stories | Principles | Waits on | Design |
|---|---|---|---|---|---|---|
| 1 | One width: the collapse stylesheet, the writer in `corex-core`, the child theme fixture, the four tests, the research note | FR-001 to FR-008 | US-NAV-11 | I, II, V, VI, VIII | nothing | approved: the kit says the menu folds at 782px |
| 2 | The language source and the language switcher block in its menu form; the two proofs; the two documents | FR-029 to FR-035 and FR-032a, the toggle (the list form of FR-032 is slice 4's), FR-021 | US-NAV-05 | IV, VI, VIII, IX | nothing | approved: `header-system.md`, step 2 (2026-11-22) and step 2a, the toggle (2026-11-23) |
| 3 | The mega menu item: the block, the area, the editor controls, the desktop panel and its behaviour; the four panel patterns | FR-009 to FR-021 (the collapsing group of FR-018 is slice 4's) | US-NAV-02 (all but its phone criterion), US-NAV-04 | I, V, VI, VIII | 1; spec 136's renderer base if it is on `main` (FR-021) | approved for the panel and its trigger: the kit's MegaMenu. The editor controls are WordPress's own components |
| 4 | The phone menu: the three parts, the `header-actions` part, the same-menu rule, groups that open one at a time, the bar's phone form, the language list, the action bar, the proof on WordPress 7.0 | FR-022 to FR-028, FR-025a, the list form of FR-032, the group of FR-018 | US-NAV-10, US-NAV-02's phone criterion | I, II, V, VI, VIII | 1, 2, 3 | approved 2026-11-22: `header-system.md`, steps 1 and 3 |
| 5 | The utility menu, the corporate header, the language slot in every header, search as blocks, the docs and the gap row | FR-036 to FR-040 | US-NAV-16 (Story 6), Story 7 | I, V, VI, VIII, IX | 4 | approved 2026-11-22: `header-system.md`, steps 2 and 3 |
| 6 | The header's scroll distance: the token, the script reading it, the tests, the token docs. Ships in `v0.49.0` | FR-046 to FR-048 | Story 8 | I, V, VI | nothing | none: nothing new is seen |
| 7 | The toggle form of the language switcher, only if slice 2 was built before its drawing was approved. Ships in `v0.50.0` | FR-032a | Story 5 (past it, G-054) | IV, VI, VIII, IX | 2, 6 | not needed: the owner approved step 2a before slice 2, so slice 2 builds the toggle |

FR-041 to FR-045 bind every slice. Each slice adds the terms it builds to `docs/_glossary.md`. Principle X (the spec
first) and the Guard Gate bind every slice too. Slice 1 waits for nothing and is built first. Each pull request says
which criteria of its stories it closes. Slices 1 to 6 are built in order; slice 2 takes FR-032a when its
drawing is approved before slice 2 starts, and slice 7 then has nothing to build.
