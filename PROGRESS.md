# Corex — Progress

**This is a resume point, not a history.** It holds the current baseline, what is in flight, and what
to do next. A session that ends does not append here — it *replaces* what is here.

Where the history went, and why you can stop looking for it in this file:

| Question | Answered in |
|---|---|
| What changed in a release? | [`CHANGELOG.md`](CHANGELOG.md) |
| Why was it built that way? | [`DECISIONS.md`](DECISIONS.md) |
| What was the agreed contract? | [`specs/`](specs/) — one directory per feature, written before its code |
| What happened on a given day? | `git log` |
| What works today? | [`PROJECT-STATUS.md`](PROJECT-STATUS.md) |
| What is planned? | [`ROADMAP.md`](ROADMAP.md) |

Until spec 098 this file was 4,589 lines of stacked `RESUME HERE` blocks, forty sessions deep. Only
the top of it was ever read, and everything beneath that top was already recorded in one of the six
sources above — usually better, and always somewhere a reader could find it. (DECISIONS #216)

---

## Baseline

- **Latest published release: v0.42.0** — tag `v0.42.0`, reachable from `main`.
- **`main` is green** on all six required checks, verified against **WordPress 7.1**.

**`main` can go red without a commit, and that is the design.** CI provisions WordPress with
`--version=latest`, so a result here depends on two inputs and only one of them is in git. Core went
7.0.4 → 7.1 on 2026-08-31 and took two browser specs with it while `main` sat untouched and
green-looking; the breakage was finally read off an unrelated dependency PR three weeks later. There
is a nightly run on `main` now so the discovery happens where it belongs (DECISIONS #221). A red
nightly means current WordPress broke us — read it like a red pull request.

**CI is the authority for the integration and browser suites.** A long-lived development install
accumulates rows a freshly provisioned one does not, which is why three integration specs fail
locally and pass in CI. Check a claim against a CI run, not against your machine. `composer test`
additionally segfaults at shutdown on Windows/PHP 8.3 ZTS — it does so on an unmodified tree too.

## In flight

**The October advisory pass landed as #212 on 2026-10-04** (DECISIONS #227). #203 had cleared every
advisory known on 2026-09-09, and by the day it merged the gate on `main` was red again with 26 new
findings. #212 took every patched release that exists — ten packages, one of them (`basic-ftp`
5 → 6) through a new override proven against its parent — removed the `adm-zip` exception that
0.6.1 made unnecessary, and bounded the one finding with no fix, `braces`. The gate reported PASS
for the merged tree that day: zero findings in Composer and the docs site, three findings and three
exceptions in the root.

**The `braces` exception was merged as written, on the owner's instruction.** The policy forbids
excepting a high finding whose exposure is CI, and `braces` does run in CI. It is classed as build
tooling with repository-authored input, following spec 056's precedent. DECISIONS #227 records both
readings and what would overturn the exception.

Merged since v0.42.0: #202 (issue #201 — `composer install --no-dev` no longer drops seven WP-CLI
commands, DECISIONS #225), #203 (the September advisory pass, DECISIONS #226), #212 (the October
one, DECISIONS #227), #213 (the linters and Jest no longer walk `wp-ms/` or session worktrees),
#214 (Jest's module map no longer indexes generated copies), three routine Dependabot bumps —
#205 (`@playwright/test` 1.63.0), #206 (`@wordpress/element` 8.8.0) and #207 (`@wordpress/i18n`
6.29.0) — #216 (the lockfile back in the order npm writes it), #208 (`@wordpress/components`
38 → 41, DECISIONS #229) and #217 (the browser-test sign-in helper, DECISIONS #228).

