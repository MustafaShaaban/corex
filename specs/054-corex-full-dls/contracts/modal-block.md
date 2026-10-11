# Contract: corex/modal (US3 — the one new block)

The single justified custom block: reusable accessible-dialog behavior core has no block for.

## Block

- `block.json`: `apiVersion 3`, name `corex/modal`, category `corex`, `editorScript`, `viewScript` (the open/
  close behavior — conditional, loads only when the block renders), `style`, `corex.renderer → ModalRenderer`.
- attributes: `title` (the dialog's heading and its name; may be empty), `triggerLabel` (button text; "Open",
  translated, when empty); inner blocks = dialog content.

## Rendered markup (ModalRenderer, escaped, token-only)

```
<button class="corex-modal__trigger" aria-haspopup="dialog" aria-controls="ID">{triggerLabel}</button>
<dialog id="ID" class="corex-modal" aria-labelledby="ID-title">
  <div class="corex-modal__header">
    <h2 id="ID-title" class="corex-modal__title">{title}</h2>
    <button class="corex-modal__close" aria-label="Close">×</button>
  </div>
  <div class="corex-modal__body">{inner blocks}</div>
</dialog>
```

Amended 2026-10-11 (`corex/drawer` the same, with its own class names): the title and the close button are in
a header, and the close button comes before the body. Until then the close button was last, after the body.
The order is the behavior: a browser opening a dialog puts focus on the first thing in it that takes focus and
scrolls to it, which was the first link of the content, wherever in the content that was.

**The dialog always has a name** (amended 2026-10-11, DECISIONS #316; `corex/drawer` the same). With a title
it is the title, by `aria-labelledby`, as above. With no title there is no `<h2>`, the header holds the close
button alone, and the `<dialog>` carries the trigger's label:

```
<dialog id="ID" class="corex-modal" aria-label="{triggerLabel}">
```

Until then a dialog with no title had neither attribute and was announced as "dialog" and nothing more. The
label is the trigger's because it is the one text the block always has, and it is what the visitor pressed.
A trigger nobody labelled reads "Open", and so does its dialog: a name, and a poor one, since it says what
the button does and nothing of what the dialog holds. A title is not made a requirement, because a dialog
can be meant to show none. The editor is told instead: while the Title field is empty, the help under it
says what a screen reader will announce in its place, and asks for a title when the trigger has no label
either (`src/Blocks/shared/title-help.js`).

## Behavior (view.js — minimal, Interactivity API or vanilla)

- trigger click → `dialog.showModal()`; close button / ESC / backdrop click → `dialog.close()`; focus returns to
  the trigger. Native `<dialog>` provides the focus trap + `::backdrop` + ESC.
- A backdrop click is a press of the mouse that starts and ends outside the dialog's box. The `<dialog>` element
  is the panel, so a click whose target is the dialog is not enough to know one by (amended 2026-10-11;
  `corex/drawer` follows the same rule, `src/Blocks/shared/backdrop-press.js`).
- The panel's sizes are the panel's, padding and border included (`box-sizing: border-box`; amended
  2026-10-11, `corex/drawer` the same). An open panel is inside the window and no wider than 92% of it, so
  there is backdrop beside it to press, and one holding more than fits scrolls as far as its last line.
- **Opening.** Focus is on the close button, by the order of the markup and not by script. The dialog is at
  its first line. A screen reader is told the dialog, by its name, and the close button (amended 2026-10-11).
- **Reading.** The header does not scroll: the body does (`overflow: auto` on `.corex-modal__body`; the
  `<dialog>` is a column and clips). The title and the close button are in view at every line, by the wheel
  and by the keyboard. The padding is the header's and the body's, so the body's scrollbar is at the panel's
  edge (amended 2026-10-11).
- **The keyboard reaches the body.** The keys that scroll act on what has focus or is around it, and the close
  button is outside the body. While the body holds more than fits, `view.js` makes it a stop for Tab
  (`tabindex="0"`, `role="group"`, named as the dialog is: the same `aria-labelledby`, or the same
  `aria-label`); while all of it fits it is not one (`src/Blocks/shared/keyboard-scroll.js`; amended
  2026-10-11).
- **The close button** is a square no smaller than 24 CSS pixels a side (WCAG 2.2, 2.5.8), its padding drawn
  out into the header's so the cross sits on the header's edge.
- **A drawer comes in from the edge it is on**, in both directions: its entrance moves a logical margin, not
  `translateX`, which is the same way on a page read right to left (amended 2026-10-11).
- **With scripting off** the dialog is in the page, where the block is: its title and its content, in the
  flow, and neither the trigger nor the close button, which only `view.js` answers. This is the stylesheet's
  doing (`@media (scripting: none)` in `src/Blocks/shared/_dialog-parts.scss`), with no change to the markup.
  Amended 2026-10-11 (DECISIONS #316; `corex/drawer` the same). This line used to say that the trigger is
  also an in-page anchor to the content and the dialog is revealed by `:target`. It never was: the trigger is
  a `<button>`, and with scripts off a visitor had a button that did nothing and no content. What is still
  not covered: a browser with scripting on in which `view.js` did not run (blocked, or failed to load). There
  the trigger is shown and does nothing.

## Invariants

- Escaped output; token-only (overlay `--wp--custom--z--modal`, radius, shadow, focus ring); i18n-ready;
  logical-CSS/RTL; state/affordances not by color alone; keyboard-operable (Tab/Shift-Tab/ESC).

## Test contract

- **Pest** `ModalRendererTest`: renders the trigger + `<dialog aria-labelledby>` + close; a dialog with no
  title carries the trigger's label as `aria-label`, the written one or the default, escaped; the close
  button is in the header and before the body, with and without a title; escapes `title`/`triggerLabel`; no
  hardcoded color/size (token scan).
- **Jest** `modal/index.test.js`: `registerBlockType(metadata.name)`, `save()===null`, `edit()` previews via
  `<ServerSideRender>`, the Title field carries the help. `shared/title-help.test.js`: what the help says.
  `shared/keyboard-scroll.test.js`: the body is named as the dialog is.
- **Playwright (052, env-gated):** open/ESC/backdrop/focus-return + console-clean + RTL. In
  `tests/e2e/block-dialogs.spec.js` also: focus and position on opening, the title and the close button in
  view at the last line, the keyboard as far as the last line, the close button's size, a drawer's entrance,
  the accessible name of a dialog with a title and of one without, and the page with scripts off.
