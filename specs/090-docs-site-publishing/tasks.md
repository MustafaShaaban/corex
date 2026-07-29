# Tasks — Spec 090

- [x] T001 Add `site` + `base` to `astro.config.mjs`, both env-overridable
- [x] T002 Verify the build emits `/corex/`-prefixed URLs and still produces 286 pages
- [x] T003 Confirm the sitemap warning is gone now that `site` is set
- [x] T004 Add `pages: write` + `id-token: write` and a `pages` concurrency group to the workflow
- [x] T005 Add `upload-pages-artifact` + a `deploy-pages` job that depends on the build
- [x] T006 Keep the existing downloadable artifact alongside it
- [x] T007 Enable Pages with `build_type: workflow` via the API
- [x] T008 Set the repository homepage to the published URL
- [x] T009 Remove the "GitHub Pages is not enabled" item from both status pages
- [x] T010 Point `README.md` and `docs/en/05-deployment/ci-cd.md` at the live URL
- [x] T011 Correct the stale unit-test count in `README.md` (1704 → 1711)
- [ ] T012 Confirm the first deployment succeeds after merge — **only verifiable on `main`**

## Note

T012 cannot be closed from a branch: `deploy-pages` runs on `main` and there is no way to prove the
deployment works without merging. Left open rather than assumed, and it is the one claim in this spec
not yet verified.
