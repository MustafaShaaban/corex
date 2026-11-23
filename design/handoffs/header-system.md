# Header System Handoff

**Status:** Approved

**Approved:** 2026-11-22, by the owner, in session, in three steps, each from a picture with a button: "Approve step
1" (the phone menu), "Approve step 2" (the utility row and the language switcher), "Approve step 3" (the header
search and the phone action bar).

**Addendum, step 2a (the language toggle):** approved 2026-11-23 by the owner, "Approve step 2a", typed in the
Blackstone coordinating session and relayed by it (DECISIONS #702). It covers the nine boards of `#page-step2a`.

**Design source:** the canvas "CoreX spec 137: the header system" in Claude Design, private to the owner:
https://claude.ai/artifact/3jYwcAEaZyBWC1pgG1ZHvF. It is drawn on the CoreX design system
(https://claude.ai/artifact/DExY8maEuxwM6JwdZ1jw5c) and uses the approved visitor kit's SiteHeader, MegaMenu,
Navigation, Dropdown, Button, Badge and TextInput unchanged (`visitor-kit.md`, DECISIONS #639). The canvas holds
27 approved boards and the 9 of the step 2a addendum. The repository keeps no design-tool export (`tests/repo-hygiene.test.js`), so the boards stay on the
canvas; what the code needs from them is written here, and the rules the kit lacked are kept beside the spec as
`specs/137-header-system/design/header-system.css.txt`, the build contract.

**Engineering target:** `specs/137-header-system/spec.md`, slices 2, 4 and 5. Slices 1 and 3 are built from
`navigation-footer.md` and the kit.

## Approval evidence

The boards were drawn by a design agent from the spec, read in a browser by the spec's session before they were
shown, and put to the owner page by page. He approved each page as drawn, with no change asked. Nothing was
measured: the boards are static renditions, and their heights are estimates. Keyboard order, focus movement and
opening and closing are written below and are proven when each slice is built.

## Approved scope

**Step 1, the phone menu (375px, 11 boards).**
- The bar closed: the logo and the "Menu" button, one row. The call to action and the utility links are not in it.
- The menu open: the brand and the close button, which stay at the top while the menu scrolls; the menu's rows,
  each 48px, where an item that opens a group shows a chevron and a plain link does not; a "Language" row; the
  utility links; the one strong button.
- A mega item opened in place: its entries as a list under its row, each a title and one line of text with no
  icon tile, a badge where the panel has one, then the panel's side card as a quiet block. The chevron points
  forward when closed and down when open. The other groups stay closed.
- A WordPress sub-menu opened in place looks the same as a mega item's group.
- Keyboard focus on a row: the 2px brass ring.
- With search: the Search block is the first row.
- A long case: a label on two lines and eight entries in one group; the menu scrolls inside itself.
- A site with one language: no Language row.
- The fullest state in dark, in Arabic, and in dark Arabic.

**Step 2, the utility row and the language switcher (1280px, 11 boards).**
- The corporate header: a utility row above the bar, on the quiet band (`surface-alt`), holding a short menu
  (three links) on the inline start and the language switcher on the inline end; the kit's bar under it.
- The utility row before an owner edits it: the Brand phone and email as its two links.
- The language switcher, **menu form**: the kit's ghost button naming the current language with a chevron; the
  kit's Dropdown under it listing every language, the current one ticked on the brass tint. Closed, hover,
  keyboard focus, open. With three languages and with two.
- The language switcher, **list form**: every language as a link in a row; the current one is not a link and is
  marked by the brass tint, a tick and weight 600. At rest, hover, keyboard focus.
- The standard header with the switcher in its actions, in each form.
- Six menu items of which three open a panel, with the Services panel open under the bar as wide as the header's
  content; the trigger at rest, hover, focus and open (its caret turned).
- Arabic: the row, the bar and the open language menu aligned to the inline end and inside the window; the panel
  open in dark, in Arabic and in dark Arabic.

**Step 3, search and the phone action bar (5 boards).**
- Search in the bar at 1280px: closed (an icon button named "Search"), open (a field 14rem wide that opens over
  the end of the menu, so nothing in the bar moves, with the icon button inside it), focus, a typed value; dark
  and Arabic.
- The phone action bar at 375px: the strong button, full width, fixed to the foot of the screen, with the page's
  last content readable above it; light, dark, Arabic.

**Step 2a, the language toggle (addendum, 9 boards, page `#page-step2a`; spec FR-032a, G-054, DECISIONS #697).
Approved 2026-11-23.**
- The toggle is one link of the list form with no current item: the other language's name in its own script,
  with its own `lang`, `dir` and `hreflang`, no tint, no tick, no icon, no script. It reads "العربية" on an English
  page and "English" on an Arabic page. At rest, hover and keyboard focus (the 2px brass ring), on the page and
  on the utility row's quiet band; its 44px target drawn. The same in dark.
- In the corporate header's utility row at 1280px, on the inline end, English and Arabic pages.
- In the standard header's actions at 1280px, beside the strong button, English and Arabic pages.
- In the open phone menu at 375px, as the "Language" row's link, English and Arabic pages.
- The editor: the block's "Form" setting with three choices, Menu, List and Toggle, and one sentence of help:
  "With three or more languages, the toggle shows as the list." Drawn in WordPress's own look.
- With one language nothing is printed; with three or more the list form is printed instead (not drawn: both
  are already drawn forms).

## Explicit exclusions

- **The editor.** The mega item's panel picker and notice and the language switcher's placeholder are WordPress's
  own editor components as they come (spec FR-013, FR-031). They are not drawn and not approved here.
- **A slide-in drawer.** Later work (NAV-D2, spec FR-028).
- **Icons in the utility row and on the switcher.** The kit has no phone or globe icon; both are text only.
- **A mark for the current page inside the phone menu.** The kit's phone menu has none, and none was added.
- **The entry anatomy of a panel as a block** (icon tile, badge, card): the boards show the kit's MegaMenu as
  content. The blocks that build it are US-NAV-03's.
- **A dark Arabic phone action bar**: not drawn; it follows from the three that are.

## Interaction and state behaviour

- Nothing opens by itself. A group, a panel, the language menu and the search field open on a click, a tap or
  Enter; a panel also opens when a mouse rests on its trigger (spec FR-015).
- In the phone menu one group is open at a time. Escape closes the menu and returns focus to the "Menu" button.
- The language menu: arrow keys move through the languages, Enter follows one, Escape closes and returns focus to
  the button (the kit's Dropdown). Without script the menu form is the list form.
- The search field: opening moves focus into it; Escape closes it and returns focus to the icon button.
- A utility row that holds no link and no control is hidden and takes no space.
- States drawn: rest, hover, keyboard focus, open, a typed value, a long label, a long group, one language.

## Desktop, tablet and mobile behaviour

- Two forms only: the bar with its inline menu from the navigation breakpoint up, and the logo with the "Menu"
  button below it. The breakpoint is the theme's token, 782px unless a theme sets another (spec FR-001).
- Below the breakpoint the utility row, the call to action and the search leave the bar and are inside the opened
  menu. The bar stays one row.
- The phone action bar shows below the breakpoint only.
- Nothing scrolls sideways at 375px. A target is at least 44px; a row of the phone menu is 48px.

## LTR, RTL and mixed-script behaviour

- Logical properties throughout. Chevrons that point forward flip in Arabic; a tick, a search glass and a close
  mark do not.
- Each language name is drawn in its own script with its own `lang` and `dir`, whatever the page's direction.
- Arabic uses the Arabic font token. The kit's stylesheet does not give that face to the brand, the phone menu's
  links, a panel entry's title or a standalone link: the build contract adds it, and the design system is owed
  the same correction.
- A language name follows its own language, not the page's: "العربية" is in the Arabic face on an English page and
  "English" in the heading face on an Arabic page, in every form. The boards drew it so; rule 3a of the build
  contract makes the code do it, because rule 0 follows the page (found while drawing step 2a, 2026-11-23).

## Accessibility expectations

- WCAG 2.2 AA. Text 4.5:1, control edges, focus rings and icons 3:1. Brass is never text on a page surface.
- The current language is said by more than colour: a tick and a heavier weight, and `aria-current`.
- The search field has no visible label, which bends the kit's TextInput rule: it carries a name for assistive
  technology and a placeholder, and its button is named "Search". The owner approved it as drawn.
- The utility menu is a second navigation and has its own name.
- Transitions (the chevron, the panel) are removed under `prefers-reduced-motion`.

## Performance and conditional assets

- No CSS framework and no global asset. Each block's style loads only where the block is; the header's styles
  load only where a navigation renders.
- No web font and no icon font is added.

## Design tokens and reusable primitives

No token was added. The rules the kit lacked are in `specs/137-header-system/design/header-system.css.txt`, every
value a `theme.json` token and every side a logical property. New classes, which the code keeps:

- The bar: `corex-nav__trigger`, `corex-nav__panel`.
- The utility row: `corex-header__utility`, `corex-header__utility-list`, `corex-header__utility-link`.
- The language switcher: `corex-language-switcher`, `--menu`, `--list`, `__list`, `__item`, and `--toggle`
  (step 2a).
- Search: `corex-header__search` with `is-open`.
- The phone menu: `corex-mobile-menu__head`, `__list`, `__group`, `__toggle`, `__panel`, `__sublist`,
  `__sublink`, `__language`, `__label`, and `corex-mobile-menu--overlay`.
- The action bar: `corex-mobile-action-bar`, and `corex-has-mobile-action-bar` on the page.

Tokens used: spacing 10 to 60, radius `sm`, `surface`, `surface-alt`, `surface-strong`, `ink`, `ink-soft`,
`accent-dark`, `accent-subtle`, the thin border width, the base duration and standard easing, the dropdown and
sticky layers, the wide size. Raw lengths are the kit's own: 3rem (a phone menu row), 2.75rem (a target), 2.5rem
(a bar link), 2.25rem (a small button), 14rem (the Dropdown's and the search field's width). Where the build maps
a class onto WordPress's own markup (the Navigation block's classes), the look, the sizes and the states stay as
drawn.

## What was checked, and what was not

- **Checked:** every page of the canvas opens and its boards render in the owner's browser; five boards of step
  1, two of step 2 and the whole of step 3 were read at a legible size before they were shown.
- **Not checked:** contrast of every pair by measurement; any board at 200% zoom; a real safe-area inset (a
  static board cannot show one); keyboard behaviour.

## Open questions

1. The design system is owed three additions so the next design starts from them: these rules, the Arabic face
   on the four classes named above, and a mark for the current page in the phone menu.
2. Whether the utility links and the language switcher get icons waits for the front-end icon set (US-NAV-03's
   ground, CX-BLK-05).

## How to change this design

A change starts on the canvas above, on the CoreX design system, is approved by the owner, and is recorded here
before the code changes (`AGENTS.md`, "UI, UX and front end").
