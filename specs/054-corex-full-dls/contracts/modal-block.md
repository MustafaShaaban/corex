# Contract: corex/modal (US3 — the one new block)

The single justified custom block: reusable accessible-dialog behavior core has no block for.

## Block

- `block.json`: `apiVersion 3`, name `corex/modal`, category `corex`, `editorScript`, `viewScript` (the open/
  close behavior — conditional, loads only when the block renders), `style`, `corex.renderer → ModalRenderer`.
- attributes: `title` (dialog label), `triggerLabel` (button text); inner blocks = dialog content.

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
  its first line. A screen reader is told the dialog, by its title, and the close button (amended 2026-10-11).
- **Reading.** The header does not scroll: the body does (`overflow: auto` on `.corex-modal__body`; the
  `<dialog>` is a column and clips). The title and the close button are in view at every line, by the wheel
  and by the keyboard. The padding is the header's and the body's, so the body's scrollbar is at the panel's
  edge (amended 2026-10-11).
- **The keyboard reaches the body.** The keys that scroll act on what has focus or is around it, and the close
  button is outside the body. While the body holds more than fits, `view.js` makes it a stop for Tab
  (`tabindex="0"`, `role="group"`, named by the title); while all of it fits it is not one
  (`src/Blocks/shared/keyboard-scroll.js`; amended 2026-10-11).
- **The close button** is a square no smaller than 24 CSS pixels a side (WCAG 2.2, 2.5.8), its padding drawn
  out into the header's so the cross sits on the header's edge.
- **A drawer comes in from the edge it is on**, in both directions: its entrance moves a logical margin, not
  `translateX`, which is the same way on a page read right to left (amended 2026-10-11).
- **No-JS degradation:** the trigger is also an in-page anchor to the content; the content renders visibly (the
  dialog is `open`-fallback or revealed via `:target`) so it is never lost without JS.

## Invariants

- Escaped output; token-only (overlay `--wp--custom--z--modal`, radius, shadow, focus ring); i18n-ready;
  logical-CSS/RTL; state/affordances not by color alone; keyboard-operable (Tab/Shift-Tab/ESC).

## Test contract

- **Pest** `ModalRendererTest`: renders the trigger + `<dialog aria-labelledby>` + close; the close button is
  in the header and before the body, with and without a title; escapes `title`/`triggerLabel`; no hardcoded
  color/size (token scan).
- **Jest** `modal/index.test.js`: `registerBlockType(metadata.name)`, `save()===null`, `edit()` previews via
  `<ServerSideRender>`.
- **Playwright (052, env-gated):** open/ESC/backdrop/focus-return + console-clean + RTL. In
  `tests/e2e/block-dialogs.spec.js` also: focus and position on opening, the title and the close button in
  view at the last line, the keyboard as far as the last line, the close button's size, a drawer's entrance.
