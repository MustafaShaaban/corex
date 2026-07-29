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
- [x] T012 Confirm the first deployment succeeds after merge — **done, run `30410411129`**

## T012, verified

`deploy-pages` runs only on `main`, so this could not be closed from the branch and was left unchecked
rather than assumed. Run `30410411129` — *Regenerate reference + build docs site: success*, *Publish
to GitHub Pages: success* — and then the site itself, because a green workflow is not the same fact
as a site that loads:

| Checked | Result |
|---|---|
| `/corex/` | 200 |
| `/corex/project-status/` | 200 |
| `/corex/_astro/print.*.css` | 200 — the `base` is right, which is what would have broken without it |
| `/corex/pagefind/pagefind.js` | 200 — search index reachable |
| `/corex/sitemap-index.xml` | 200 — the integration that used to warn |
| `/corex/reference/core/boot/` | 200 — a page generated from source, not authored |
