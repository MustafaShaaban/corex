# Tasks — Spec 089

- [x] T001 Measure: `npm audit` on root (64) and docs-app (7), and read `fixAvailable` on every entry
- [x] T002 Establish that 37 proposed fixes are **downgrades** of `@wordpress/scripts` / `@wordpress/env`
- [x] T003 Establish that `npm audit fix` moves none of the remaining 27 (parents pin them)
- [x] T004 Add `overrides` for the eight transitive packages with published patched versions
- [x] T005 Back out the `minimatch@^10` override — it breaks `eslint-plugin-jsx-a11y`
- [x] T006 Scoped `markdownlint-cli → minimatch@^3.1.5` for the one nested vulnerable copy
- [x] T007 Verify root: build, Jest, `lint:js`, `lint:css` — 64 → 0
- [x] T008 Astro 7: regenerate `docs-app` lockfile on `astro@^7.1.5` + `@astrojs/starlight@^0.41.5`
- [x] T009 Confirm the feared workspace-root expansion did not occur (no webpack/jest/eslint in the lockfile)
- [x] T010 Verify docs build — 286 pages, 7 → 0
- [x] T011 Empty the policy exception list; confirm the gate had correctly failed on 24 STALE entries
- [x] T012 Restore the JSON indentation my scripts had rewritten, so the diff is only the dependency change
- [x] T013 Full suite: Pest, integration, Playwright, dist build + verify
- [x] T014 Update `PROJECT-STATUS.md`, its docs mirror, `README.md`, `ROADMAP.md` §17
- [x] T015 Release v0.40.0 — stamp, CHANGELOG, `PROGRESS.md`, `DECISIONS.md` #206–#207

## Notes

- **T007 is the load-bearing task.** The `minimatch` breakage passed the block build and all 431 Jest
  tests and failed only `lint:js`. Verifying an override by re-running `npm audit` would have shipped
  a broken linter with a better-looking advisory count.
- The v0.39.0 stamper widening (DECISIONS #205) paid for itself here: `wp corex version 0.40.0`
  stamped 23 files in one command, including the two status pages this spec rewrote.
