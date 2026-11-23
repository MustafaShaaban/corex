# Feature Specification: Lists, the carousel, the ticker and motion

**Feature Branch**: `spec/149-lists-carousel-motion` (the spec), then one branch per slice

**Created**: 2026-11-23

**Status**: Draft

**Input**: The Blackstone gap programme (DECISIONS #651, #652), ordered by #701: the first spec after wave 1,
released as `v0.50.0` on `release/gap-line`. Gaps G-008 (a record picker), G-010 (a carousel of records and a
partner ticker), G-012 (reveal on scroll and count-up, moved here from spec 150) and G-009 (visitor-side filters),
in that order, from the Blackstone eIT site's fit check. Planning workspace stories US-BLK-03 (the part spec 136
left, #674) and US-BLK-07, whose acceptance criteria are used here as written. Decisions #722 to #731.

## Why this spec exists

The Blackstone site's rule (its decision 29) is that every visible slice matches its Figma frame, and a slice waits
rather than differs. Its Home page waits on four CoreX gaps: a carousel of eight newest articles that moves by
itself with an edge fade (HOM-4), three hand-picked stories in the order picked (HOM-5), a ticker of partner logos
(HOM-8), and figures that count up (HOM-2, and the hero's stats scene). Every section of every page reveals as it
comes into view (SHR-4). Its list pages (SSL-3, LLL-2, EVT-2, later the jobs list) need filters a visitor sets.
These are not Blackstone's alone: any product CoreX builds lists records, shows a carousel and a logo strip.

### What was measured (`main` at `26083a55`, 2026-11-23)

- `corex/posts` (`addons/corex-ui/src/Blocks/posts/`) has one attribute, `count` (1 to 12). It reads only `post`,
  newest first, and prints a linked title per item (`PostsRenderer.php:42-46`). `corex/projects`
  (`addons/corex-kit-portfolio`) reads `corex_project` and prints a thumbnail and a title; `corex/jobs`
  (`addons/corex-careers`) reads `corex_job` and prints a title and one line of terms. None has a post type,
  taxonomy, order, pages, card layout or a choice of records.
- No CoreX code registers a Query Loop variation, filters `query_loop_block_query_vars`, or registers a block
  support. The theme uses core's Query Loop once (`theme/templates/archive-corex_project.html`).
- `corex/carousel` has `slides` (text strings), `perView` (1 to 6, one number for every width), `autoplay` and
  `label`. Its script advances every 5000 ms (`view.js:110`), wraps to the first slide, stops on hover, focus and
  a hidden tab, and never runs under reduced motion. There is no Pause button, no loop or control option, and the
  arrows and dots show with scripts off. `corex/slide` does not exist yet: it is spec 136 slice 7 (#675).
- `corex/stat` prints its value as plain text; no block has a script for count-up or reveal. The only
  IntersectionObserver in CoreX is the carousel's.
- Motion tokens exist: `settings.custom.motion.duration` (instant, fast, base, slow) and `.easing` (standard,
  emphasized) in `theme/theme.json`.
- Muva and Perego use `corex/projects` in their `section-selected-work` pattern and `corex/stat` through the
  `corex/stats` pattern. Neither uses `corex/carousel`, `corex/posts` or `corex/jobs` in its theme or site code.
  Their saved content was not read.

### Decisions this spec settles (DECISIONS #722 to #731)

- **#722 Lists are WordPress's Query Loop.** CoreX adds a record picker to it and retires `corex/posts` and
  `corex/projects` from the inserter. Saved copies keep rendering. `corex/jobs` is retired by spec 141, which
  gives jobs their card and filters (G-036).
- **#723 A carousel's items come from its slides, from one Query Loop, or from one Gallery.**
- **#724 The carousel's options**: sizes per screen, controls, loop and timing, and what a carousel saved before
  the change keeps.
- **#725 The ticker is a mode of the carousel**, with a Pause button that is always shown.
- **#726 Reveal is a CoreX block support** that never hides content from a reader without the script.
- **#727 Count-up is an option of `corex/stat`** and a documented attribute that any block may use.
- **#728 Filters live inside the Query Loop they filter**, as a form that works without the script; the address
  carries term ids.
- **#729 What is CoreX's and what is the site's.**
- **#730 The slices, what each rests on, and the line.**
- **#731 Every visible value is a token a client theme can change**, tested with a fixture theme.

## What is CoreX's and what is the site's (#729)

| Feature | Where | Why |
|---|---|---|
| Record picker on Query Loop | CoreX, `corex-blocks` | every product hand-picks records for a home page |
| Carousel sources, sizes, controls, loop, timing | CoreX, `corex-ui` | the carousel is CoreX's block |
| Ticker mode | CoreX, `corex-ui` | logo strips are common; the Pause rule is CoreX's (#652) |
| Reveal support, count-up | CoreX, `corex-ui` (one script each) | every page section may use them |
| Filter blocks | CoreX, `corex-blocks` | every archive of every product |
| Card patterns (`lens-card`, `story-card-bento`, `story-card-glass`), the bento layout, the coverflow style, card hover dimming | Blackstone, its site plugin and child theme | the site's own look, drawn in its Figma |
| Story, event and office post types, their taxonomies and fields | Blackstone (spec 151 for what CoreX generates) | the site's content model |

## User Scenarios & Testing *(mandatory)*

### User Story 1 - An editor hand-picks records in the order wanted (Priority: P1)

A marketing editor places a Query Loop, switches it to "Chosen records", searches for stories by title, adds three,
puts them in order and publishes. The page and the editor's canvas show those three, in that order, in the site's
card pattern.

**Why this priority**: HOM-5 and SSL-2 wait for it, and it is the base the record carousel stands on.

**Independent Test**: insert the variation, pick three posts out of date order, check the canvas and the page show
them in the picked order; unpublish one and check the page shows two and the editor names the missing one.

**Acceptance Scenarios**:

1. **Given** a Query Loop set to "Chosen records" for a post type, **When** the editor adds records by searching
   their titles, **Then** the canvas shows them in the order they were added.
2. **Given** chosen records, **When** the editor moves one up or down with the keyboard or removes one, **Then**
   the canvas and, after saving, the page follow.
3. **Given** a chosen record that is later unpublished or deleted, **When** the page renders, **Then** it is left
   out without an error, and the editor's panel names it as unavailable with a button to remove it.
4. **Given** no record chosen yet, **When** the editor looks at the block, **Then** the canvas shows the Query
   Loop's own "No results" content and the panel says to choose records.
5. **Given** a page with `corex/posts` or `corex/projects` saved on it, **When** it renders, **Then** it prints
   what it printed before; **When** an editor opens it, **Then** the block offers "Replace with a Query Loop",
   which an editor must press.

---

### User Story 2 - A carousel of records that fits every screen and can be stopped (Priority: P1)

An editor puts a Query Loop of the eight newest articles inside a carousel, sets slide width or slides per view,
turns on "Advance by itself" and an edge fade. A visitor on a phone sees whole slides, can swipe, and can press
Pause.

**Why this priority**: HOM-4 (Leadership Lens) and SSL-2 (featured stories) wait for it; US-BLK-03's criteria
are owed since spec 136.

**Independent Test**: a carousel holding a Query Loop of eight posts, a carousel holding a Gallery, a carousel of
slides; each checked at 375, 782 and 1280px, light and dark, left to right and right to left, with and without
scripts, with reduced motion, with keyboard only.

**Acceptance Scenarios**: US-BLK-03's criteria, as written (below), and:

1. **Given** a carousel holding one Query Loop, **When** the page renders, **Then** each record is one slide, and
   the record picker of User Story 1 works inside it.
2. **Given** a carousel holding one Gallery, **When** the page renders, **Then** each image is one slide with its
   alternative text, and an image set to "Expand on click" opens WordPress's own larger view.
3. **Given** "Advance by itself" on, **When** the page loads, **Then** a Pause button is shown and the carousel
   advances at the seconds set, never below 4.
4. **Given** an edge fade at the end, **When** the page is right to left, **Then** the fade is on the left.

**US-BLK-03 acceptance criteria (as written; the first criterion's image and the last were met by spec 136):**

* A slide can be a testimonial (quote, name, role, photo or initial), an image (with its alternative text, opening a
  larger view), a logo, or a project card; a text slide still works
* The number of slides in view drops with the screen: a 6-up carousel shows at most two on a phone and four on a
  tablet; the 2-up carousel shows part of the next slide
* Dots are at least 24 by 24px including their spacing, and arrows at least 44px
* When autoplay is on, a visible Pause button stops it and becomes Play; autoplay still stops on hover, on focus and
  when the tab is hidden, and never runs with reduced motion
* With scripts off, no arrow, dot or Pause is shown, and the row still scrolls by swipe and keyboard
* On touch screens the arrows are hidden and the dots stay
* Images beyond the visible and next slides load lazily and keep their space
* In right-to-left pages the slide order and the arrows follow the page direction
* Carousels saved before the change keep their slides

---

### User Story 3 - A ticker of partner logos with a Pause button (Priority: P1)

An editor adds a Gallery of partner logos to a carousel and chooses "Ticker". The logos move in one continuous
line with edge fades; a visitor can pause them, and a visitor who asked for reduced motion sees them still.

**Why this priority**: HOM-8 waits for it.

**Independent Test**: a ticker of fourteen logos at 375 and 1280px, left to right and right to left; Pause and
Play by mouse and keyboard; hover and focus stop it; reduced motion shows a still row; scripts off shows a still
row; a screen reader hears each logo once.

**Acceptance Scenarios**:

1. **Given** a ticker, **When** the page loads with no reduced-motion preference, **Then** the items move in one
   continuous loop with no gap or jump, and a Pause button is shown.
2. **Given** a moving ticker, **When** a visitor hovers it, focuses an item in it, presses Pause, or hides the
   tab, **Then** it stops; Play, leaving it, or showing the tab again resumes it, except after Pause, which only
   Play undoes.
3. **Given** reduced motion or scripts off, **When** the page renders, **Then** the items are shown still, every
   item reachable, and no Pause button is shown.
4. **Given** a right-to-left page, **When** the ticker moves, **Then** it moves in the page's reading direction.
5. **Given** a ticker, **When** a screen reader reads it, **Then** each item is read once: the copy that makes
   the loop seamless is hidden from it and cannot take focus.

---

### User Story 4 - Figures count up when they come into view (Priority: P2)

An editor turns on "Count up" on four `corex/stat` blocks in a KPI band. A visitor scrolling to the band sees each
figure count from zero to "120+", "1,300+", "9" and "50+".

**Why this priority**: HOM-2, WWA-4 and CAP-6 wait for it, and the site's hero scenes take the same script.

**Independent Test**: four stats with count-up on, in Latin and Arabic-Indic digits; check the start, the end, no
layout shift, reduced motion, scripts off, and what a screen reader hears.

**Acceptance Scenarios**:

1. **Given** count-up on, **When** the figure first comes into view, **Then** it counts from zero to its value
   once, keeping its prefix, suffix, grouping and digits.
2. **Given** reduced motion, scripts off, or a value the script cannot read as a number, **When** the page
   renders, **Then** the final value is shown as written.
3. **Given** a screen reader, **When** it reaches the figure, **Then** it hears the final value once, never the
   numbers in between.
4. **Given** the count runs, **When** the numbers change, **Then** nothing around them moves.

---

### User Story 5 - Sections reveal as they come into view (Priority: P2)

An editor sets "Reveal" on each section's top Group. A visitor scrolling down sees each section fade and rise in.

**Why this priority**: SHR-4: every section of the site's design reveals.

**Independent Test**: a page of eight Groups with reveal on; check the page with scripts off, with reduced motion,
in the editor's canvas, when printed, and its layout shift and largest paint.

**Acceptance Scenarios**:

1. **Given** reveal on a block below the fold, **When** it comes into view, **Then** it moves from the design's
   start state to its end state once.
2. **Given** a block already in view when the page loads, **When** the script starts, **Then** it is shown as it
   is, not animated.
3. **Given** scripts off, reduced motion, the editor's canvas, or print, **When** the page or canvas renders,
   **Then** every block is shown in its end state.
4. **Given** "Inner blocks one after another", **When** the block reveals, **Then** its direct inner blocks
   reveal in order, a short step apart.

---

### User Story 6 - Visitors filter a list and share it (Priority: P2)

A visitor on the success stories page opens "Industry", ticks Government and Banking, and the list shows only those
stories, with chips for the two and a count read out. The address carries the filter; a colleague opening it sees
the same list.

**Why this priority**: SSL-3, LLL-2 and EVT-2 wait for it, and later the jobs list (spec 141). It ships in
`v0.50.0` if it is on `main` when the first three gaps are, otherwise with the next tag (#701).

**Independent Test**: a Query Loop of a post type with two taxonomies and a date, the filter bar holding two term
filters, a year filter and a status filter; each checked with and without scripts, at 375 and 1280px, right to
left, keyboard only, with a screen reader.

**Acceptance Scenarios**: US-BLK-07's criteria, as written (below), and:

1. **Given** a term filter, **When** a visitor ticks more than one term, **Then** the list shows items in any of
   them, and the control says how many are selected.
2. **Given** a year filter, **When** a visitor picks a year, **Then** only items dated that year are listed.
3. **Given** a status filter on a date field, **When** a visitor picks "Upcoming", **Then** only items whose date
   is today or later in the site's time zone are listed.
4. **Given** "Load more" in place of page links, **When** a visitor presses it, **Then** the next page's items are
   added below and focus moves to the first new item; with scripts off it is a link to the next page.
5. **Given** a phone, **When** the page renders, **Then** the filters fold into one "Filters" button that names
   how many are active and opens them in a sheet.
6. **Given** a filtered address, **When** a search engine reads it, **Then** the page asks not to be indexed and
   its links are still followed.

**US-BLK-07 acceptance criteria (as written):**

* A filter bar block on an archive offers a choice for each category-like grouping of the items listed (for projects:
  Type and Status)
* Applying a filter lists only matching items; the address carries the filters, so a shared link shows the same list
* Each active filter shows as a chip with a button named "Remove filter: <value>", and "Clear all" removes them all
* The bar works with scripts off, as a form that reloads the page
* With no match, the list says no items match these filters and offers Clear all
* The number of results is announced after filtering
* Labels are translatable and the bar mirrors in right-to-left pages

### Edge Cases

- A chosen record of another post type than the Query Loop's: it is not shown; the panel searches only the Query
  Loop's post type.
- A site with Polylang or WPML: a chosen id may belong to another language. A filter (`corex/chosen_records`) lets
  the language adapter of spec 148 map each id to the page's language; with no adapter the ids are used as saved.
- A carousel holding a Query Loop and slides at once: the editor allows one source only; the placeholder offers the
  three.
- A carousel with fewer items than fit in view: no arrows, dots, Pause or movement; the items are shown still.
- A ticker whose items are fewer than the screen is wide: the loop is filled by repeating the copy as often as
  needed, every copy hidden from screen readers.
- A count-up value with text around it ("Over 120 projects"): only a value that is a number with an optional
  prefix and suffix counts; anything else is shown as written.
- Reveal on a block inside a carousel, a ticker, a modal or a drawer: not offered there; a saved one is ignored.
- Two Query Loops with filters on one page: each reads only its own part of the address (the Query Loop's id).
- A term filter whose taxonomy has no terms with items: it is not printed.
- A filtered list that is also paged: changing a filter goes back to the first page.

## Requirements *(mandatory)*

### Lists and the record picker (G-008, #722)

- **FR-001**: CoreX MUST register a Query Loop variation named "Chosen records" whose query holds an ordered list of
  record ids (`query.corexChosen`) and a post type.
- **FR-002**: Its block settings MUST offer, built only from WordPress's own components: a search by title in the
  Query Loop's post type, the chosen records in order, a button to move each up and down, and a button to remove
  each, all usable by keyboard and named for a screen reader.
- **FR-003**: On the page, a Query Loop with chosen records MUST list exactly those that are published and readable
  by the visitor, in the chosen order, ignoring sticky posts, ordering, offset and page size.
- **FR-004**: The editor's canvas MUST show the same records in the same order. The slice MUST first measure
  whether core's post template sends a parameter the REST query can read for this; if not, CoreX supplies the
  preview's records itself. Either way a test MUST compare the canvas with the page.
- **FR-005**: A chosen record that is no longer published or readable MUST be left out of the page without an
  error, and named in the panel as unavailable, with a button to remove it.
- **FR-006**: A filter `corex/chosen_records` MUST receive the ids, the post type and the block, and return the ids
  to use, so a language adapter can map them (spec 148).
- **FR-007**: `corex/posts` and `corex/projects` MUST leave the inserter and MUST render content saved with them as
  before. Each MUST offer a transform "Replace with a Query Loop" that writes a Query Loop of the same post type and
  count with a post template that prints what the block printed; it runs only when an editor presses it.
- **FR-008**: The corex-ui pattern `corex/news`, the only pattern holding `corex/posts` (`PatternLibrary.php:218`),
  MUST insert a Query Loop instead. The company kit applies that pattern (`CompanyBlueprint.php:74`), so a kit
  applied after the change writes the Query Loop; pages written by an earlier apply keep `corex/posts`.
- **FR-009**: `corex/jobs` is not changed here (spec 141).

### Carousel items (G-010, #723)

- **FR-010**: `corex/carousel` MUST accept as its items exactly one of: its `corex/slide` blocks (spec 136 slice
  7), one Query Loop (each record of its post template is an item), or one Gallery (each image is an item). The
  editor's placeholder MUST offer the three; once one is placed the other two are not offered.
- **FR-011**: The renderer MUST mark each item as a slide in the markup the carousel's script and styles read,
  whatever the source, and MUST keep the source block's own markup inside it.
- **FR-012**: An image item set to "Expand on click" MUST open WordPress's own larger view; CoreX MUST NOT draw
  its own (#652: where WordPress's own block does the job, CoreX uses it).
- **FR-013**: Images beyond the visible and next slides MUST load lazily and keep their space; the visible ones
  MUST NOT be lazy.

### Carousel options (G-010, US-BLK-03, #724)

- **FR-020**: Size MUST be set one of two ways: slides per view for desktop, tablet and phone, or one slide width
  (a length or a spacing token) with as many in view as fit. The tablet and desktop widths are the theme's.
- **FR-021**: When the tablet and phone counts are not set, they MUST follow US-BLK-03: at most four on a tablet,
  at most two on a phone, and a 2-up or 3-up carousel shows one slide on a phone with part of the next.
- **FR-022**: The gap between slides MUST be a spacing token, and the current slide MUST be alignable to the start
  or the centre. The slides in view MUST carry a class and the current one `aria-current`, so a site style (such
  as a coverflow) can draw them differently.
- **FR-023**: Arrows and dots MUST each be shown or hidden by the editor. Dots MUST be at least 24 by 24px with
  their spacing and arrows at least 44px, sized from tokens. On touch screens arrows MUST be hidden and dots kept.
- **FR-024**: "Advance by itself" MUST take seconds with a floor of 4, MUST show a Pause button that becomes Play,
  MUST stop on hover, on focus and when the tab is hidden, and MUST never run under reduced motion.
- **FR-025**: "Loop" MUST make the next control after the last slide go to the first and the previous control
  before the first go to the last; with it off, the control at an end is disabled, and advancing by itself stops
  at the last slide.
- **FR-026**: An edge fade MUST be offered at the start, the end, both or neither, drawn with a mask in logical
  terms so it follows the page direction, and MUST never cover a control.
- **FR-027**: With scripts off no arrow, dot or Pause MUST be shown, and the row MUST scroll by swipe and keyboard.
- **FR-028**: In right-to-left pages the slide order, the arrows and the direction of advance MUST follow the page.
- **FR-029**: A carousel saved before the change MUST keep its slides and, as far as no US-BLK-03 criterion says
  otherwise, its behaviour: `perView` becomes the desktop count, autoplay keeps 5 seconds and wraps to the first
  slide as today. The slice MUST record in its pull request each visible difference the criteria force (the Pause
  button, the control sizes, fewer slides on a phone).

### The ticker (G-010, #725)

- **FR-030**: The carousel MUST offer the mode "Ticker": the items move in one continuous line at one of three named
  speeds (values set by the approved design), with no arrows or dots.
- **FR-031**: A ticker MUST always show a Pause button that becomes Play (WCAG 2.2.2), MUST stop on hover, on focus
  inside it and when the tab is hidden, and after Pause MUST move again only on Play.
- **FR-032**: The copies that make the loop seamless MUST be hidden from screen readers and unable to take focus
  (`aria-hidden` and `inert`); the first copy is the one read.
- **FR-033**: Under reduced motion and with scripts off, a ticker MUST show its items still, as the approved
  design's still state, every item reachable, with no Pause button.
- **FR-034**: A ticker MUST move in the page's reading direction and MUST take the edge fade of FR-026.

### Count-up (G-012, #727)

- **FR-040**: `corex/stat` MUST offer "Count up", off by default.
- **FR-041**: The page MUST carry the final value as text. The script MUST count from zero to it once, when the
  figure first comes into view, over the duration and easing of the approved design, from tokens.
- **FR-042**: The count MUST keep the value's prefix, suffix, decimals, grouping and digit script (Latin or
  Arabic-Indic). A value that is not a number with an optional prefix and suffix MUST NOT count.
- **FR-043**: A screen reader MUST hear the final value only; the changing numbers MUST be hidden from it.
- **FR-044**: The figure MUST keep the final value's width while counting, so nothing around it moves (layout shift
  0 in the test).
- **FR-045**: The count-up MUST be a registered script handle with a documented attribute
  (`data-corex-count-up` on the element holding the value), so a site block such as the Blackstone hero's stats
  scene uses the same script, never its own. The attribute and the handle are written in `addons/corex-ui/README.md`.
- **FR-046**: Under reduced motion and with scripts off, the final value MUST be shown at once.

### Reveal (G-012, #726)

- **FR-050**: CoreX MUST add a "Reveal" setting to core's Group, Cover, Columns, Media and Text, Image, Gallery and
  Query Loop and to every CoreX block, with a filter `corex/reveal/blocks` to change the list. Its values: none
  (the default), "Fade", "Fade and rise". With either, "Inner blocks one after another" MAY be turned on.
- **FR-051**: The renderer MUST print the choice as a data attribute on the block's root; nothing else in the
  markup changes, and the stylesheet and script load only on pages that use it.
- **FR-052**: A block MUST be hidden or moved only by the script, and only when it is out of view when the script
  starts. A block in view at that moment MUST be shown as it is. With scripts off, under reduced motion, in print
  and in the editor's canvas, every block MUST be shown in its end state.
- **FR-053**: A block MUST reveal once, when the share of it that the approved design names comes into view, over
  the duration, distance and easing of the approved design, from tokens. The start state MUST never be fully
  transparent.
- **FR-054**: Reveal MUST NOT be offered on a block inside a carousel, a ticker, a modal or a drawer, and a saved
  one there MUST be ignored.
- **FR-055**: A page with reveal on its sections MUST have a layout shift of 0 and the same largest paint element
  as without it, measured by the quality suite.

### Filters (G-009, US-BLK-07, #728)

- **FR-060**: CoreX MUST add a filter bar block placed inside a Query Loop, which reads that Query Loop's id and
  query from its context. It holds filter blocks and MAY show the active filters as chips with "Clear all" and the
  number of results.
- **FR-061**: Three filter blocks MUST exist: by terms of one taxonomy (multi-select; shown as a dropdown of
  checkboxes, as chips, or as tabs for one choice), by year (of the publish date or of a date field named in its
  settings), and by status from a date field (upcoming or past, in the site's time zone). Each label MUST be
  editable in place, with a translatable default.
- **FR-062**: The bar MUST be a form that submits by GET and works without the script. The address MUST carry each
  filter under a name made from the Query Loop's id (`filter-<id>-<taxonomy>`, `filter-<id>-year`,
  `filter-<id>-status`), with term ids as values, never slugs.
- **FR-063**: The server MUST apply the address to that Query Loop only: through `query_loop_block_query_vars` for
  a Query Loop with its own query, and through the main query for one that inherits it (an archive template).
  Changing a filter MUST go back to the first page.
- **FR-064**: Each term control MUST say how many terms are selected. Counts per option MAY be turned on; they
  count items of the Query Loop's own query, without the other filters, and are cached until an item of that post
  type is saved or deleted.
- **FR-065**: Each active filter MUST show as a chip with a button named "Remove filter: <value>", and "Clear all"
  MUST remove them all. With no match the list MUST say "No items match these filters." with Clear all, inside the
  Query Loop's "No results" block, where the sentence is editable.
- **FR-066**: With the script, a change MUST update the list without reloading the page, update the address, keep
  focus where the visitor was, and announce the number of results in a polite live region. A "Load more" block MUST
  add the next page's items and move focus to the first new one; without the script it is a link to the next page.
- **FR-067**: Below the tablet width the filters MUST fold into one "Filters" button that names how many are active
  and opens them in a sheet, as the approved design draws it; the chips stay outside it.
- **FR-068**: A page whose address carries a filter MUST print `noindex, follow` through `wp_robots`.
- **FR-069**: The filter script MUST leave core's own page links alone where the Query Loop has "Reload full page"
  off; the slice MUST test the two together.

### Restyling by a client theme (#731)

A client site must match its own design to the pixel (the Blackstone site's decision 29) without CSS that fights
CoreX's. So every visible value of every block this spec adds or changes is a token a client theme can change.

- **FR-080**: No block of this spec MAY hold a fixed size, colour, radius, gap, offset, speed, duration, easing,
  distance or threshold. Each is read from a property named `--corex-<block>-<name>` set on the block's root,
  whose default is the token `settings.custom.<block>.<name>` in `theme/theme.json`
  (`--wp--custom--<block>--<name>`), itself made from the theme's presets where one fits.
- **FR-081**: A client theme MUST be able to change each value two ways: in its own `theme.json`
  (`settings.custom.<block>.<name>`), and in a block style or class that sets `--corex-<block>-<name>`.
- **FR-082**: Values a script needs (the ticker's speed, the reveal's and the count-up's duration, step and
  threshold) MUST be read by the script from the computed property when it starts, never written in the script.
- **FR-083**: The markup MUST carry stable classes for every part a style may target (`corex-carousel__arrow`,
  `__dot`, `__pause`, `__fade`, `__slide`, `is-current`, `is-in-view`; `corex-filters__control`, `__panel`,
  `__chip`, `__count`, `__clear`, `__sheet`, `__more`; the reveal and count-up data attributes), and these MUST be
  listed in `addons/corex-ui/README.md` and `plugins/corex-blocks/README.md` as a public contract.
- **FR-084**: The tokens are at least:

| Block | Tokens (`settings.custom.<block>.*`) |
|---|---|
| `carousel` | `gap`, `peek`, `fadeSize`; `arrowSize`, `arrowIconSize`, `arrowColor`, `arrowBackground`, `arrowBackgroundHover`, `arrowBorder`, `arrowRadius`, `arrowInset`; `dotSize`, `dotTarget`, `dotGap`, `dotColor`, `dotColorCurrent`, `dotRadius`; `controlsGap`, `controlsOffset`; `pauseSize`, `pauseColor`, `pauseBackground`, `pauseRadius`, `pauseInsetBlock`, `pauseInsetInline` |
| `ticker` | `speedSlow`, `speedMedium`, `speedFast` (pixels per second), `gap`, `fadeSize`, `pauseSize`, `pauseColor`, `pauseBackground`, `pauseRadius`, `pauseInsetBlock`, `pauseInsetInline` |
| `reveal` | `distance`, `opacityStart`, `duration`, `easing`, `stagger`, `threshold` |
| `countUp` | `duration`, `easing`, `threshold` |
| `filters` | `gap`; `controlHeight`, `controlPadding`, `controlRadius`, `controlBorder`, `controlBackground`, `controlColor`; `panelBackground`, `panelRadius`, `panelShadow`, `panelMaxHeight`; `chipBackground`, `chipColor`, `chipRadius`, `chipPadding`, `chipGap`, `chipRemoveSize`; `countColor`, `countSize`; `clearColor`; `moreGap`; `sheetBackground`, `sheetRadius`, `sheetPadding`, `sheetBackdrop`, `sheetMaxHeight` |

- **FR-085**: A fixture client theme in the test suite MUST override every token of FR-084 with a value unlike its
  default, and a Playwright test MUST prove from the computed style that each one changes what renders, at 375
  and 1280px. A unit check MUST fail when a style file of these blocks holds a length, colour, time or easing
  literal (0, `100%`, keywords and `var()` references excepted).
- **FR-086**: Each design (149-D1 to 149-D4) MUST name its values as the tokens of FR-084, with their defaults, so
  the code and the drawing hold the same names.

### Every slice (the gap programme's rules)

- **FR-090**: Every string a person reads MUST go through the translation functions with the plugin's text domain,
  in PHP and in scripts. No lookup by an English slug; Polylang and WPML are never required; new strings are
  written so a `wpml-config.xml` can name them.
- **FR-091**: Styles MUST use `theme.json` tokens and logical properties only; a block's assets MUST load only where
  the block is. A new token is added to `theme/theme.json` beside the tag's own tokens.
- **FR-092**: Every block this spec adds or changes MUST meet the editor standard of spec 136 (its kind declared,
  its parity fixture passing): the canvas shows the page, and every text is typed in place.
- **FR-093**: Each slice's pull request MUST say what it rests on that `main` gained after `v0.48.1`.
- **FR-094**: Each slice MUST add its `### Client impact` lines to `CHANGELOG.md` under `[Unreleased]`, in a
  sub-list "Gap programme, v0.50.0", naming Muva, Perego and Mizzey.

### Key Entities

- **Chosen records**: an ordered list of ids in a Query Loop's query (`corexChosen`), of the Query Loop's post type.
- **Item source**: what a carousel's items are: its slides, one Query Loop, or one Gallery.
- **Ticker**: a carousel mode; its seamless copy is decoration, never content.
- **Reveal**: a block attribute (none, fade, fade and rise; one after another), printed as a data attribute.
- **Count-up**: an option of `corex/stat` and the attribute `data-corex-count-up` any block may print.
- **Filter state**: the address's `filter-<id>-*` values for one Query Loop.

## Design first

The Figma frames of the Blackstone site set what the blocks must be able to show; the CoreX look of each visible
part is drawn on the CoreX design system in Claude Design and approved by the owner before its slice is built
(`AGENTS.md`, "UI, UX and front end"). Each approval is recorded in `design/handoffs/spec-149-<name>.md`. Every
board is drawn at 375, 782 and 1280px, light and dark, left to right and right to left (Arabic), and names each
value as its FR-084 token with the default (FR-086): the board sets defaults, a client theme may change any of them.

| Design | What is drawn | Slice that waits |
|---|---|---|
| 149-D1 | Carousel controls: arrows (44px) and dots (24px targets) placed below and at the sides, Pause and Play, the disabled end arrow, the touch state (dots only), the scripts-off row, part of the next slide, the edge fades, a long caption, a carousel of records and of logos | 3 |
| 149-D2 | The ticker: the moving strip with edge fades, the Pause and Play button's place and look over a light and a dark band, the still state under reduced motion and with scripts off (wrapped or scrolling), the three speeds | 4 |
| 149-D3 | Motion: reveal's start and end states (opacity, distance), duration, easing and the step between inner blocks, the share in view that starts it; count-up's duration and easing; both written as tokens | 5, 6 |
| 149-D4 | Filters: the bar with a dropdown of checkboxes ("2 selected", Clear inside), the chips and tabs forms, the year dropdown, active chips with Remove and Clear all, the results count, the no-results state, Load more and its loading state, the phone's Filters button and sheet, keyboard focus | 7, 8 |

The record picker (slice 1) and the carousel's item sources (slice 2) draw nothing new: the picker is built from
WordPress's own components, and the sources keep the approved kit's carousel. The kit's rule stands: nothing moves
by itself unless a site turns it on, never under reduced motion, always with a control a person can see (#652).

## Success Criteria *(mandatory)*

- **SC-001**: The Blackstone Home sections HOM-2, HOM-4, HOM-5 and HOM-8 can be built from CoreX blocks and site
  patterns alone, with no site script or site CSS standing in for movement, picking or counting.
- **SC-002**: Every US-BLK-03 criterion this spec takes and every US-BLK-07 criterion passes a browser test.
- **SC-003**: Every page in the quality suite that uses reveal, count-up, a carousel or a ticker keeps a layout
  shift of 0 at 375 and 1280px and passes the accessibility checks with no new violation.
- **SC-004**: With scripts off, every list, carousel, ticker and figure shows all its content, and every filter
  works by reloading the page.
- **SC-005**: A non-technical editor can pick records, build a carousel of records, a ticker of logos and a
  filtered list in the editor's canvas without code, each shown in the canvas as on the page.
- **SC-006**: A client theme that sets only `theme.json` tokens and block styles changes every value listed in
  FR-084, proved by the fixture test of FR-085, with no CSS that overrides a CoreX selector.

## Client impact

- **Muva** uses `corex/projects` in its `section-selected-work` pattern and `corex/stat` through `corex/stats`.
  Saved and patterned copies render as today; `corex/projects` leaves the inserter. Count-up and reveal are off
  unless an editor turns them on. No step is needed.
- **Perego** has the same two patterns and corex-ui active. As for Muva. A carousel on its pages, if any (none in
  its theme or site code), keeps its slides and gains the Pause button, the larger controls and fewer slides on a
  phone, as US-BLK-03 requires; each is named in slice 3's changelog lines. No step is needed.
- **Mizzey** is not on this line.

## Assumptions

- WordPress 7.0's Query Loop, Gallery, Image "Expand on click" and `query_loop_block_query_vars` behave as in 6.x;
  slice 1 measures the editor preview path (FR-004) before building it.
- The tablet and desktop widths are the theme's layout widths; CoreX adds no breakpoint of its own.
- Chips, the dropdown and the sheet are built on the approved visitor kit's components where they exist.
- The site's story, event and job post types and their taxonomies exist before its list pages are built (site work
  and spec 151); the filter blocks need only a post type and a taxonomy.

## Out of scope

- Card patterns and the bento layout of the Blackstone site (its own patterns), the coverflow style (a site style
  on FR-022's classes), card hover effects.
- `corex/jobs` and the jobs list (spec 141), the content model and generators (spec 151), the visible breadcrumb
  trail (spec 150), language adapters for chosen ids (spec 148), search and sort on a list.
- The header's scroll distance (spec 137 slice 6), the hero's headline scenes (a Blackstone block that takes FR-045).
- A tag, and the line `release/gap-line`: the coordinating session's.

## Slices

One pull request each, test first, merged into `main` on green. "Rests on" names what `main` gained after
`v0.48.1` that the slice needs, for the cherry-pick to the line (#730).

| Slice | What | Requirements | Waits on | Principles | Rests on |
|---|---|---|---|---|---|
| 0 | This spec, DECISIONS #722 to #731, the gap table rows | | | X | nothing |
| 1 | The record picker; `corex/posts` and `corex/projects` retired with their transform; the `corex/news` pattern | FR-001 to FR-008, FR-090 to FR-094 | 0 | IV, VI, IX | spec 136 slice 3 (#506, the editor kinds and parity gate), carried in wave 1 |
| 2 | Carousel items from a Query Loop or a Gallery; lazy images; the larger view | FR-010 to FR-013, FR-080 to FR-085 for the carousel | 1, spec 136 slice 7 on `main` | VI, VIII | spec 136 slice 7 (`corex/slide`), carried in wave 1 |
| 3 | Carousel sizes, controls, loop, timing, edge fade, scripts off, touch, right to left, saved carousels | FR-020 to FR-029, FR-080 to FR-085 for the carousel | 2, 149-D1 approved | V, VI, VIII | slice 2; a new token in `theme/theme.json` is placed by hand on the line beside the tag's tokens (#701) |
| 4 | The ticker | FR-030 to FR-034, FR-080 to FR-085 for the ticker | 3, 149-D2 approved | V, VI, VIII | slice 3 |
| 5 | Count-up on `corex/stat` and its documented attribute | FR-040 to FR-046, FR-080 to FR-085 for count-up | 149-D3 approved | V, VI | spec 136 slice 3 (stat's parity fixture); motion tokens as slice 3 |
| 6 | Reveal as a block support | FR-050 to FR-055, FR-080 to FR-085 for reveal | 149-D3 approved | V, VI | nothing past wave 1; motion tokens as slice 3 |
| 7 | Filters that work as a form: the bar, the three filters, chips, Clear all, no results, the address, `noindex` | FR-060 to FR-065, FR-068, FR-080 to FR-085 for the filters | 1, 149-D4 approved | III, VI, VII, VIII | slice 1 |
| 8 | Filters in the browser: no reload, the count announced, Load more, the phone sheet, core's page links | FR-066, FR-067, FR-069, FR-080 to FR-085 for the sheet and Load more | 7 | VI, VIII | slice 7 |

Slices 1 and 2 draw nothing new and are built while the drawings wait for the owner; 5 and 6 may be built in either
order after 149-D3. Slices 2 to 8 change what a visitor may see and run the full UI steps of `AGENTS.md`. Every
slice runs the Guard Gate, `corex-reviewer` and the two-model review before its pull request.

**For the line (#730).** Measured on 2026-11-23: since `v0.48.1`, `main` changed the carousel, posts and stat
blocks only through wave 1 (spec 136 slices 1, 3 and 2a: seven files, 95 lines). So every slice can be carried
once wave 1 is, with the one `theme/theme.json` risk #701 names. `v0.50.0` holds slices 1 to 6; slices 7 and 8
join it if they are on `main` when 1 to 6 are, and otherwise ship with the next tag.
