# Feature Specification: The editor standard for blocks

**Feature Branch**: `spec/136-block-editor-standard` (the spec), then one branch per slice

**Created**: 2026-11-21

**Status**: Draft

**Input**: The Blackstone gap programme, wave 1 (DECISIONS #651, #652, #656). Gaps G-005, G-003 (part one), G-004
and G-011 of the Blackstone eIT site's fit check, decided by the owner in that site's grill of 2026-11-21
(its decisions 4 and 18). Planning workspace stories US-BLK-01, US-BLK-02, US-BLK-03, US-BLK-06 and US-BLK-14,
whose acceptance criteria are used here as written.

## Why this spec exists

The owner's first rule for a client site is that a page shows its real design in the editor and is edited there.
CoreX's blocks do not meet it, in three ways that were measured on `main` on 2026-11-21.

### What was measured

- **No block prints what the editor sets on it.** 26 classes implement `Corex\Blocks\BlockRenderer` and each
  writes its own root element by hand. `get_block_wrapper_attributes()` is called nowhere, so the HTML anchor,
  the alignment and the extra CSS class an editor sets never reach the page, although eight blocks declare
  `anchor` or `align`. No block declares colour or spacing support.
- **Every renderer is called from one place.** `DynamicBlockRegistrar::renderCallback()`
  (`plugins/corex-blocks/src/DynamicBlockRegistrar.php`) resolves the renderer from the container and returns its
  markup for all 30 `block.json` blocks. No renderer prints an `id` on its root element.
- **18 of 30 blocks preview by server render**, static ones included. Of the 16 blocks the Blackstone site uses,
  ten do: carousel, drawer, modal, breadcrumbs, copyright, posts, `corex/form`, newsletter signup, social share
  and jobs. Their text is typed in the side panel.
- **The six blocks edited in place do not match their front end.** Hero is a `div` in the editor and a `section`
  on the page; hero and cta wrap the button in an extra element and draw it as a `span`; tabs show stacked rows
  in the editor and radio inputs on the page. No test compares the two.
- **`make:block` generates a server-rendered preview**, and `packages/build-tools/README.md` states that as the
  rule for every block.
- **No block holds other blocks.** `InnerBlocks` appears nowhere. A tab, an accordion answer, a slide, a modal
  and a drawer hold one line of formatted text.
- **The hero** has one button, centred text, and a background image that is always `loading="lazy"`, so the
  largest image of the first screen starts late.
- **WordPress.** CoreX's minimum is 7.0. `core/accordion` exists since 6.9, the server side of the `anchor`
  support since 7.0, `core/tabs` since 7.1. CI installs the latest release.

### Decisions this spec settles (DECISIONS #670 to #675)

1. **Wrapper attributes are applied in one place** (#670). The registrar's render callback merges what
   `get_block_wrapper_attributes()` returns into the root element of every renderer's markup. A renderer cannot
   forget it, a generated block gets it, and the 26 renderers and their unit tests do not change.
2. **Forms keep their bytes** (#670). The six form and flow blocks print no generated class, so their markup is
   byte for byte today's until an editor sets an anchor, a class or a spacing value. Perego adapts that markup
   by exact strings.
3. **What parity means and how it is tested** (#671): the section "The parity contract" below.
4. **The accordion stays CoreX's own** (#672). The planning decision BLK-D1 says "WordPress's Accordion now",
   and DECISIONS #652 says to style WordPress's block where it does the job. It does not do this one:
   US-BLK-02 requires a question to open and close from the keyboard with scripts off, which `corex/accordion`
   does today because it is `<details>`, and which `core/accordion` cannot do because its questions are buttons
   driven by a script. CoreX's block also migrates its own saved content when opened, where a move to
   WordPress's block would need an editor to convert each one.
   Breadcrumbs is the other half of BLK-D1: that choice is made with G-014 in spec 144; here the block only
   gains its wrapper attributes. Tabs stay CoreX's until CoreX's minimum is WordPress 7.1, as BLK-D1 says.
5. **`corex/form` is a record block** (#673). It shows one record, the form, whose fields are edited in the form
   builder or in code. It keeps a server render.
6. **What is left to other specs** (#674). Of US-BLK-03 (carousel) this spec takes the first criterion without
   its larger view, and the last; the larger view of an image, the widths per screen, the control sizes, the
   Pause button, the scripts-off state, the touch state, lazy images, the right-to-left criterion and the ticker
   go with G-010 in spec 149. US-BLK-09 (a video in a dialog) stays in its release; the modal holding blocks is
   what it will be built on. The trail of the breadcrumbs is G-014 (spec 144). Width, height and responsive
   sizes for team and gallery images are G-016 (spec 150). Of the G-011 row's wishes, child blocks inside the
   hero and a slideshow background are not taken: the owner's decision 18 lists the scope and names neither.
7. **A container block keeps its name and migrates its content** (#675): the section "Blocks that hold blocks".

## The parity contract

**Kinds.** Every block declares its kind in `block.json`, key `corex.editor`:

- `in-place`: a static block. The editor draws the block with the same elements as the front end and its text is
  typed on the canvas. It makes no request to the server to draw itself.
- `records`: a block that shows records it does not hold (a list of posts, a trail, a form). It keeps a server
  render in the editor, inside the block's own wrapper, and the preview cannot be clicked or submitted.
- `none`: a block with no editor (`corex/assistant`).
- `pending`: a block whose turn has not come. Only the blocks the gate names may declare it.

**The comparison.** For a fixture with every field filled, the tree of elements that carry a class of the block,
with each element's tag, its block classes and its nesting, is the same in the editor as in the front end
markup. A class of the block is one that is the fixture's `prefix` or starts with it followed by `__` or `--`
(`corex-hero`, `corex-hero__title`, `corex-hero--split`); a child block prints its parent's prefix. An element
the editor needs and the page does not (a remove button, a link field) carries `data-corex-editor-only` and no
block class. An element the page needs and the editor does not (a trap field against robots) is named in the
fixture's `pageOnly` list. Classes that are not the block's (`is-selected`, `wp-block`, WordPress's own) are
ignored. Where a block holds other blocks, the element that holds them is compared and what the child blocks
draw is compared through their own fixtures.

**The fixture.** Each `in-place` block has a `parity.json` beside its `block.json`: the class `prefix`, the
`attributes` to draw with, the `innerBlocks` when the block holds blocks, what the page `needs` to draw it (a
current post), the optional `pageOnly` list, and the expected `tree`.

**The two tests.** Neither language reads the other; each compares its tree with the fixture's.
- PHP, in the integration suite: every fixture is drawn through WordPress (`render_block`), so the renderer comes
  from the container with its real collaborators and inner blocks are real blocks.
- JavaScript, in Jest: every fixture is drawn by rendering the block's `edit` to markup with `react-dom/server`
  and one shared set of stand-ins for the `@wordpress/*` packages, kept in `packages/build-tools` beside the
  helper. A stand-in draws what the real component draws that matters here: a text field as its tag and class,
  inner blocks as the element that holds them.

**The gate.** A test reads every `block.json` in the repository. It fails when a block declares no kind, when an
`in-place` block has no fixture or imports the server render, when a `records` block does not use it, or when a
block outside the waiting list declares `pending`. That list is in the test and may only get shorter. It starts,
in slice 3, as the 13 blocks of spec 150 and the eight wave 1 blocks not yet brought over (copyright, newsletter
signup, social share, tabs, accordion, carousel, modal, drawer); each later slice takes its own blocks off it,
and when this spec is done it holds the 13.

| Kind after this spec | Blocks |
|---|---|
| `in-place`, wave 1 | hero, cta, stat, team, tabs, accordion, carousel, modal, drawer, copyright, newsletter signup, social share, and the three child blocks (tab, accordion item, slide) |
| `records`, wave 1 | posts, jobs, breadcrumbs, `corex/form` |
| `pending`, for spec 150 | alert, badge, gallery, pricing, testimonial, entity field, project meta, projects, flow, cta flow, subscribe, success message, survey |
| `none` | assistant |

`corex/account` (corex-profile) is registered in code with no `block.json`, so it has no kind; it receives the
wrapper attributes in slice 1 and its editor is spec 150's.

## Blocks that hold blocks

Tabs, accordion and carousel gain a child block each (`corex/tab`, `corex/accordion-item`, `corex/slide`); modal
and drawer hold blocks directly. The rules are the same for all five:

- The block keeps its name. Its saved content becomes its inner blocks; the label of a tab and the question of an
  accordion item are attributes of the child.
- **Content saved in today's shape still renders, and nothing about how it behaves changes.** A block with no
  inner blocks and a filled `tabs`, `items`, `slides` or `content` attribute is drawn from that attribute with
  the same labels and text. For accordion, carousel, modal and drawer the markup is the new one, which behaves as
  today's. Tabs are the exception, because the new tabs need a script and today's need none: tabs saved in the
  old shape are drawn exactly as today, radio inputs and no script, until an editor converts them
  (DECISIONS #675, #651 point 5).
- **Opening it in the editor migrates it**, for accordion, carousel, modal and drawer. A deprecation, marked
  eligible for a block that has the old attribute and no inner blocks, turns each old item into a child block
  holding a paragraph with the old text; the old attribute is emptied on save.
- **Old tabs are converted by a button, never by themselves.** The editor shows tabs in the old shape as they
  are, with a notice that says what converting changes and a "Convert" button that does what the deprecation
  does for the others. Saving the page for another reason leaves them as they were.
- Every new setting defaults to today's behaviour: one-open off, no heading level (the question is plain text in
  its `<summary>`, as today), advancing off.
- The renderer stays the source of the markup: the block saves its inner blocks only.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - What an editor sets on a block reaches the page (Priority: P1)

An editor gives a hero the anchor `services`, sets a carousel to full width and adds the class `home-clients` to a
stat. The page prints all three, and a link to `#services` scrolls to the hero.

**Why this priority**: The Blackstone section dock scrolls to anchors on 14 pages, and every other story's wrapper
rests on it.

**Independent Test**: Render each block through WordPress with an anchor, an alignment where the block has one and
a class; read the root element.

**Acceptance Scenarios**:

1. **Given** any CoreX block a person can insert (but a tab, whose fragment is its `id`), with the anchor `about`,
**When** the page is rendered by WordPress, **Then** the
   block's root element has `id="about"`.
2. **Given** a block that supports alignment set to full width, **When** rendered, **Then** its root has
   `alignfull`.
3. **Given** a block with the extra class `home-clients`, **When** rendered, **Then** its root has that class
   beside its own.
4. **Given** a form or flow block with nothing set, **When** rendered, **Then** its markup is byte for byte what
   `main` printed before this spec.
5. **Given** a block whose renderer returns nothing, **When** rendered, **Then** nothing is printed.
6. **Given** a section block with a background colour, a text colour, a padding and a margin set, **When**
   rendered, **Then** the page shows them and the block's text takes the chosen colour.
7. **Given** a section block with the Dark band style, **When** rendered under the CoreX theme, **Then** it is
   drawn as the dark band.

### User Story 2 - The editor shows the page (Priority: P1)

An editor opens a page and sees each static block as it will be published, types its text where it stands, and
never types a headline in the side panel.

**Why this priority**: It is the owner's rule (decision 4), and the reason the gap is High.

**Independent Test**: The two parity tests and the gate; then, in the editor, each wave 1 block typed in place.

**Acceptance Scenarios**:

1. **Given** hero, cta, stat and team, **When** the parity tests run, **Then** each block's tree is the same in
   the editor and on the page.
2. **Given** copyright, newsletter signup and social share, **When** inserted, **Then** each is drawn in the
   canvas without a request to the server, and the newsletter's heading, button label and consent text are typed
   in place.
3. **Given** posts, jobs, breadcrumbs and `corex/form`, **When** inserted, **Then** each shows its server render
   inside the block's wrapper, and a click or a submit in the preview does nothing.
4. **Given** `corex/form` with no form chosen, **When** inserted, **Then** the canvas asks which form, in the
   block, and the page prints nothing.
5. **Given** a new block that declares no kind, or an `in-place` block with no fixture, **When** CI runs, **Then**
   it fails and names the block.

### User Story 3 - A generated block is edited in place (Priority: P1)

A developer runs `wp corex make:block team-card`. The block that comes out is edited in place, has its parity
fixture, and passes the gate without a line written by hand.

**Independent Test**: Generate a block into a scratch folder; build it; run the parity tests and the gate on it.

**Acceptance Scenarios**:

1. **Given** `make:block` with no option, **When** it runs, **Then** the block declares `in-place`, its `edit`
   draws the renderer's elements with one text field typed on the canvas, and no server render is imported.
2. **Given** `make:block --records`, **When** it runs, **Then** the block declares `records` and previews by
   server render inside its wrapper.
3. **Given** either shape, **When** the stub's style sheet is read, **Then** every value is a token and every
   property is logical.
4. **Given** the two READMEs, **When** read, **Then** they state the rule of DECISIONS #656 and neither says a
   server-rendered preview is the rule.

### User Story 4 - An accordion answer holds any content and can open one at a time (Priority: P1)

US-BLK-02, its criteria as written:

1. An answer can hold any blocks (paragraphs, lists, images, buttons).
2. An "Open one at a time" setting closes the open item when another opens; it is off by default.
3. Each question shows a chevron that turns when open and is mirrored in right-to-left pages; the browser's
   triangle is not shown.
4. Questions open and close with Enter and Space and are announced as expanded or collapsed, with scripts on and
   off.
5. The editor can choose the questions' heading level so they fit the page outline.
6. Accordions saved before the change keep their questions and answers.
7. The FAQ pattern (US-KIT-07) uses the same accordion.

**Independent Test**: The story's test cases TC-BLK-02-01 to 06, with scripts on and off, in English and Arabic.

### User Story 5 - Tabs hold any content and behave as tabs (Priority: P1)

US-BLK-01, its criteria as written:

1. A panel can hold any blocks (cards, a pricing group, a table, a form, an FAQ); editors add, reorder and remove
   tabs in the editor.
2. A screen reader hears a tab list, each tab with its selected state, and each panel named by its tab.
3. Left and Right arrows move between tabs (reversed in right-to-left pages), Home and End go to the first and
   last, and Tab moves from the selected tab into its panel.
4. Each tab has a stable address fragment: a link with it opens that tab, and choosing a tab updates the address
   without adding a history step.
5. Below the navigation breakpoint the tabs show as an accordion with the same content, one section open at a
   time.
6. With scripts off, every panel is shown under its tab's title, in order.
7. Two tab blocks on one page work independently.
8. Tabs saved before the change keep their labels and text.

And, from G-004 and DECISIONS #652:

9. An "Advance by itself" setting, off by default, moves to the next tab after a number of seconds the editor
   sets. While it is on, a Pause button is shown beside the tabs; pressing it stops the movement and it becomes
   Play. Movement also stops while the pointer or the focus is inside the block and while the page is hidden. It
   never starts under reduced motion, and then no Pause button is shown. The button is added by the script, so
   with scripts off there is none. Advancing does not run while the tabs are shown as an accordion on a phone,
   and it does not change the address.

**Independent Test**: The story's test cases, then criterion 9 with a clock the test controls.

### User Story 6 - A slide, a modal and a drawer hold any content (Priority: P2)

1. US-BLK-03's first criterion, as written: "A slide can be a testimonial (quote, name, role, photo or initial),
   an image (with its alternative text, opening a larger view), a logo, or a project card; a text slide still
   works". Here a slide holds any blocks, which gives all of these but the larger view of an image; that part,
   and the story's test case for it, go to spec 149.
2. US-BLK-03's last criterion, as written: "Carousels saved before the change keep their slides".
3. The carousel is drawn in the editor as on the page, one slide after another, and each slide is edited where it
   stands.
4. A modal and a drawer hold any blocks: a heading, a list, an image, a form, a video. What is placed in them is
   no longer removed when the page renders.
5. In the editor the trigger and the panel are both drawn in place and the panel's content is edited there; on
   the page the panel opens from its trigger as today.
6. Closing a modal or a drawer stops a video or a sound playing inside it: a media element is paused, and an
   embedded player in a frame is reset.
7. Modals and drawers saved before the change keep their title, their button label and their text.

**Independent Test**: Insert each with an image, a list and a button inside; publish; open and close with the
keyboard; then load a page saved before the change.

### User Story 7 - A hero with media, two actions and its text at the start (Priority: P2)

US-BLK-06, its criteria as written:

1. The hero offers a Centred and a Split layout; Split puts the image or video at the end of the line (the left in
   Arabic) and stacks it under the text on a phone.
2. A second action can be added as an outline button or a text link; each action renders only when its text and
   link are set.
3. An optional short label (for example "v1.0 now open source") can sit above the title.
4. Title, text and both actions have contrast of at least 4.5:1 (text) and 3:1 (buttons) in light and dark.
5. Heroes saved before the change keep their layout and button.

US-BLK-14, the criteria that concern the hero, as written:

6. A hero's image starts loading with the page and is marked high priority.
7. With images delayed on purpose, no layout shift is measured from the hero.
8. An image chosen from the media library carries its width, height and responsive sizes.
9. WebP delivery by Corex Media keeps working.

And, from G-011 and the Blackstone decision 18:

10. In the Centred layout the media is behind the text, as the image is today. A scrim can be put between them:
    none (the default, which is today's drawing), light, medium or strong. Criterion 4 holds at light, medium
    and strong; with media behind and no scrim the editor says the text may not be readable.
11. In the Centred layout the text can be aligned to the centre (the default) or to the start of the line. In
    Split it is at the start.
12. Only the first hero on a page is marked high priority; a later one loads lazily. The first hero also tells
    WordPress the page's priority is taken, so no later image claims it.
13. The media, behind the text or beside it, can be a video with a poster. The poster is an image, is what is painted
    first, and is the
    largest element measured. The video is silent, loops, plays inline, may be given as WebM with an MP4
    fallback, and is not requested until the page has finished loading. It is never requested under reduced motion,
    Save-Data or a slow connection: the poster
    stays. It is an option, off until a video is chosen.
14. While a video plays, a Pause button is shown on the hero; pressing it stops the video and it becomes Play.
    With scripts off no video and no button is drawn.

**Independent Test**: The stories' test cases; then, with a throttled connection and with reduced motion, the
network log shows no request for the video; the LCP entry is the poster.

### Edge Cases

- A renderer's markup starts with a notice before its root (the form on a test site): the attributes go on the
  block's root, not on the notice. The form block has two roots, by its source: the flow's section or the form.
- A renderer prints only a notice (a paused form): the attributes go on the first element.
- An anchor that is already an `id` elsewhere on the page: the block prints what the editor set; uniqueness is the
  editor's, as in WordPress.
- A tab with no label, an accordion item with no question, an empty slide: skipped on the page as today, shown in
  the editor so it can be filled.
- Two tabs with the same label, in one block or in two: their `id`s still differ, because an `id` that is
  already on the page gets a number added when the page is drawn. A fragment keeps the letters of any script, so
  an Arabic label gives an Arabic fragment.
- A link to a tab's fragment inside a tab of another block on the same page.
- A container block inside itself (tabs in a tab, an accordion in a modal): each works on its own.
- A hero with a video and no poster: the editor says a poster is needed and the page draws no video.
- A hero video that fails to load: the poster stays and no Pause button is drawn.
- A block saved in today's shape and never opened again: drawn from its old attribute for as long as it exists.
- Right to left: tab arrows, the chevron, the Split hero and the drawer's side follow the page direction.

## Requirements *(mandatory)*

### Wrapper attributes (G-005)

- **FR-001**: The markup of every block registered through `DynamicBlockRegistrar` MUST carry, on its root
  element, what `get_block_wrapper_attributes()` returns for that block: the anchor as `id`, the alignment class,
  the editor's extra classes, the generated class, and the classes and inline styles of its supports.
- **FR-002**: The merge MUST happen in one collaborator injected into the registrar, not in each renderer. A class
  is added to the root's classes, a style is appended to the root's style, and an attribute the root already has
  is kept. Markup that is empty stays empty.
- **FR-003**: A block MAY name its root in `block.json` (`corex.root`, a list of classes) when its markup does
  not always start with it; the attributes then go on the first element, in document order, that carries one of
  those classes as a whole class, and on the first element of the markup when none does.
- **FR-004**: Every block a person can insert MUST declare `anchor`, but for `corex/tab`, whose fragment is its
  `id`. `corex/account`, registered in code, MUST pass through the same collaborator. The section blocks (hero, cta,
  team, posts,
  gallery, tabs, accordion, carousel, newsletter signup, jobs, projects, testimonial, pricing) MUST declare
  `align` wide and full.
- **FR-005**: The six blocks of `plugins/corex-forms` MUST declare `className: false`, so that with nothing set
  their markup is unchanged, and a test MUST prove it by comparing what WordPress prints for each with what its
  renderer returns, in the same run.
- **FR-006**: A test in the integration suite MUST render a block through WordPress (`do_blocks`) and prove that
  an anchor, an alignment and a class reach the page, and a second MUST do so for the anchor of every CoreX
  block a person can insert.
- **FR-006a**: For the editor's preview request (WordPress's block renderer route) the markup MUST be returned
  without the merge: the editor's own wrapper already carries the attributes, and they are not printed twice.
- **FR-007**: Every block a person can insert MUST support a top and bottom margin. The section blocks, stat and
  alert MUST also support padding, declared by axis (vertical, horizontal), so the two sides of a line are always
  equal and no physical side is set alone (constitution VIII).
- **FR-008**: The section blocks and stat MUST support a background colour and a text colour, and each one's
  style sheet MUST let a chosen text colour reach its headings and text.
- **FR-009**: The quiet, dark and brass band styles MUST be offered on the section blocks under a theme that can
  draw them, from the same sheet and the same rule that decides it for the Group block (DECISIONS #642).

### The standard (G-003)

- **FR-010**: Every `block.json` MUST declare `corex.editor` as `in-place`, `records`, `none` or `pending`.
- **FR-011**: The parity helper and the shared stand-ins MUST live in `packages/build-tools` for JavaScript and
  in the framework's test support for PHP, and both helpers MUST compute the tree the contract describes.
- **FR-012**: One PHP test, in the integration suite, and one JavaScript test MUST cover every `parity.json` of
  the framework, found by search, so a new framework block is covered without a new test. A client's blocks
  under `sites/` are the client's to test (they are hidden from the framework's suites): the helper is exported
  for the client package that spec 135 generates.
- **FR-013**: The gate test MUST fail as the contract describes, with a message that names the block and the rule.
- **FR-014**: `make:block` MUST generate the `in-place` shape by default and the `records` shape with `--records`,
  each with its kind declared; the `in-place` shape MUST include its `parity.json`. The starter's example block
  (`packages/cli/stubs/starter/`) MUST declare its kind and pass its markup through the same collaborator.
- **FR-015**: The generated style sheet MUST use tokens and logical properties only, and the generated strings
  MUST go through the translation functions with the configured text domain.
- **FR-016**: `packages/build-tools/README.md`, `packages/cli/README.md` and the scaffolder's own description MUST
  state the rule of DECISIONS #656.
- **FR-017**: Hero, cta, stat and team MUST meet the contract: the same root tag as the page, the button as a link
  element with its address field marked editor-only, and no extra wrapper that carries a block class.
- **FR-018**: Copyright, newsletter signup and social share MUST be `in-place`. The newsletter's form MUST NOT
  submit in the editor.
- **FR-019**: Posts, jobs, breadcrumbs and `corex/form` MUST be `records`: the server render inside the block's
  wrapper, inert, with a named loading state and a named empty state. Those states and the form chooser are
  WordPress's own Placeholder, Spinner and Notice components as WordPress draws them, so CoreX draws nothing
  new in the editor; their words are reviewed.
- **FR-020**: `corex/form` MUST ask for its form on the canvas when none is chosen, and the change to it MUST stay
  in the block's editor files and its `block.json`. Its front end markup MUST NOT change.

### Blocks that hold blocks (G-004)

- **FR-030**: `corex/accordion` MUST hold `corex/accordion-item` blocks; an item has a question (text, typed in
  place) and holds any blocks. The markup stays `<details>` and `<summary>`.
- **FR-031**: The accordion MUST offer "Open one at a time", off by default, done with the `name` attribute of
  `<details>` so it holds with scripts off.
- **FR-032**: The accordion MUST offer the questions' heading level (none by default), and MUST draw the kit's
  chevron, turned when open and mirrored right to left, with the browser's marker hidden. A test MUST read the
  accessibility tree for a question with a heading level: a heading of that level is in it, and the question is
  still announced as a control that is expanded or collapsed.
- **FR-033**: `corex/tabs` MUST hold `corex/tab` blocks; a tab has a label (typed in place) and a fragment (made
  from its label, kept when the label changes, editable), and holds any blocks. The fragment keeps the letters
  of any script and is the `id` of the tab's panel; an `id` already printed on the page gets a number added.
- **FR-034**: The tabs MUST meet criteria 2 to 7 of User Story 5 with one view script loaded only where the block
  is, and MUST print every panel under its title when the script has not run.
- **FR-035**: The tabs MUST offer "Advance by itself" as criterion 9 says. The seconds have a floor of 4.
- **FR-036**: `corex/carousel` MUST hold `corex/slide` blocks, each holding any blocks. Its options, its view
  script and its controls stay as they are.
- **FR-037**: `corex/modal` and `corex/drawer` MUST hold any blocks in their body. Title, button label and side
  stay attributes, typed in place.
- **FR-038**: Closing a modal or a drawer MUST pause a media element playing inside it and reset an embedded
  player's frame.
- **FR-039**: Each of the five MUST render content saved in today's shape, tabs in today's markup and the other
  four in the new one, and MUST migrate it as "Blocks that hold blocks" says: the four when opened in the
  editor, tabs only by their button. A test MUST render one saved example of each old shape, and for tabs MUST
  compare it with today's markup; a test MUST prove that old tabs loaded and saved untouched stay in the old
  shape.
- **FR-040**: The FAQ pattern of corex-ui MUST insert an accordion with items, so it shows questions.

### The hero (G-011)

- **FR-050**: The hero MUST offer the layouts Centred and Split. In Centred the media, when there is any, is
  behind the text, and the text is aligned to the centre or to the start. In Split the media is beside the text,
  at the end of the line, and the text is at the start. Every new setting defaults to today's drawing: Centred,
  centre, no scrim, one action. The short label of criterion 3 is the hero's `eyebrow`, which exists.
- **FR-051**: The hero MUST offer a second action, drawn as an outline button or a text link, and each action MUST
  render only when its text and its address are both set.
- **FR-052**: A hero image chosen from the media library MUST be printed from its attachment, with width, height,
  `srcset` and `sizes`; an image saved as an address alone MUST still be drawn. Corex Media's filter MUST still
  be applied.
- **FR-053**: The first hero rendered on a page MUST print its image with `fetchpriority="high"` and without
  lazy loading, whether or not the image has a width and a height, and MUST mark WordPress's priority for the
  page as taken (`wp_high_priority_element_flag`). A later hero MUST load lazily.
- **FR-054**: Media behind the text MAY be covered by a scrim: none (the default), light, medium or strong. User
  Story 7's criterion 4 MUST hold at light, medium and strong, and the editor MUST warn when media is behind the
  text with no scrim.
- **FR-055**: A video, behind the text or beside it, MUST follow criteria 13 and 14 of User Story 7. The server prints
  the poster and
  the video's addresses (WebM, MP4 or both) as data; the view script adds the video element only when every
  condition allows it.
- **FR-056**: The hero's view script MUST be enqueued by the renderer, only for a hero that has a video.

### Every slice (the gap programme's rules)

- **FR-060**: Every string a person reads MUST go through the translation functions with the plugin's text
  domain, in PHP and in scripts.
- **FR-061**: Styles MUST use `theme.json` tokens and logical properties only; a block's assets MUST load only
  where the block is.
- **FR-062**: Each slice's pull request MUST say whether it rests on anything `main` gained after `v0.48.1`.
- **FR-063**: Each slice MUST add its `### Client impact` lines to `CHANGELOG.md` under `[Unreleased]`, in the
  sub-list "Gap programme, wave 1", naming Muva, Perego and Mizzey.

### Key Entities

- **Block kind**: `in-place`, `records`, `none` or `pending`; declared in `block.json`.
- **Parity fixture**: a block's `parity.json`: the class prefix, the attributes and inner blocks to draw with,
  what the page needs, what only the page draws, and the expected tree.
- **Child block**: `corex/tab`, `corex/accordion-item`, `corex/slide`; exists only inside its parent.
- **Legacy shape**: a container block saved with its content in an attribute and no inner blocks.

## Design first

Parity draws nothing new: the editor shows what the page already shows. The accordion, the tabs and the carousel
are built on the components of the approved visitor kit (`design/handoffs/visitor-kit.md`, DECISIONS #639) and
are reviewed against it. The phone state of the tabs is the kit's Accordion as drawn. Three things are seen for the
first time and are drawn on the CoreX design system and
approved by the owner before they are built. Each approval is recorded in `design/handoffs/spec-136-<name>.md`.

| Design | What is drawn | Slice that waits |
|---|---|---|
| D-1 | Tabs with "Advance by itself" on: the Pause and Play button and its place, at desktop and phone width | 6b |
| D-2 | A modal and a drawer holding a heading, a list, an image and a form; the drawer itself, which the kit does not have; phone width | 8 |
| D-3 | The hero: Centred and Split, text at the start, media behind with each scrim strength, the second action as outline and as link, the video's Pause button, phone width, Arabic | 9 |

The kit's rule stands in all three: nothing opens, moves or plays by itself unless a site turns it on, never under
reduced motion, always with a control a person can see (DECISIONS #652).

## Success Criteria *(mandatory)*

- **SC-001**: For every CoreX block a person can insert but `corex/tab` (the 29 of today, the accordion item and
  the slide when they exist, and `corex/account`), an anchor set in the editor is the `id` of the block's root.
- **SC-002**: The six form and flow blocks, with nothing set, print markup identical to `main` before this spec.
- **SC-003**: When the spec is done: every `block.json` declares a kind; the 12 wave 1 `in-place` blocks and the
  three child blocks pass both parity tests; exactly 13 blocks declare `pending`, none of them a wave 1 block.
- **SC-004**: A block generated by `make:block` into a scratch folder of the framework builds and passes the
  parity tests and the gate untouched.
- **SC-005**: Every test case of US-BLK-01, US-BLK-02 and US-BLK-06 passes; of US-BLK-03, the cases for a slide's
  content (without the larger view) and for saved carousels; of US-BLK-14, the cases for the hero.
- **SC-006**: One saved example of each of the five old container shapes renders with the same labels and text,
  and the old tabs example renders the markup it renders today.
- **SC-007**: On a throttled phone profile the LCP element of a page whose hero has a video is the poster, and
  with reduced motion, Save-Data or a slow connection the network log holds no request for the video.
- **SC-008**: `npm run test:quality` passes for every slice a person sees, in light, dark and right to left, at
  375px and at 200% zoom.

## Client impact

- **Muva** runs no corex-ui block. Its forms print the same bytes (FR-005). The newsletter signup and social
  share blocks of corex-blocks, where it uses them, gain the class `wp-block-corex-<name>` on their root, and an
  alignment or a class already saved on one now reaches the page. No step is needed.
- **Perego** has corex-ui active. Every CoreX block that is not a form gains the class `wp-block-corex-<name>` on
  its root (corex-ui, corex-blocks, careers, portfolio, the assistant), and an anchor, an alignment or a class
  already saved on a block now reaches the page. Tabs, accordions, carousels, modals and drawers already on its
  pages keep their content and behave as today; an accordion's marker becomes the kit's chevron. A tabs block
  becomes scripted tabs only when an editor presses its "Convert" button. Its form markup and messages do not change. No
  step is needed.
- **Mizzey** is not on this line.

## Assumptions

- A site's theme draws `alignwide` and `alignfull`; the CoreX theme does.
- Every current browser supports the `name` attribute of `<details>`; where one does not, items simply stay open
  together, which is today's behaviour.
- "A slow connection" is what the browser reports (`navigator.connection`: `saveData`, or an effective type of
  `2g` or `slow-2g`); a browser that reports nothing is treated as fast, and reduced motion still applies.
- The client supplies the hero footage; the block works with a poster alone.
- `corex/account` is registered outside `DynamicBlockRegistrar` and has no `block.json`; it is not one of the 30.
  It receives the wrapper attributes here (FR-004); its editor is spec 150's.

## Out of scope

- The other 13 blocks' editors (spec 150), the record picker and the list blocks' content (spec 149), the
  carousel's widths, controls and ticker (spec 149), the breadcrumb trail (spec 144), reveal on scroll and
  count-up (spec 150).
- The site stubs (`packages/cli/stubs/site/`, spec 135) and the header patterns and navigation files of the theme
  (spec 137).
- A tag, and the line `release/gap-line`: the coordinating session's.

## Slices

One pull request each, test first, merged into `main` on green. "Rests on" names what `main` gained after
`v0.48.1` that the slice needs, for the cherry-pick to the line.

| Slice | What | Requirements | Waits on | Principles | Rests on |
|---|---|---|---|---|---|
| 0 | This spec, DECISIONS #670 to #675, the gap table rows | | | X | nothing |
| 1 | Wrapper attributes reach the page | FR-001 to FR-006a | 0 | IV, VII | each forms `block.json` (one line each) and corex-profile's `ProfileServiceProvider.php` changed after the tag: the cherry-pick may need a hand merge there |
| 2a | Margin, padding and colour | FR-007, FR-008 | 1 | V, VIII | nothing |
| 2b | The bands on the section blocks | FR-009 | 2a | V | the section bands (spec 134, #640, #642), which the line does not carry: this slice waits for `main` to be released and is not cherry-picked |
| 3 | The standard: kinds, the two parity tests, the gate, `make:block`, the starter's block, the READMEs, and hero, cta, stat, team | FR-010 to FR-017 | 1 | X, V, VIII | nothing |
| 4 | Copyright, newsletter signup and social share in place; posts, jobs, breadcrumbs and the form as records | FR-018 to FR-020 | 3 | VI, VII | nothing (the form: editor files only) |
| 5 | The accordion holds blocks | FR-030 to FR-032, FR-039, FR-040 | 3 | VI, VIII | nothing |
| 6a | Tabs hold blocks and behave as tabs | FR-033, FR-034, FR-039 | 3 | VI, VIII | nothing |
| 6b | Tabs advance by themselves, with Pause | FR-035 | 6a, D-1 approved | VI | nothing |
| 7 | Carousel slides hold blocks | FR-036, FR-039 | 3 | VI, VIII | nothing |
| 8 | Modal and drawer hold blocks | FR-037 to FR-039 | 3, D-2 approved | VI, VIII | nothing |
| 9 | The hero | FR-050 to FR-056 | 3, D-3 approved | V, VI, VIII | nothing |

Slices 1, 3, 4, 5, 6a and 7 show nothing that is not already approved or WordPress's own, and are built while
D-1 to D-3 wait for the owner. Slices 2a, 2b, 5, 6a and 7 change what a visitor may see and run the full UI
steps of `AGENTS.md` against the approved kit. Every slice runs the Guard Gate, `corex-reviewer` and the two-model
review before its pull request.
