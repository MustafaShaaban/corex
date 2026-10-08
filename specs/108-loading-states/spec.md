# Feature Specification: The admin shows what is coming while it loads

**Feature Branch**: `spec/108-loading-states`

**Created**: 2026-10-08

**Status**: Draft, with the readings it is written to under Assumptions.

**Input**: The owner, 2026-10-08: "i also want to add scelaton loader in all calls and loader
inside coreX will take a better effect".

## Why this spec exists

Every CoreX admin screen that asks the server for something has to say so while it waits. Read
at `9302016f`, they say it five different ways, and several do not say it at all:

- **A stock spinner and a sentence**, in three places: the Submissions list, the submission pane,
  and Data records. The spinner is WordPress's own; no CoreX stylesheet touches it.
- **A sentence alone**, in five: Forms, Email Studio, the Notifications list, its preferences,
  and the notification drawer on every screen.
- **Nothing**, where a request is certainly in flight: opening a record in Data (the dialog
  appears when the answer does), the Data export history and the migrations list (both show "no
  history" until the answer says otherwise), choosing a template in Email Studio, the Insights
  widgets (the cards do not exist until they load), and the Insights results (the cards are drawn
  at once looking like finished results that found nothing).
- **The content is thrown away to say "loading"**: Data records and the Notifications list remove
  their table on every page turn, every filter, and, in Data, every letter typed in the search
  box, and put a line of text in its place.
- **Actions disagree**: some buttons show WordPress's striped "busy", some change their label
  ("Saving…"), some are only disabled, and every action in the submission pane (reply, note,
  status, resend, trash) shows nothing and can be pressed again while the first is on its way.

There is no skeleton anywhere in the admin and no shared loading component. A skeleton class
exists for the public theme; it is built on the theme's tokens and is not loaded in the admin.
The admin has two motion tokens, both durations, and one general rule meant to cut every
animation short for a person who asked for reduced motion.

So a person using CoreX cannot tell "still loading" from "nothing here" on five surfaces, loses
their place on two, and can double-submit on one.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — A screen shows the shape of what is coming (Priority: P1)

An operator opens Submissions. Before the rows arrive the table is already there: its columns,
and rows of grey bars where the text will be, moving faintly. When the answer lands the bars
become the rows, and nothing on the page jumps. The same holds wherever CoreX waits for content
it will draw: a list shows rows, a pane shows its header and sections, tiles show tiles, a dialog
opens at once and fills.

**Why this priority**: It is what was asked for, and it is where the admin is weakest: five
surfaces today cannot be told from empty ones.

**Independent Test**: With the server's answer held back, open each surface in the list under
Requirements and see a placeholder in the shape of its content; release the answer and see the
content replace it with no shift of the page around it.

**Acceptance Scenarios**:

1. **Given** a surface that asks the server for its content, **When** it is opened and the answer
   has not come, **Then** a placeholder in the shape of that content is shown where the content
   will be.
2. **Given** a placeholder, **When** the content arrives, **Then** it takes the placeholder's
   place and what surrounds it does not move.
3. **Given** a surface whose content turns out to be empty, **When** the answer arrives, **Then**
   the placeholder gives way to that surface's empty state, and at no point did the surface say
   "nothing here" before it knew.
4. **Given** a request that fails, **When** it fails, **Then** the placeholder gives way to the
   error state with a way to try again, and does not go on moving.
5. **Given** an answer that comes at once, **When** the surface is opened, **Then** no
   placeholder flashes up for an instant first.
6. **Given** a person who has asked their system for reduced motion, **When** a placeholder is
   shown, **Then** it does not move, and still reads as a placeholder.
7. **Given** a person using a screen reader, **When** a surface is loading, **Then** they are
   told once that it is loading and once when it is ready, and the placeholder's bars are not
   read out.

---

### User Story 2 — Refreshing does not throw the page away (Priority: P1)

An operator is on page 2 of Data records and turns to page 3, or types in the search box. The
rows they were looking at stay where they are, visibly waiting, until the new rows replace them.
The page does not collapse to a line of text and build itself again, and the search box does not
lose focus or flicker with each letter.

**Why this priority**: The two surfaces that blank themselves do it on the most frequent
actions in the admin. A skeleton there would make it worse, by flashing on every letter typed.

**Independent Test**: With the answer held back, turn a page, change a filter and type in a
search box on each list; the previous content stays, marked as waiting, and is replaced in
place.

**Acceptance Scenarios**:

1. **Given** a list that is showing content, **When** it asks for different content (a page, a
   filter, a search, a refresh after an action), **Then** the content it has stays in place,
   shown as waiting, until the answer replaces it.
2. **Given** a list that is waiting this way, **When** the operator tries to act on a row,
   **Then** the row does not take the action until the list is current.
3. **Given** a search box, **When** the operator types, **Then** the box keeps focus and its
   text, and the list is asked once the typing pauses, not once per letter.
4. **Given** counts or totals shown beside a list, **When** the list is waiting, **Then** they
   are shown as waiting too, not as the previous numbers presented as current.

---

### User Story 3 — A button says it is working (Priority: P2)

An operator sends a reply from the submission pane. The button they pressed shows that it is
working and cannot be pressed again; the other actions that would conflict wait too. When it is
done the button is itself again and the result is said. Every action in the admin that sends a
request behaves this way, and looks the same doing it.

**Why this priority**: The pane's actions can be double-submitted today, which is a defect
beyond appearance. It comes after the first two because those are what a person sees on every
visit.

**Independent Test**: With the answer held back, press each action; it shows it is working and
a second press does nothing; release the answer and it returns.

**Acceptance Scenarios**:

1. **Given** an action that sends a request, **When** it is pressed, **Then** it shows it is
   working, in one way everywhere, and keeps its width.
2. **Given** an action that is working, **When** it is pressed again, **Then** nothing is sent.
3. **Given** an action that is working, **When** it finishes or fails, **Then** it returns to
   what it was and the outcome is said where the operator is looking.
4. **Given** a screen reader, **When** an action starts and ends, **Then** each is announced.

---

### User Story 4 — CoreX has one loader, and it is CoreX's (Priority: P2)

Where a skeleton does not fit (a button, a small inline wait, a step with a count), the operator
sees one loader throughout CoreX. It is drawn from CoreX's own tokens, in both themes, and is
plainly part of the product, not the browser's or WordPress's default.

**Why this priority**: It is the second half of what was asked, "the loader inside CoreX will
take a better effect". It follows the others because it is the same visual language applied to
the smaller cases.

**Independent Test**: Find every place a loader appears; each is the same component, reads in
both themes and both directions, and stops moving under reduced motion.

**Acceptance Scenarios**:

1. **Given** any place CoreX shows a loader, **When** it is shown, **Then** it is the one CoreX
   loader, not WordPress's spinner.
2. **Given** the dark and the light theme, **When** the loader and the placeholders are shown,
   **Then** each is visible against the surface it sits on, in both.
3. **Given** a wait whose progress is known (an export, an upload), **When** it runs, **Then**
   the progress is shown, not an indefinite loader.

---

### Edge Cases

- A surface is asked for content twice in quick succession: the second answer is the one shown,
  and the surface never shows the first after the second.
- An answer takes very long: the placeholder does not time out into an error by itself; the
  request's own failure decides.
- A placeholder is inside a dialog or a drawer: opening it moves focus as it does today, and
  focus is not lost when the content replaces the placeholder.
- The content that arrives is a different height from the placeholder (two rows, where eight
  were drawn): the surface settles to the content, and what is above it does not move.
- A screen drawn by the server that asks for nothing: it gets no loading state, because it is
  never loading.
- Right-to-left: placeholders mirror with the content they stand for.
- Narrow windows: a placeholder has the layout the content will have at that width.
- Several surfaces on one screen load at once: each shows its own, and the page as a whole is
  not covered.
- A test or a script that waits for a screen to be ready keeps a dependable way to know.

## Requirements *(mandatory)*

### Functional Requirements

**Placeholders (story 1)**

- **FR-001**: Each of these surfaces MUST show a placeholder in the shape of its content while
  its first answer is awaited:

  | Surface | Shape |
  |---|---|
  | Submissions list | table rows |
  | Submission pane | header and sections |
  | Submissions and Data export dialogs: counts, and past exports | the counts; a list |
  | Data records: the rows, the two totals | table rows; two tiles |
  | Data: a record opened from the list | the dialog, opened at once, with field rows |
  | Data: export history; migrations | a list; cards |
  | Forms and flows catalog; opening a flow | list rows; the editor's layout |
  | Email Studio; a template chosen from the list | the panel's layout; the editor form |
  | Notifications list; preferences; the drawer on every screen | cards; rows; compact cards |
  | Insights results and widgets | score cards; widget cards |
  | Setup wizard: its state, and the plan step | the step panel; the plan list |

- **FR-002**: A placeholder MUST occupy the space its content will, closely enough that nothing
  outside the surface moves when the content replaces it.
- **FR-003**: No surface MAY show its empty state before its first answer has arrived.
- **FR-004**: A placeholder MUST NOT appear for an answer that arrives within a short, stated
  delay.
- **FR-005**: A failed request MUST replace the placeholder with the shared error state, with a
  way to try again where the surface can.
- **FR-006**: A request that fails silently today (the Data source list, the Insights results
  and widgets) MUST say that it failed.

**Refreshes (story 2)**

- **FR-010**: A list that already shows content MUST keep it in place while it awaits different
  content, shown as waiting, and MUST NOT replace it with a placeholder or a message.
- **FR-011**: Rows of a waiting list MUST NOT accept actions until the list is current.
- **FR-012**: A search box MUST ask the server after typing pauses, not on every keystroke, and
  MUST keep focus and its text throughout.
- **FR-013**: Totals shown beside a waiting list MUST be shown as waiting.

**Actions (story 3)**

- **FR-020**: Every control that sends a request MUST show that it is working while the request
  is in flight, in one way across the admin, without changing its width.
- **FR-021**: A control that is working MUST NOT send its request again.
- **FR-022**: The outcome MUST be said where the action was taken.

**The loader (story 4)**

- **FR-030**: There MUST be one CoreX loader, drawn from the admin's tokens, and no CoreX screen
  MAY show WordPress's spinner or striped busy button.
- **FR-031**: A wait with known progress MUST show the progress.

**For all of it**

- **FR-040**: Placeholders and the loader MUST be visible against their surface in the dark and
  the light theme, with every colour from the admin's tokens.
- **FR-041**: Under reduced motion nothing here MAY move, and each MUST still read as what it is.
- **FR-042**: A loading surface MUST be marked busy for assistive technology and announce its
  start and its end once each; placeholder shapes MUST be hidden from it.
- **FR-043**: Layout MUST use logical properties and be correct right-to-left.
- **FR-044**: Every new string MUST be translatable.
- **FR-045**: Each surface MUST expose one dependable, documented signal of "loading" and
  "ready" that a test can wait on, the same on every surface.
- **FR-046**: The shared pieces MUST be usable by the two screens that are not built with the
  admin's component library (Insights, the setup wizard).

### Key Entities

- **Placeholder**: a stand-in in the shape of content that has not arrived: a line, a block, a
  row of a table, a card, a tile.
- **Waiting content**: content that is on screen and about to be replaced.
- **Working control**: a button whose request is in flight.
- **Loader**: CoreX's one indefinite indicator, for where a placeholder does not fit.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: On every surface in FR-001, with its answer held back, a person can tell "loading"
  from "empty" at a glance: none shows an empty state or a blank area first.
- **SC-002**: When content replaces a placeholder, nothing outside the surface moves by more
  than a few pixels, measured on each surface.
- **SC-003**: Turning a page, filtering or searching on any list never removes the rows that
  were on screen before the new ones are ready.
- **SC-004**: Typing ten characters in a search box sends at most two requests.
- **SC-005**: No action in the admin can be sent twice by pressing its button twice.
- **SC-006**: A search of the admin's code for WordPress's spinner and busy button finds none.
- **SC-007**: With reduced motion asked for, nothing on a loading surface moves.
- **SC-008**: Every existing test that waits for a screen to be ready still does, through the
  one signal of FR-045.

## Assumptions

Nobody was asked these. Each is the reading this spec is written to, and the owner's to overrule.

- **"All calls" is read as three different things, treated three ways**: a first load gets a
  skeleton; a refresh keeps the content and marks it waiting; an action marks its button. A
  skeleton on a refresh or on a button would flash on every page turn and every click, which is
  the opposite of what a skeleton is for.
- **"The loader inside CoreX"** is read as the spinner and the busy button, which today are
  WordPress's. One CoreX loader replaces both.
- **The admin only.** The public forms have their own loading state, on the theme's tokens, and
  are not changed here.
- **Screens the server draws whole get nothing**: Overview, Add-ons, Guides, Settings, and the
  server-drawn parts of Operations & Security and Access. They are never loading.
- **A placeholder's movement is a slow shimmer**, and the resting look under reduced motion is a
  plain block. The exact effect is a design decision made when it is built, in both themes.
- **The delay before a placeholder appears** is about a sixth of a second: long enough that a
  fast answer shows nothing, short enough that a slow one is covered before it feels stuck.
- **The Submissions inbox is changed last**, and with the session that is building its trash
  and delete (spec 105), since both touch the same files.

## Out of Scope

- The public front end.
- Making any request faster, or changing what any request returns.
- Optimistic updates (showing an action's result before the server confirms it).
- A global progress bar across the top of the admin.
- Redesigning the empty or error states; they are only what a placeholder hands over to.
