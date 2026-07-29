# Tasks — Spec 092

- [x] T001 Write the link test first, and confirm it fails against the current broken build
- [x] T002 `rehypeBaseLinks` in `docs-app/astro.config.mjs` — idempotent, skips `//` and schemes
- [x] T003 Fix `index.mdx`'s hero action, the one path rehype cannot reach
- [x] T004 Rebuild; confirm 286 pages and all seven reported URLs carry the base
- [x] T005 Re-run the link test — zero failures
- [x] T006 `DocsUrl` falls back to the published site instead of a GitHub blob URL
- [x] T007 Update `DocsUrlTest` and `AddonsDocsLinkTest` to the new contract
- [x] T009 Build the docs site in the CI Jest job, so the link test cannot silently skip there
- [ ] T008 Confirm the seven URLs return 200 **after deploy** — only verifiable on `main`

## Note

T008 is the same shape as spec 090's T012: the docs workflow runs on `main`, so the live check cannot
be done from a branch. Left open rather than assumed. Everything else — including that the test
catches the defect — is verified here.
