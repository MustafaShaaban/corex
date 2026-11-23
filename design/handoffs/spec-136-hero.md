# Hero Handoff (spec 136, design D-3)

**Status:** Approved

**Approved:** 2026-11-23, by the owner, in session, from the pictures of the 21 artboards with a button
("Approve hero"). DECISIONS #677.

**Design source:** a Design on the CoreX design system in Claude Design, private to the owner:
https://claude.ai/artifact/CvbMefdih5Ej96Z5HjS9an. The boards stay on the owner's private canvas; the repository
keeps no design-tool export.

**Engineering target:** `specs/136-block-editor-standard/spec.md`, slice 9 (User Story 7, FR-050 to FR-056).
Gap G-011 of the Blackstone gap programme; stories US-BLK-06 and US-BLK-14.

## Approved scope

The `corex/hero` block, built on the approved visitor kit (`visitor-kit.md`): its Hero block, Button (brass and
outline), Hyperlink, the dark band and the type steps.

- **Centred, no media.** Today's hero, unchanged: eyebrow, title, text, one brass button.
- **Centred, media behind.** The picture or footage behind the text, the text centred or at the start of the line.
- **Split.** Text at the start, the media at the end of the line in a frame with the kit's card corners.
- **A second action**, as the kit's outline button or as its text link. Each action is drawn only when its text
  and its address are set.
- **The label above the title** is the hero's existing eyebrow.
- **A video**, behind the text or in the Split frame, with a poster and a Pause button.

## Explicit exclusions

- Child blocks inside the hero and a slideshow background (DECISIONS #674).
- The look of the other CoreX blocks.
- The design system itself: what it lacks is listed under "Owed to the design system".

## The scrim

| Setting | Ground | What changes |
|---|---|---|
| None (the default) | none | Today's drawing. Text keeps the page's colours and nothing is promised: on a dark part of a picture the text can vanish. The editor warns when media is behind the text with no scrim. |
| Light | the `band` colour at 75% | The hero becomes the kit's dark band: text turns light, buttons, the link and the focus ring take the band's colours with no rule of their own. |
| Medium | the `band` colour at 82% | As Light. |
| Strong | the `band` colour at 90% | As Light. |

Under any scrim, the text under the title is full ink, not the soft grey, which does not hold 4.5:1 there.

**Contrast measured on a pure white picture, the worst case** (title and text, brass text, brass edge):
Light 7.44, 4.72, 3.40; Medium 9.62, 6.10, 4.40; Strong 12.70, 8.06, 5.82. On a mid grey picture Light gives
12.12, 7.69, 5.55. Lighter scrims fail (65% gives brass text 3.29 on white; 55% gives the title 3.70), so 75% is
the floor and nothing is measured from the editor's picture.

## The Pause button

- The kit's outline button with an icon and the word "Pause" or "Play", 48px tall, at least 95px wide. Its
  accessible name is "Pause background video" or "Play background video".
- Media behind the text: in the bottom corner at the end of the line, 24px from both edges, last in the hero's
  keyboard order. A hero with a video keeps 96px of padding under its content at every width, so the button never
  covers the actions (measured at 1280 and 375px: 24px clear above it). Its ground is solid: label 15.12:1,
  edge 3.08:1, whatever the footage.
- Split: the same button in the frame's corner, 12px in, in the page's colours. Against bare footage the brass
  focus ring cannot be promised (it is 3.35:1 against the button itself), as with the kit's video play button.
- Not drawn when there is no video, under reduced motion, under Save-Data or on a slow connection (the poster
  stays), or with scripts off.

## Desktop, tablet and phone

Drawn at 1280px and 375px. On a phone Split stacks the media under the text, full width in its frame; section
padding is 64px; buttons are at least 44px tall (the brass button 56px, the link 44px). Nothing scrolls sideways
at 375px (scroll width measured).

## Right to left

Logical layout throughout: Split puts the media on the left in Arabic, the Pause button sits in the bottom left
corner, the play icon flips and the pause icon does not. Arabic follows the kit's written rules: IBM Plex Sans
Arabic, line height 1.8 for text, no letter spacing, never uppercase.

## Dark theme

Centred with media behind, Split with a link and Split with an outline button were drawn in the dark theme.

## Owed to the design system

Added there before slice 9 is built, so the code takes them through `theme.json` (AGENTS.md, "UI, UX and front
end", step 7):

- Three scrim tokens, `--wp--custom--scrim--light`, `--medium`, `--strong` (the `band` colour at 75, 82 and 90%).
- A pause icon and a play icon in the kit's style (24 by 24, 1.75px stroke).
- A 44px minimum height for the standalone link inside the hero's actions.
- Three Arabic fixes the drawing made to follow the kit's rules: the lead at line height 1.8, the standalone link
  in the Arabic face, no letter spacing on the grey tile's label.
- The kit's title renders at line height 1.2 where its hero class states 1.05, because a broader heading rule
  wins; the drawing shows what renders.

## Open questions

None for slice 9. If the owner later wants the Split video's focus ring promised against any footage, the Pause
button moves under the frame.

## Addendum D-3a: the hero's slot (approved 2026-11-23)

The owner approved the addendum board D-3a with the word "approve" (2026-11-23, relayed by the gap programme's
coordinator from the Blackstone planning session). His approval also approves DECISIONS #679, which reverses the
child-blocks bullet of #674. Six boards: the slot holding a block, the slot empty, Split, phone width, Arabic, and
the rules. Recorded as DECISIONS #681; slice 9 builds FR-057 from them.

- **Where it sits.** The slot takes the place of the hero's title and text, in both layouts: the eyebrow stays
  above it and the actions below it, 24px from each. With a block in the slot the hero's own title and text are
  not drawn, so the page never shows two headlines or two main headings.
- **No block unless a site names it.** Nothing can be put in the slot by default; a site lists the blocks it
  allows for the hero. Without that list the hero is exactly today's.
- **An empty slot draws the approved hero:** no box, no space, no change. A hero saved without a block stays
  valid, with no deprecation.
- **The scrim and the contrast hold over what the slot holds.** The slot is inside the media and the scrim, so
  its block sits under the same scrim and takes the band's colours. The approved numbers stand (text 7.44, 9.62
  and 12.70:1, brass text 4.72, 6.10 and 8.06:1 on a pure white picture under Light, Medium and Strong). A site's
  block that sets colours of its own is the site's to measure.
- **The Pause button keeps its place:** bottom corner at the end of the line, 24px from both edges, 96px kept
  under the content so it stays 24px clear however tall the block is, and last in the keyboard order, after the
  block and the actions.
- **The motion of a site's own block is the site's.** CoreX's rule (nothing moves by itself unless a site turns
  it on, never under reduced motion, always with a visible control) covers CoreX's own parts, here the video. A
  rotating headline is the site's block, and its motion, control and steady height are the site's to build and
  review.
