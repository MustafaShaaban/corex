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
  <h2 id="ID-title">{title}</h2>
  <div class="corex-modal__body">{inner blocks}</div>
  <button class="corex-modal__close" aria-label="Close">×</button>
</dialog>
```

## Behavior (view.js — minimal, Interactivity API or vanilla)

- trigger click → `dialog.showModal()`; close button / ESC / backdrop click → `dialog.close()`; focus returns to
  the trigger. Native `<dialog>` provides the focus trap + `::backdrop` + ESC.
- **No-JS degradation:** the trigger is also an in-page anchor to the content; the content renders visibly (the
  dialog is `open`-fallback or revealed via `:target`) so it is never lost without JS.

## Invariants

- Escaped output; token-only (overlay `--wp--custom--z--modal`, radius, shadow, focus ring); i18n-ready;
  logical-CSS/RTL; state/affordances not by color alone; keyboard-operable (Tab/Shift-Tab/ESC).

## Test contract

- **Pest** `ModalRendererTest`: renders the trigger + `<dialog aria-labelledby>` + close; escapes `title`/
  `triggerLabel`; no hardcoded color/size (token scan).
- **Jest** `modal/index.test.js`: `registerBlockType(metadata.name)`, `save()===null`, `edit()` previews via
  `<ServerSideRender>`.
- **Playwright (052, env-gated):** open/ESC/backdrop/focus-return + console-clean + RTL.