Two feature specs are open as draft pull requests: spec 101, coming-soon mode (#210), and spec 102,
update-safe client sites (#211). No release is in preparation.

No dependency pull request is being held. **The `@wordpress/components` major, held since #186, landed as
#208 on 2026-10-04.** The build does not bundle that library — every script reads it from the copy
WordPress ships — so the bump was verified by building at 38.0.0 and at 41.0.0 and comparing the
output: 169 files, identical. DECISIONS #229 has the evidence and the rule it leaves for the next
major of an externalised package.

**`fix/jest-haste-collisions` — tooling only, merged as PR #214 on 2026-10-04.** It follows #213, merged on
2026-10-04, which keeps the linters and Jest out of `wp-ms/` and `.claude/worktrees/`, so
`npm run lint:js`, `lint:css` and `test:js` report on Corex again on a machine that has the local
multisite install. #214 also takes `wp/`, `wp-ms/`, `dist/` and `.claude/worktrees/` out of
Jest's module map: on a machine with a `dist/` build or an agent session worktree, a cold-cache
`npm run test:js` passed and printed nine "Haste module naming collision" warnings, one per package
name it found twice. The suite is unchanged at 54 suites and 442 tests. CI never builds `dist/` and
has no worktrees, so it never printed them.

**`fix/e2e-flaky-helpers` — browser-test helpers and CI only, merged as PR #217 on 2026-10-04.** The
browser job failed three times on diffs that changed no runtime code: the nightly on 2026-09-21,
#210 and #211. Two of the
three were one bug in `signInAs`. WordPress's login page moves focus to the username field 200ms
after it renders; when that lands in the middle of Playwright typing the password, the password goes
into the username field and the browser refuses to submit the form. The helper then waited on an
error message that was never coming until the test timed out, so its retry passes never ran. It now
checks that each field holds its credential before submitting, and reads a refusal without waiting
for one. `tests/e2e/helpers.spec.js` reproduces both against a login form it controls
(DECISIONS #228).

The third failure is **not fixed, because it is not a test fault**: `GET corex/v1/flows` answered
500 to three inbox specs in a row. See "Open, and not hidden".

## Recently landed (v0.42.0)

- **Spec 100 — multisite.** `README.md` advertised it; there were three runtime references to it in
  the whole framework, one of which rendered the word "Yes". The headline defect: network-activating
  a CoreX add-on **silently disabled it on every site in the network**, with the Network Plugins
  screen still reporting it active. `integration-multisite` now runs in CI against a real three-site
  network.
- **`main` green under WordPress 7.1** (#193) and a nightly run so core drift is found by a nightly
  rather than by a dependency PR three weeks later.
- **Dependency advisories cleared** (#194, #196) — the gate passes with one bounded exception.

## Next

The next *feature* spec is an owner decision; [`ROADMAP.md`](ROADMAP.md) §17 lists the candidates in
order. **M3 (navigation and template parts) and M4 (the company-page contract)** are the substantive
product direction — everything else on that list is remediation or productization.

Roadmap presence does not authorize implementation (`ROADMAP.md` §16).

## Open, and not hidden

Each is stated with the file that records it in [`PROJECT-STATUS.md`](PROJECT-STATUS.md):

- Three browser specs are excluded from a fresh-install run — two block-editor specs and the flow
  builder. The list lives in `tests/e2e/playwright.config.js` with the evidence for each.
- **The hidden `/wp-admin/` 404 is distinguishable from a real one by its inline styles.** It carries
  block styles for CoreX blocks that are not on the page; a real 404 does not. Structurally
  unreachable from here — `wp_should_load_separate_core_block_assets()` returns false on `is_admin()`
  before its own filter runs. Measured in DECISIONS #222.
- Three bounded dependency exceptions, each with a named upstream trigger: `extract-zip` twice
  (GHSA-jmr9-qjv8-65gv and its sibling GHSA-7pqw-9j4j-h8q3, which must be removed together) and
  `braces` (GHSA-vfj7-8cjw-p6xm). None has a patched release to take. `extract-zip` is installed
  and never executed; `braces` runs in the linter and the build, on patterns that come only from
  tool defaults. The `extract-zip` pair would clear with `@wordpress/scripts` 36, a toolchain major
  that has not been attempted (DECISIONS #226, #227).
- **Something behind `GET corex/v1/flows` threw on 2026-10-04, and nobody knows what.** Three
  `submissions-inbox` specs in a row got "Request could not be processed." on #211, with nothing
  else running, straight after a seed that had succeeded. Every exception the flow code raises on
  purpose is answered with a 409 or a 422, so this was one it does not expect. The message went to a
  log CI did not keep. It keeps it now, in a file the job names and proves with a probe line on
  every run. `seedSubmission` reports the server's answer instead of a `TypeError`, so the next
  occurrence names itself (DECISIONS #228).
- **The dependency gate is still not a required check**, so a red result on `main` blocks nothing.
  Its weekly run on `main` failed every week from 2026-08-12 to 2026-09-30.
- Nothing enforces that `docs/ar/` mirrors `docs/en/`; five pages had no Arabic counterpart from
  spec 087 until 2026-09-04.
- Arabic typography is proved for layout, not for type.
- Development installs predating spec 091 may hold leaked fixture users.

No security items.

## Before you edit anything

1. [`.specify/memory/constitution.md`](.specify/memory/constitution.md) — the non-negotiable rules.
   They override everything, including this file.
2. [`AGENTS.md`](AGENTS.md) — the precedence order, the Role Gate, and the required handoff format.
3. The active spec in [`specs/`](specs/) for whatever you are about to touch.
