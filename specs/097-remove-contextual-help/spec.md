# Feature Specification: The Help tab CoreX never asked for

**Feature Branch**: `spec/097-remove-contextual-help`

**Created**: 2026-08-05

**Status**: Draft

**Input**: Owner defect report with a screenshot — WordPress's contextual Help tab opening a large
panel above the CoreX admin shell.

## Why this spec exists

Spec 084 shipped `ContextualHelp`, which put each guide into the WordPress contextual Help tab of the
screen it describes. The reasoning was recorded in its own docblock and it was not silly: *"a
contextual-help panel on every admin screen, sitting empty. A guide that names the screen it is about
can fill it for free."*

It is being removed, and the reason is worth stating plainly rather than filed as a preference.

**The Help tab is wp-admin's chrome, and CoreX admin is not wp-admin.** Every CoreX screen renders a
full-bleed product shell — its own rail, header, canvas and appearance control — deliberately
stripped of wp-admin padding so no core canvas leaks (`body.corex-admin-screen`, spec 067). The Help
tab is the one piece of core chrome that survived that, and it does not survive it quietly: it opens
a panel *above* the shell, pushing the entire product down the page. A surface built to look like an
application acquires a disclosure widget from the host it was built to hide.

**The guide was already reachable by a better route.** `GuidesScreen` renders each guide's declared
address as a link (`.corex-guides__guide-screen`), and every screen's guide is one click from the
Guides menu item. The Help tab was a second, worse copy of a route that already existed: smaller, not
searchable, and carrying a summary rather than the steps.

### What is emphatically *not* being removed

The Guides add-on stays whole. The registry, `registerDeferred()`, the `corex_guides` filter, client
plugin registration, the standalone Guides screen, guide content, sections, topics, steps,
screenshots, search, support requests, documentation links and `wp corex make:guide` are all
untouched. `Guide::onScreen()` stays too — it feeds the Guides-screen deep link, which is a real
consumer.

### Removal has to survive other people's plugins

Deleting `ContextualHelp` removes the tabs CoreX adds. It does not remove tabs that WordPress core,
another plugin, or a future add-on adds to a CoreX screen — and a screen that is clean today and
grows a Help panel when somebody activates an unrelated plugin has not been fixed, only tidied. So
the same change adds a positive removal on CoreX-owned screens, at the latest lifecycle point where
it still beats the render.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — The CoreX shell begins at the top of its page (Priority: P1)

**Independent Test**: open any CoreX admin route. There is no Help link, no panel, no empty wrapper
and no reserved space; the shell's top edge sits directly below the admin bar.

### User Story 2 — The Guides screen still does everything it did (Priority: P1)

**Independent Test**: open Guides. Guides render grouped into sections, topics open to numbered
steps, screenshots load, search narrows the list and reports when nothing matches, the support panel
submits, and each guide's screen link opens the screen it describes.

### User Story 3 — A client plugin's guide still appears (Priority: P2)

**Independent Test**: a plugin registering through `corex_guides` (or `registerDeferred()`) sees its
guide on the Guides screen, gated by its declared capability.

### User Story 4 — Nothing outside CoreX changes (Priority: P1)

**Independent Test**: `edit.php`, `options-general.php` and `plugins.php` keep their own Help tab and
open their own panel, exactly as stock WordPress does.

### Edge Cases

- **Another plugin adds a Help tab to a CoreX screen.** It is removed before the screen renders,
  because the removal runs later than any registration point.
- **Screen Options.** Untouched. Only Help is removed, and `#screen-meta-links` still renders when a
  screen offers Screen Options — this spec does not claim that wrapper is gone, only the Help one.
- **The Guides add-on is deactivated.** CoreX screens still have no Help tab: the removal lives in
  `corex-config`, which is always active, not in the optional add-on.
- **A guide declaring a screen address.** Still valid, still rendered as a link. Only the Help-tab
  consumer of that address is gone.

## Requirements *(mandatory)*

- **FR-001**: No CoreX-owned admin screen MUST render `#contextual-help-link-wrap`,
  `#contextual-help-link` or `#contextual-help-wrap`.
- **FR-002**: The removal MUST be by unregistering the help tabs and sidebar, never by hiding them
  with CSS or JavaScript.
- **FR-003**: The removal MUST run at a lifecycle point later than every point at which help can be
  registered, so tabs added by core or a third-party plugin are also removed.
- **FR-004**: The removal MUST apply only to CoreX-owned screens, identified by the single canonical
  screen predicate the shell stylesheet already uses.
- **FR-005**: The removal MUST NOT depend on the Guides add-on being active.
- **FR-006**: No blank area, empty wrapper or extra top spacing MUST remain; the CoreX shell MUST
  begin directly below the admin bar.
- **FR-007**: The standalone Guides screen MUST keep every capability it has today.
- **FR-008**: Guides registered by a client plugin MUST keep working, through both seams.
- **FR-009**: `Guide::onScreen()` MUST be preserved as the Guides-screen deep link; the registry
  method that existed only to serve the Help tab MUST be removed rather than left dead.
- **FR-010**: Non-CoreX admin screens MUST be unaffected.
- **FR-011**: Coverage MUST hold in light and dark appearance, LTR and RTL, at mobile, tablet,
  desktop and wide widths, and at 200% zoom.

### Out of scope

- Removing the Guides add-on, any guide, or any guide content.
- Removing Screen Options from anything.
- Changing help behaviour on non-CoreX screens.

## Defects fixed alongside

Two browser failures block the release and both are real layout defects, not flakiness. They are in
this spec because they are the same class of problem the Help tab is — CoreX admin surfaces not
containing themselves — and because a spec that leaves the suite red cannot be verified.

- **FR-012**: The Blog Pro workspace MUST contain itself at 1024px in LTR (`.corex-blog-pro-app`
  `scrollWidth <= clientWidth`), as it already must at 375, 768 and 1440.
- **FR-013**: Every disclosure control in the Forms & Flows catalog MUST be clickable without a
  CoreX wrapper intercepting the pointer.

Neither is to be addressed by weakening the assertion, forcing the click, or adding a wait.

### One more, found by the work rather than reported

- **FR-014**: The Guides screen MUST introduce itself as "Corex / Guides".

Declared here rather than fixed quietly, because it is a behaviour change and not a refactor. The
breadcrumb map in `AdminPage` never learned the `guides` section, so since spec 084 the screen has
fallen through to the default and read **"Corex / Framework"** while its own heading said Guides —
on the one screen somebody opens *because they are lost*. It surfaced the moment this spec widened
the browser route matrix from twelve routes to all fourteen, which is the argument for widening it.

## Success Criteria

- **SC-001**: `#contextual-help-link-wrap` count is 0 on every CoreX route, in every appearance,
  direction, width and zoom level under test.
- **SC-002**: The Guides screen, its search, its screenshots, its support panel and a
  client-registered guide all pass in the browser.
- **SC-003**: `edit.php` still opens a Help panel.
- **SC-004**: The full Playwright suite is green, with no test skipped, weakened or forced.
