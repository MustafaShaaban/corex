# Tasks — Spec 088

- [x] T001 Canonicalize the repository URL across `composer.json`, `theme/style.css` and four plugin headers
- [x] T002 Fix the `Update URI` headers specifically — the functional half of T001
- [x] T003 Update the two deployment/update docs that quoted the old URL
- [x] T004 Read every module's source directory to establish its real state
- [x] T005 Write `PROJECT-STATUS.md` with a cited source for every entry
- [x] T006 `ROADMAP.md`: released version, verification counts, specs 086–088, 086 as a consumed identifier
- [x] T007 `ROADMAP.md`: pointer to `PROJECT-STATUS.md` and the rule for which wins on disagreement
- [x] T008 `README.md`: current status block and a prominent status link
- [x] T009 `README.md`: "How this repository records itself"
- [x] T010 `CONTRIBUTING.md`: correct the branch model (no `develop`) and the CI description
- [x] T011 `docs-app`: mirrored `project-status.md`, sidebar entry, landing-page link
- [x] T012 Hygiene sweep — tracked build/vendor dirs, env files, `sites/`, secret scan
- [x] T013 docs-guard on the documentation diff
- [x] T014 Verify the suites are unchanged
- [x] T015 `PROGRESS.md` + `DECISIONS.md`

## Deliberately not done

- **The 24 bounded dependency exceptions.** Out of scope per the spec: they are behaviour-changing
  upgrades governed by their own policy and review dates, and hiding them inside a documentation diff
  is exactly the kind of thing this repository writes decisions against. Recorded as a known open
  item instead.
- **Enabling GitHub Pages.** A repository setting; only the owner can change it. Flagged in
  `PROJECT-STATUS.md` rather than described as done.
- **Making the five CI checks required in branch protection.** Same: owner action, flagged not faked.
