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
one, DECISIONS #227), #213 (the linters and Jest no longer walk `wp-ms/` or session worktrees) and
#214 (Jest's module map no longer indexes generated copies).

Two feature specs are open as draft pull requests: spec 101, coming-soon mode (#210), and spec 102,
update-safe client sites (#211). No release is in preparation.

One dependency pull request stays open on purpose: **#208, `@wordpress/components` 38 → 40.1**
(Dependabot's replacement for #186 and #200, both of which it closed). npm
resolves 38.0.0 against a `^39.0.0` requirement and reports success, producing a lockfile that
contradicts itself — so it was not bundled into the dependency pass. It is a major in a library more
than twenty admin modules import, which per DECISIONS #220 needs render-time verification in a real
browser. #186 carries what was tried.

**`fix/jest-haste-collisions` — tooling only, merged as PR #214 on 2026-10-04.** It follows #213, merged on
2026-10-04, which keeps the linters and Jest out of `wp-ms/` and `.claude/worktrees/`, so
`npm run lint:js`, `lint:css` and `test:js` report on Corex again on a machine that has the local
multisite install. #214 also takes `wp/`, `wp-ms/`, `dist/` and `.claude/worktrees/` out of
Jest's module map: on a machine with a `dist/` build or an agent session worktree, a cold-cache
`npm run test:js` passed and printed nine "Haste module naming collision" warnings, one per package
name it found twice. The suite is unchanged at 54 suites and 442 tests. CI never builds `dist/` and
has no worktrees, so it never printed them.

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
