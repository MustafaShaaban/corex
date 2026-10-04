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

- **Latest published release: v0.42.1** — tag `v0.42.1`, reachable from `main`.
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

Nothing is in flight on `main`. Two feature specs are open as draft pull requests: spec 101,
coming-soon mode (#210), and spec 102, update-safe client sites (#211). No dependency pull request
is being held.

## Recently landed (v0.42.1)

A patch release. [`CHANGELOG.md`](CHANGELOG.md) has the full entry; the decisions are #224 to #229.

- **`composer install --no-dev` no longer drops seven WP-CLI commands** (#202, issue #201). A
  production install is a `--no-dev` install, and it could not run `wp corex migrate`.
- **Two dependency advisory passes** (#203, #212), the first of which cleared a critical `astro`
  finding in the docs site. The gate passes with three bounded exceptions.
- **The dependency gate runs on every pull request** (#198).
- **`@wordpress/components` 38 → 41** (#208), with three routine Dependabot bumps (#205, #206, #207)
  and the lockfile put back in the order npm writes it (#216). The build does not bundle that
  library, so the bump was verified by building at both versions and comparing the output
  (DECISIONS #229).
- **The linters and Jest no longer walk `wp-ms/` or session worktrees** (#213, #214).
- **The browser-test sign-in helper no longer loses the password to WordPress's focus timer**
  (#217), and the browser job keeps the server's logs when it fails (#219). Two of three recent
  browser failures were that one bug; the third is under "Open, and not hidden" (DECISIONS #228).

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
