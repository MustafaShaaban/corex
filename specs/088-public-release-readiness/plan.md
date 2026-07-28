# Implementation Plan — Spec 088

**Branch**: `spec/088-public-release-readiness` · **Spec**: `./spec.md`

This spec changes no runtime behaviour except six plugin/theme/composer header URLs. Everything else
is documentation, so the verification that matters is **docs-guard**, not the test suite — though the
suite must stay green to prove the header change was inert.

## 1. One canonical URL (FR-001, FR-002)

Twelve references across six files said `github.com/bseit/corex` while `docs-app` said
`MustafaShaaban/corex`. All now say `MustafaShaaban/corex`:

`composer.json` · `theme/style.css` (Theme URI + Author URI) · `plugins/corex-core/corex-core.php`
(Plugin URI **and `Update URI`**) · `corex-config` · `corex-forms` · `corex-blocks` ·
`docs/en/05-deployment/updates-and-distribution.md` · `docs-app/.../guides/updates.md`.

`Update URI` is the one that mattered: WordPress uses it to decide where an update comes from, so
this was a functional defect shipping in every installed copy, not a documentation typo.

## 2. `PROJECT-STATUS.md` (FR-004, FR-005, FR-008)

New file at the repository root. Every module with stable / partial / planned, and for anything not
stable, what is missing. Every known-open item cites the file that records it:
`.github/dependency-security-policy.json` (24 exceptions), `tests/e2e/playwright.config.js`
(`CANNOT_RUN_ON_A_FRESH_INSTALL`), `PROGRESS.md` (the RTL overflow, the test-hygiene item, branch
protection), `.github/workflows/docs.yml` (Pages), and spec 079's acceptance matrix (Arabic
typography).

Module descriptions were read from the source directories rather than recalled — `corex-newsletter`
has `src/Subscriber` and `src/Subscription` and no campaign composer; `corex-bookings` has
`src/CallRequest*` and no calendar; `corex-kit-woo` is four files.

## 3. `ROADMAP.md` (FR-003)

It named v0.35.x as latest and said "Active now: nothing" above a §17 list running to spec 085.
Updated: the released version, the real verification counts, specs 086–088 added to §17, 086 recorded
as a consumed branch identifier alongside 066/067, and a pointer to `PROJECT-STATUS.md` with a rule
for which wins when they disagree.

## 4. `README.md` (FR-006, FR-007)

The status paragraph led with spec 060 / PR #59 — thirty specs ago — and sent a public reader to
`PROGRESS.md`, a 420 KB session log. Replaced with a current status block, a prominent link to
`PROJECT-STATUS.md`, and a **How this repository records itself** table, so `specs/`, `DECISIONS.md`
and `PROGRESS.md` read as a method rather than as clutter.

## 5. `CONTRIBUTING.md` (FR-009)

Two claims were false. It documented a `develop` integration branch and `feature/NNN-slug` naming —
`develop` does not exist and work is branched `spec/NNN-slug` off `main`. And it said CI runs
"`composer validate`, a PHP lint, and the headless unit suite", when CI gates four suites plus CodeQL.

## 6. `docs-app` (FR-010)

A mirrored `project-status.md`, placed **second in the sidebar** — before Getting Started, because
somebody evaluating this should meet what it does not do before the tutorial. Landing page links to
it first.

## Verification

```powershell
composer validate --no-check-publish
find plugins packages addons -name '*.php' -print0 | xargs -0 -n1 php -l
php -d memory_limit=1G ./vendor/bin/pest       # 1704 — must be unchanged
npm run test:js                                # 431  — must be unchanged
cd docs-app; npm run build                     # 286 pages
```

The suites must be **unchanged**, not merely green: this spec asserts it changed no behaviour.

## docs-guard

Run on the whole documentation diff, because the diff is almost entirely documentation. Six findings,
all fixed — three false numbers in newly written prose (`specs/` directory count, DECISIONS entry
count, PROGRESS file size), one anchor link that may not have resolved, one paragraph promising
entries the page did not contain, and one source type cited in an intro and never used.

That three of the six were false numbers in *this spec's own new writing* is the point of running it.
