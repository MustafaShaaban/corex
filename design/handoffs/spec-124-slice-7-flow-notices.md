# Spec 124 slice 7: flow block notices

Approved design-to-engineering handoff for slice 7 of [spec 124](../../specs/124-modes-and-login/spec.md)
(FR-021, User Story 10).

## Approval evidence

- Drawn in Claude Design: <https://claude.ai/artifact/2ZCTSn85qW54cRVKDfbyE4> ("CoreX spec 124 slice 7: flow block
  notices", eight artboards, drawn on the `theme.json` and `styles/dark.json` tokens).
- Approved by the owner "as drawn" on 2026-11-15 at canvas Version 10. Questions 1 to 7 of the design session were
  settled as drawn. The question of a CoreX design system in Claude was left open and does not block this slice.

## Scope

Two notices in every flow block (Flow, Form, Subscribe, Survey, Call to action), both built on the closed-flow notice
panel of spec 122 (`.corex-flow-notice`, title, message, optional extra line). No new component.

Excluded: the mode cards, the visitor messages screen, the maintenance page, the login screens (slices 8 to 11).

## 1. The test-site notice

When: Development, or Staging. Shown to every visitor, the administrator included, above the form and below the
block's heading.

- Panel: `.corex-flow-notice corex-flow-notice--test`. Same panel, small text
  (`--wp--preset--font-size--sm`) and a warning edge (`border-inline-start-color: var(--wp--preset--color--warning)`).
- Title: "Test site".
- Message, two wordings, chosen by the mail gate's answer for this site (a display query only, `MailGate::decideIn()`
  or what replaced it; it sends nothing):
  - emails kept: "This is a test copy of the site. What you send here is kept for testing. This form's emails stay
    on the site and are not delivered."
  - emails delivered (Staging with a mail provider and live delivery both on): "This is a test copy of the site.
    What you send here is kept for testing. This form's emails are delivered."
- The form is drawn as usual below it.
- Arabic as drawn: title "موقع تجريبي"; kept: "هذه نسخة تجريبية من الموقع. ما ترسله هنا يُحفظ للاختبار. رسائل البريد
  الخاصة بهذا النموذج تبقى في الموقع ولا تُرسَل."; delivered: "... رسائل البريد الخاصة بهذا النموذج تُرسَل فعلاً."
  (These go through the translation files; the owner's native Arabic review applies.)

## 2. The paused panel (Forms paused)

When: the site is in Forms paused and the viewer is not a signed-in administrator.

- The form is not drawn. The block keeps its heading and shows the spec 122 panel, unchanged (info edge, normal text).
- Title: "Paused".
- Message: the owner's message when set (up to 300 characters, the panel grows, nothing is cut, `overflow-wrap:
  anywhere`), otherwise CoreX's default "Forms are paused for a short while. Please try again later."
- Third line, `.corex-flow-notice__when`:
  - with a back-by time in the future: "Back by {date} at {time}." in the site's own date and time formats
    (`get_option('date_format')`, `get_option('time_format')`, the site's timezone);
  - with a back-by time that has passed while still paused: "This is taking longer than planned." (the past time is
    never shown, as on the maintenance page);
  - with no back-by time: no third line and no gap where it would be.
- Arabic as drawn: "متوقفة مؤقتاً", "النماذج متوقفة لفترة قصيرة. يُرجى المحاولة لاحقاً.", "نعود بحلول {date} في {time}.",
  "يستغرق الأمر وقتاً أطول مما خططنا له."

## 3. Paused after the page was loaded

The visitor loaded the page before the pause and submits after it. The server answers 503 (slice 1). Then:

- the same paused panel (title, message, third line) is drawn above the form, as for a flow that closed meanwhile;
- the answers stay as typed; no field is marked as an error;
- the form's status line under the button (`.corex-form__status`, `role="status"`) says the message, so a screen
  reader hears it once.

## 4. A closed or scheduled flow on a paused site

The flow's own notice wins: a closed flow, or one not open yet, shows exactly what spec 122 shows today, with the
owner's alternative, and no paused panel beside or instead of it.

## 5. Other blocks and the administrator

- The notices are identical in every flow block.
- The one change: inside the Call to action block (`.corex-flow--cta-flow`, already filled with `surface-alt`) the
  panel takes `background: var(--wp--preset--color--surface)` so it stays a visible panel. This applies to every
  notice in that block, the spec 122 ones included.
- A signed-in administrator on a paused site gets the real form and no panel. Production, Coming soon and
  Maintenance show no notice at all.

## Layout, direction, themes

- 768px column and 375px phone: the panel is full width of the block; the form's two half fields stack at phone
  width as they do today.
- Logical properties only (`border-inline-start`, `padding-inline`, `padding-block`, `text-align: start`); RTL mirrors
  the edge to the right.
- Light (`theme.json`) and dark (`styles/dark.json`) tokens only: `surface`, `surface-alt`, `ink`, `primary`,
  `info`, `warning`, spacing `20` to `40`. No hardcoded colour, size or font.

## Accessibility

- Both notices are part of the page as it loads (server-rendered), not live regions, so a screen reader reads them
  once. Only the after-load status line is `role="status"`.
- Text contrast at least 4.5:1 in both themes on `surface-alt` (and on `surface` in the Call to action block).
- No new focusable element. The closed-flow alternative link keeps its underline.

## Measurement

Measured at 1280px and 375px, light and dark, LTR and RTL, against the artboards (spec 124, "What each slice
proves"), then one recorded round for the owner (`ui-review-loop`).
