# Implementation Plan: The admin shows what is coming while it loads

**Branch**: `spec/108-loading-states` | **Date**: 2026-10-08 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/108-loading-states/spec.md`

**How this was written**: by hand to the Spec Kit plan template, from an inventory of every admin
request at `9302016f`. `/speckit-plan` was not run.

## Summary

One small set of shared pieces (a placeholder, a loader, a wrapper that hands a surface from
loading to ready, empty or error, and a working state for a button), styled once in the admin
shell on the admin's tokens, then applied surface by surface: a skeleton on a first load, the
content kept and marked waiting on a refresh, the button marked on an action.

Delivered in six slices, each its own pull request, in the order under "Slices".

## Technical Context

**Language/Version**: JavaScript (ES2022) with `@wordpress/element` for the React surfaces; plain
JavaScript for two screens that are not React; CSS

**Primary Dependencies**: none new. WordPress's `Spinner` and `Button isBusy` are removed from
the admin, not added to

**Storage**: none

**Testing**: Jest for the shared pieces and each surface's states; Playwright for each surface
with its answer held back, measuring that nothing moves when content arrives; the existing CSS
contract tests

**Target Platform**: the CoreX admin, dark and light, left-to-right and right-to-left

**Project Type**: WordPress plugin (`plugins/corex-config`), with the shared styles in
`plugins/corex-core`

**Performance Goals**: SC-002 (nothing outside a surface moves by more than a few pixels);
SC-004 (ten keystrokes, at most two requests)

**Constraints**: every colour, radius and duration from the admin's tokens; logical properties;
nothing moves under reduced motion, and each piece still reads as what it is when it is still;
raw values only in the stylesheets the token contract allows

**Scale/Scope**: 20 surfaces on 7 screens, one of them (the drawer) on every screen; 3 files that
use `Spinner` and 4 uses of `isBusy` to remove

## What the source does today

| Fact | Where |
|---|---|
| Three surfaces show WordPress's `Spinner` with a sentence; five show a sentence; six show nothing while a request is in flight | the inventory in spec.md, "Why this spec exists" |
| Data records and the Notifications list unmount their list for a loading line on every refresh; Data asks the server on every keystroke of its search box | `admin/data/DataExplorer.js`, `admin/dataClient.js` `viewState()`, `admin/data/QueryBar.js`; `admin/notifications/NotificationsApp.js` |
| No shared loading component. The shared components are `CorexDialog`, `CorexErrorState`, `CorexSelect`, `CorexTime`, `FieldValue`, the notification drawer and the export parts | `plugins/corex-config/src/admin/components/` |
| Every React surface is one bundle, so a shared component reaches all of them | `plugins/corex-config/src/admin/index.js` |
| Insights and the setup wizard are plain JavaScript and cannot import a component | `plugins/corex-config/assets/insights.js`, `addons/corex-kit-company/assets/setup-wizard.js` |
| The shell stylesheet is loaded on every CoreX screen, already holds the shared components' styles, and may hold raw values; most per-screen stylesheets may not | `corex-core/assets/css/corex-admin-shell.css`; `tests/Unit/Theme/TokenConsumerContractTest.php` |
| Tokens: surfaces, three borders, an action colour (`--corex-admin-action`) with its ink, two durations, no easing | `corex-admin-tokens.css` |
| Under reduced motion the shell gives every element in `.corex-admin` an animation of 0.01ms, once. The rule is one class strong, so a selector stronger than that which sets `animation` wins over it; and an animation that ends with no fill mode leaves the element in its un-animated style | `corex-admin-shell.css`, the `.corex-admin *` rule in a `prefers-reduced-motion` block |
| Tests wait on `data-status="ready"` (Submissions, Forms) and on loading sentences being hidden (three specs and a shared helper); a hidden-text wait also passes when the text never existed | `tests/e2e/` |
| `Corex.api` fires `corex:request:start` and `corex:request:end` on every call; nothing listens. Notifications use `apiFetch`, and Blog, Access and the import upload use `fetch`, which fire neither | `corex-runtime.js` |
| A skeleton class exists for the public theme on the theme's tokens; the admin's scoping test forbids those tokens in an admin stylesheet | `addons/corex-ui/assets/block-styles.css`; `tests/Unit/Config/AdminAssetScopingTest.php` |

## Decisions

### D1. Four shared pieces, in the admin's component folder

| Piece | Is |
|---|---|
| `CorexSkeleton` | A placeholder, hidden from assistive technology, and its parts: a bar the height of a line of text, and a box |
| `CorexLoader` | The one indefinite loader, with an optional sentence |
| `CorexLoadable` | The wrapper a surface puts round its content. Given a status (`loading`, `refreshing`, `ready`, `error`), a name for the content, and a skeleton, it shows the right thing, marks the surface, and announces |
| `workingProps( working )` | What a button is given while its request is in flight: disabled, busy, and one attribute the styles hang on |

The plain-JavaScript screens use the same class names, written as markup. The class names are
the contract; the components are the convenience.

Each piece lands with its first caller, not before: the placeholder and the wrapper in slice 1,
the loader and the working button in slice 2. A placeholder is composed by its surface, from
shared bars and boxes inside the surface's own markup, so that it takes the content's padding
and gaps from the same rules the content does; the shared file holds the parts, not a catalogue
of shapes nobody has asked for yet.

### D2. One signal on every surface (FR-045)

`CorexLoadable` sets `data-corex-state` to `loading`, `refreshing`, `ready` or `error` on the
surface, and `aria-busy` while it is one of the first two. The two existing `data-status`
attributes stay, because tests wait on them; the loading sentences that tests wait to be hidden
are replaced in those tests by waits on `data-corex-state="ready"`, which fail when a surface
never becomes ready, where the old waits passed.

### D3. What a placeholder looks like

A block in a colour one step from the surface it sits on, with a band of a lighter step crossing
it. Two new tokens, defined from colours the admin already has, so both themes switch for free:
`--corex-admin-skeleton` (the border colour) and `--corex-admin-skeleton-shine` (the strong
border in the dark theme, the soft one in the light, lighter than the block in both). The band
is a pseudo-element moved with `transform`, not a background position, every 1.4 seconds
(`--corex-admin-motion-shimmer`), eased (`--corex-admin-ease`, the first easing token).

The band's un-animated place is off the block, and under reduced motion its animation is turned
off by a rule of its own (the shell's general rule is too weak to be relied on, see the table
above), so a still placeholder is a plain block. Text lines are bars of
the text's own line height with ragged widths; a table's rows keep the table's row height; a
tile keeps its tile's size.

### D4. A placeholder waits a moment before it shows (FR-004)

In CSS, not JavaScript: a placeholder is held transparent for 160ms
(`--corex-admin-loading-delay`) by an animation's delay and then is simply there, so a fast
answer replaces it before it was ever seen, with no timer to clean up and the same behaviour on
the two plain-JavaScript screens. The space it holds is held from the first frame, so nothing
moves either way. Appearing is not movement, so the delay stays under reduced motion.

### D5. Waiting content (story 2)

Content that is about to be replaced stays mounted, at the admin's disabled opacity, takes no
pointer or keyboard action (`inert`), and has a thin bar of the action colour travelling along
its top edge. If the keyboard's focus was inside it, focus moves to the surface, not to the top
of the page. Under reduced motion the bar is still and spans the edge, over the dimmed content.

`viewState()` in the Data client stops putting loading first when there are rows; the
Notifications list keeps its items while it reloads. Search boxes ask after 300ms without a
keystroke.

### D6. The loader (story 4)

A ring: a track in the strong border colour and an arc in the action colour, turning in 900ms
(`--corex-admin-motion-slow`), drawn with borders so it needs no image and takes
`currentcolor` inside a button. Under reduced motion it does not turn: the ring with its arc at
the top, beside its sentence.

### D7. A working button (story 3)

The label stays in the layout with its fill made transparent, which leaves the button's own
colour for the loader centred over it. So the button keeps its width exactly, and a screen
reader still has the label. The button is disabled and marked busy, without the dimming a
disabled control gets, and the outcome is announced by the surface. `Button isBusy` is not
used.

### D8. Announcing (FR-042)

`CorexLoadable` holds one visually hidden live region. It says the surface's own loading
sentence when a load has lasted a second, and "Loaded." when a load it announced ends; a load
that was quicker than that says nothing, and nor does a refresh unless it fails. Each sentence
is whole and translatable, not assembled. Placeholder shapes are `aria-hidden`.

### D9. Failures that are silent today (FR-006)

The Data source list, the Insights results and the Insights widgets get the shared error state
at panel scale with a retry.

## Constitution Check

| Principle | How this plan meets it |
|---|---|
| Spec before code (X) | The spec and this plan, before the first slice. |
| Design tokens are runtime (V) | Two colour tokens, two motion tokens and one easing token, defined beside the others from colours the admin already has. No raw colour outside the token file. |
| Assets load conditionally (VI) | The shared styles go in the shell stylesheet every CoreX screen already loads; no new stylesheet, no library. |
| RTL first (VIII) | Logical properties; the band's direction follows the document's. |
| Thin controllers, injection (III, IV) | No PHP services change. |
| i18n, WCAG 2.2 AA | Sentences through `corex`; `aria-busy`, one live region per surface, shapes hidden; nothing moves under reduced motion; a working button keeps its name. |
| Tests | Under each slice in tasks.md, written first. |
| Guard Gate, UI/UX gate | Each slice through its guards; each surface looked at and measured in both themes and both directions, with its answer held back, before it is presented. |

No violation needs justifying.

## Project Structure

```text
plugins/corex-core/assets/css/corex-admin-tokens.css     # five tokens
plugins/corex-core/assets/css/corex-admin-shell.css      # .corex-admin-skeleton*, .corex-loadable, .corex-loader, [data-corex-working]
plugins/corex-config/src/admin/components/
├── CorexSkeleton.js
├── CorexLoader.js
├── CorexLoadable.js
├── working.js
└── __tests__/
plugins/corex-config/src/admin/useDebounced.js           # the search boxes
plugins/corex-config/src/<Feature>/…                     # each surface
plugins/corex-config/assets/insights.js                  # the class names as markup
addons/corex-kit-company/assets/setup-wizard.js
tests/e2e/loading-states.spec.js                         # each surface, its answer held back
docs-app/src/content/docs/design-system/admin-experience.md # "Loading states": the pieces, for whoever adds a screen
```

## Slices

Each is one pull request and leaves `main` releasable.

| # | Stories | What lands |
|---|---|---|
| 1 | US1, US2 | The tokens, the placeholder and the wrapper, with their tests; applied to Notifications (the list, its preferences, the drawer on every screen), which also stops blanking its list. |
| 2 | US3, US4 | The loader and the working button; every action outside the Submissions inbox; `isBusy` gone from the admin. |
| 3 | US1, US2 | Data: records (a skeleton first, rows kept after), the totals, the search box, a record opened at once, export history, migrations, the silent source list. Its `Spinner` goes. |
| 4 | US1, US2 | Forms and flows, and Email Studio, with a template chosen from the list. |
| 5 | US1 | Insights and the setup wizard, in plain JavaScript; their silent failures. |
| 6 | US1, US2, US3 | Submissions: the list, the pane, its actions and both export dialogs, agreed with the session building spec 105. The last `Spinner` goes. |

## Risks

- **A placeholder that is the wrong size is worse than none.** Each surface's placeholder is
  measured against its content in a browser test, at 1280 and at 782, in both directions.
- **Dimmed content must not look disabled for good.** The bar along its top edge is what says
  "waiting", moving or still, and the surface is marked busy.
- **`inert` on waiting content** removes it from the tab order, so focus that was inside a list
  when it refreshes has to be put back. Each list's test covers a refresh started from the
  keyboard.
- **Two sessions in one inbox.** Slice 6 touches the files spec 105 is changing; it goes last
  and is agreed first.
- **Per-screen stylesheets may not hold raw values.** Everything shared goes in the shell; a
  surface's own stylesheet only places the shared pieces.
- **Tests that wait on a sentence being hidden** pass whether or not the screen ever loaded.
  They are moved to the one signal as each surface is changed, not left.
