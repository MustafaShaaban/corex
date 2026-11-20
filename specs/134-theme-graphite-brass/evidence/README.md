# Spec 134 evidence: every template before and after

`before/` is `main` at `5f709b25` (the spec merged, before slice 1), `after/` is `main` at `0b8b3296` (slices 1 to 4
merged). Both are the quality suite's own captures (`tests/e2e/quality/screen-matrix.spec.js`) from CI's run of each
commit, on the site `tests/e2e/quality/seed-quality-site.php` prepares (Company kit at Full), signed out, full page:
the artifact `quality-findings` of runs 43752894911 and 44670075428. For each template two cells are kept, both at
1280px:

- `<template>-dark.jpg`: the Dark style, left to right;
- `<template>-light.jpg`: the default style, right to left (Arabic).

The 375px cells of the same runs are in the artifacts.

## What changes on every template (FR-028)

| Change | Before | After | Where |
|---|---|---|---|
| Page ground and text | light: white, text `#11181C`, headings navy `#0B1F3B`; Dark: navy `#0B1F3B` ground, headings cyan `#00C2FF` | light: white, text and headings graphite `#14151a`; Dark: `#16181d` ground, text and headings `#f4f5f7` | slice 1, DECISIONS #641 |
| Accent | cyan `#007A9E` (Dark `#7DD3FC`) | brass `#ad8643` (Dark `#c9a25e`); brass as text is `accent-dark` | slice 1 |
| Main button | navy `primary` with white text, 6px corner, about 58px tall | brass with a graphite label, 10px corner, 48px tall; every button style one height | slice 2, DECISIONS #644 |
| Links | `accent-dark`, a dark cyan `#006080` | `accent-dark`, brass `#76521d`, underlined, `primary` on hover | slices 1 and 2 |
| Focus ring | the cyan `accent` token where a rule applied it | 2px brass, 2px away, on every link, button and field | slice 2 |
| Hero (front page) | a `primary` section: navy in light, a cyan slab in Dark | the dark band: graphite in light, the deepest graphite in Dark, a brass button | slice 2, DECISIONS #640 |
| Call to action | an `accent` section | the brass band: brass with graphite text and a graphite button | slice 2 |
| Card, Section, Secondary, Ghost (corex-ui styles) | their sheet never reached visitors | drawn, in the kit's tokens | slice 2, DECISIONS #643 |
| Required mark on forms | `accent` | `accent-dark` | slice 1 |
| Browser tab | WordPress's icon | the CoreX favicon, when the site has no site icon | slice 3, DECISIONS #647 |

Layout, spacing, type and Arabic rules are R2.1's and do not change, apart from the button's height.

## The login and the logo

The sign-in's lockup was drawn with black corner modules and divider on the dark card and the whole word in brass
(corner modules 1.20:1). It now draws the lockup that suits its card, "Core" neutral and only the "x" and the
centre brass: corner modules and "Core" 15.62:1 on the dark card, 16.72:1 on the light one (slice 3; measured in
`tests/e2e/logo-set.spec.js`). The theme's preview, `theme/screenshot.png`, is a capture of the front page as it now
is (slice 4).

## Client impact

See the changelog's R2.2 section: Muva's sign-in shows the whole mark; Perego keeps its own logo and gets no bands;
both start drawing corex-ui's block styles for visitors.
