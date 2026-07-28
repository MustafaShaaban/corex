# T060 — the denied surface's acceptance matrix

**Run**: 2026-07-28, `node tests/e2e/capture-denied-acceptance.mjs specs/079-admin-errors-access-request/evidence/after`
**Against**: `http://corex.local`, signed in as `corex-requester` (subscriber), on the surface at
`admin.php?page=corex-forms`.

## Result

**16 cells measured — 375px and 1280px × LTR and RTL × light and dark × 100% and 200% text zoom.
No cell scrolls sideways.**

The script asserts `document.documentElement.scrollWidth <= clientWidth` in every cell and exits
non-zero if any fails. That is the point of it: the defect this repository keeps meeting is a
**one-pixel** horizontal overflow, and no screenshot review has ever caught one. It is still open on
`corex-access`, which is a different screen and out of this spec's scope.

Four corners are photographed — `375-ltr-light-1x`, `375-rtl-dark-2x`, `1280-rtl-light-1x`,
`1280-ltr-dark-2x` — enough to review the design, few enough that somebody looks at them.

## What the RTL cells do and do not prove

They prove **layout**: logical properties flip, the card and its controls stay inside the viewport,
and nothing overflows at 375px with text at 200%.

They do **not** prove typography. The script forces `dir="rtl"` onto a page whose strings are
English, so the browser applies bidi to Latin text and trailing punctuation lands at the visual
left — `?access` rather than `access?`. That is a property of the fixture, not of the product: on a
genuinely Arabic install the strings are Arabic and the punctuation is correct. Chasing it here
would mean "fixing" something that is not broken.

Proving the Arabic typography needs an Arabic catalogue for these strings, which does not exist yet
and is its own task.

## The four states

The **form** state is covered across the full matrix. The other three — sent/pending,
validation-failed and service-failed — are reached by submitting a request, and are covered
functionally by `tests/e2e/access-request.spec.js` and visually by the pre-existing captures beside
this file (`denied-sent.png`, `denied-invalid.png`, `denied-sent-rtl-375.png`).

They are not re-measured across all 16 cells, and that is a deliberate limit rather than an
oversight: they share the same card, the same grid and the same controls as the form state, so a
sideways scroll in one would appear in all. Recorded so the gap is visible rather than implied.
