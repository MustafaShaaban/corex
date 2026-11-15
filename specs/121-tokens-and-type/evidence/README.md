# Spec 121 evidence: every template before and after

`before/` is the base branch (`main` at `09a72e8c`), `after/` this branch, on the same scratch site prepared by
`tests/e2e/quality/seed-quality-site.php` (Company kit at Full), signed out, at 1280 and 375px. Each template is one
file per width, named `<template>-<width>.png`; `login-system-<scheme>-1280.png` is the login with the computer in
dark and in light mode. Captured with `node specs/121-tokens-and-type/evidence/capture-templates.js <url> <folder>`;
`probe-front.js` prints the computed values listed below.

## What changes on every template (TC-BRD-03-05)

| Change | Before | After | Why |
|---|---|---|---|
| Spacing between and inside blocks | WordPress's sizes: slug 30 = 10.7px, 40 = 16px, 50 = 24px, 60 = 36px, 70 = 54px, 80 = 81px | the theme's: 16, 24, 32, 48, 64, 96px | WordPress's default sizes are off (#462) |
| Side gutter of the page | 16px | 24px | the same: slug 40 draws what theme.json writes |
| h1 | browser default 32px, weight 700, no tracking | 64px at 1280 (40px at 375), weight 600, -0.03em | type scale (#462) |
| h2, h3 | browser defaults, 24px and 18.7px | 48px and 36px at 1280, fluid down to 34px and 28px | type scale |
| h4 to h6 | browser defaults | 28, 22, 16px | type scale |
| Buttons | 8px radius, system font | 6px radius, Space Grotesk 600, colours change over 140ms | CoreX radius, interface face (#462, #464) |
| Navigation links and form labels | system font | Space Grotesk | interface face (#464) |
| Paragraphs | system font, 1.6 | unchanged | |
| Colours | navy and cyan | unchanged (R2.2) | |
| Call to action block heading | the size never applied (`--2xl` is not a property WordPress prints) | Heading 3 size | the property name is corrected (#462) |

Pages with long checkbox labels (the comment form, the newsletter consent) set them in Space Grotesk too, as form
labels.

## The login

The lockup is unchanged (its fix is US-BRD-05, R2.2). Labels, the button and the links under the form are in
Space Grotesk. With the computer in light mode and the appearance on System, shadows are graphite rather than navy;
choosing Light now gives the same light shadows, overlay, card and backdrop instead of keeping the dark ones.
