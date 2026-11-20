# Logo Set Handoff

**Status:** Approved

**Approved:** 2026-11-16, by the owner, in session ("Approve logo set"), on the artboard "Logo set"
(`project/Logo-set.dc.html`) of the R2.2 canvas at version `1794836545-0480`.

**Design source:** the R2.2 canvas in Claude Design, private to the owner:
https://claude.ai/artifact/RgpHR931N7vqqnF6whkNyz , artboard "Logo set on every background". Only that artboard is
approved here. The canvas's page artboards (Front, Content, Contact, Sections and their variants) are not: the
visitor kit (`visitor-kit.md`) is the approved look for pages.

**Engineering target:** `specs/134-theme-graphite-brass/spec.md`, slice 3 (FR-018 to FR-024, SC-006, SC-007).

## Approval evidence

The visitor kit's hand-over left the logo files out of its approval ("They need their own approval before slice 3
of spec 134 is built"). On 2026-11-16 the owner was shown the "Logo set" artboard, with what each row draws and its
brass values, and answered "Approve logo set". The approved design is the functional contract (DECISIONS #115).

## Approved scope

1. **Horizontal lockup, two fixed-colour files.** "Core" in the neutral colour; only the terminal "x" and the
   centre module in brass. For light backgrounds: neutral `#14151a`, brass `#ad8643`. For dark backgrounds:
   neutral `#f4f5f7`, brass `#c9a25e`. Drawn on white `#ffffff`, the quiet band `#f3f4f6`, graphite `#14151a` and
   the dark page `#16181d` (FR-018, FR-019).
2. **The sign-in lockup by appearance.** Dark admin appearance: the dark-background lockup on `#0d0e11`. Light: the
   light-background lockup on the light login ground. System draws the dark lockup, because the card is dark-first
   under System (spec 060; DECISIONS #647). A logo set in CoreX Settings still replaces
   it. Today's sign-in (black corner modules and divider on near-black, the whole word in brass) is the failure this
   replaces (FR-020, SC-006).
3. **Stacked lockup**, light and dark, for square places; **the symbol** alone, light and dark (FR-019).
4. **Knockout:** one ink, graphite, on a brass `#c9a25e` tile, for print and stamps (FR-019).
5. **Favicon:** the symbol on a graphite tile, so it reads in a light and a dark browser bar. One SVG and PNG at 16,
   32, 180 and 512px; the 180 and 512 files fill the square (phones round the corners). The CoreX theme links it only
   when the site has no site icon (FR-021, FR-022, SC-007).

Geometry and the wordmark are today's, from the approved Core X mark (`plugins/corex-config/assets/brand/
logo-manifest.json`: five modules on a 48 by 48 grid, Space Grotesk 600 outlines). Only the colours and the new
variants change. `corex-mark.svg` is removed (FR-023). The artboard sets the word as live text, so its horizontal
lockup's divider sits 3.5px further out and its word about 7% larger; the owner confirmed the master geometry on
2026-11-20 (DECISIONS #647).

## Explicit exclusions

- The page artboards of the same canvas (see Design source).
- The CoreX design system's Logos group held the files of 2026-11-15. It was brought into step from the generated
  files on 2026-11-20 (design system Version 12), after slice 3 merged, so the design system and the code show one
  logo set; `corex-mark.svg` is retired there too.
- No new logo geometry, no animated mark, no client logo.

## Accessibility expectations

- Every module and the word differ from the ground behind them by at least 3:1 (SC-006 names the four corner
  modules): measured on the login in Dark and Light by pixel samples (the light brass centre and "x" read about 3:1,
  2.95 to 3.1 as the login's glow moves, and are recorded, not held to 3:1). The divider
  is a decorative hairline drawn as the approved artboard draws it (`#c2c8d1` light, `#343b47` dark, 1.55:1); a logo
  carries no contrast requirement in WCAG 2.2 (1.4.3, 1.4.11). Corrected 2026-11-17, when the build measured it.
- A lockup used as an image of text has an accessible name ("CoreX"); the symbol and the favicon are decorative.

## How to change this design

The change starts on the artboard (or in the design system once its Logos group is updated), then reaches the code
through `scripts/generate-logo-assets.py`, never by editing an SVG by hand.
