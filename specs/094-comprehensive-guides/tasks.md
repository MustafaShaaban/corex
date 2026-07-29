# Tasks — Spec 094

- [x] T001 `OrientationGuide` — what CoreX is, the menu, the first week; gated on `read`
- [x] T002 `SettingsGuide` — 8 topics covering all 9 sections and 42 fields
- [x] T003 The three behaviours the screen cannot explain about itself
- [x] T004 `EverydayScreenGuides` — Overview, Add-ons, Forms & Flows, Setup Wizard
- [x] T005 Compose in `CorexGuides::all()`, one file per new guide
- [x] T006 `SettingsGuideCoverageTest` — every field, matched on its on-screen label
- [x] T007 Verify the coverage test fails when a label is missing (it did, on two)
- [x] T008 Fix the browser gating assertion to name guides rather than count them
- [x] T009 Full suites

## Not done, and why

- **Six specialist guides** (Data Models, Access, Operations & Security, Blog Pro, Insights,
  Notifications). The plan named this seam before starting: orientation plus the everyday screens
  first. These are screens somebody is *sent to*, not ones they browse.
- **Screenshots.** Two exist for 23 topics, and the capture script fails loudly on any id it cannot
  produce — a half-finished set breaks it for everybody.

## Two things worth keeping

- **The coverage test earned itself immediately.** It failed on `captcha.action` and
  `media.webp.min_saving`, where the guide had dropped the parenthetical part of a label —
  "Minimum size saving" against the screen's "Minimum size saving (%)". Small, and exactly the drift
  that makes a reader think a guide is about a different screen.
- **The gating test asserted a count.** `toHaveCount( 1 )` became "expected 1, received 3" — a true
  statement about a number that says nothing about whether gating works. It names guides now, so a
  failure names the one that leaked.
