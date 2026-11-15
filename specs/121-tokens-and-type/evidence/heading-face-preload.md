# Evidence: spec-121-T031-heading-face-preload

The font manifest's preload of Space Grotesk (DECISIONS #631) rests on these measurements, made 2026-11-15 on the
branch's own WordPress with a Playwright probe. `system-ui` was rewritten to Verdana to stand in for CI's wider
Linux fallback; with the font file blocked nothing moved, so the swap is the whole shift.

Cumulative layout shift, before the preload:

| Page | Probe | CI (Ubuntu) |
|---|---|---|
| `/` at 375px | 0.0686 | 0.067 |
| `/case-study/` at 375px | 0.0626 | 0.065 |
| `/single-service/` at 375px | 0.059 | 0.065 |
| `/projects/` at 375px | 0.134 | 0.090 |
| `/category/uncategorized/` at 1280px | 0.128 | 0.129 |

The cause, measured: at 40px the h1 "Build something great" is 126px tall (three lines) in the fallback and 84px
(two lines) in Space Grotesk; "Archives: Projects" is 84px against 42px.

After the preload, with `font-display: swap` kept: 0 on every page above, with the font delivered normally,
instantly, or 60ms late. Removing the preload brings the shift back (`/single-service/` 0.059,
`/category/uncategorized/` at 1280px 0.128). At 150ms or more of extra delay the shift returns, as on main.

Tried and not kept: a metric-matched fallback (Arial with `size-adjust` 102.59% bold, 110.89% regular) fixed `/`
but not `/projects/`; `font-display: optional` gave no shift but Chrome then never used Space Grotesk on a first
visit.
