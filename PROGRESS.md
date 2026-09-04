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

- **Latest published release: v0.41.0** — tag `v0.41.0`, published at
  <https://github.com/MustafaShaaban/corex/releases/tag/v0.41.0>. `main` is ahead of it.
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

**Spec 100 — multisite runtime foundation.** Branch `spec/100-multisite-runtime-foundation`, 78 of 80
tasks done. The runtime, config, schema, activation and site-scope work is complete and its suite
passes against a real three-site network (`composer test:multisite`, 6 specs, 66 assertions). What
remains is T078 (guard gate) and T080 (the PR into `main`). T072 is recorded as not achievable —
see DECISIONS #223.

The headline defect it fixes: **network-activating a CoreX add-on silently disabled it on every site
in the network**, with no notice and the Network Plugins screen still reporting it active.

## Recently landed

- **#193** — `main` green again under WordPress 7.1. Two browser specs, one of which was a real
  cross-file race: `security-access.spec.js` toggles login protection for the whole site while other
  spec files run on other workers, and the shared sign-in helper looked for the login endpoint only
  once. The hidden-admin size assertion was removed rather than re-thresholded (DECISIONS #222).
- **#194** — dependency advisories cleared across all three ecosystems. `verify:dependencies` passes
  with one bounded exception (`extract-zip`, whose advisory range is `*`, reached only through a
  chain nothing here calls).

## Next

Land spec 100, then clear the remaining Dependabot pull requests, then cut a release. After that the
next *feature* spec is an owner decision; [`ROADMAP.md`](ROADMAP.md) §17 lists the candidates.

Roadmap presence does not authorize implementation (`ROADMAP.md` §16).

## Open, and not hidden

Each is stated with the file that records it in [`PROJECT-STATUS.md`](PROJECT-STATUS.md):

- Three browser specs are excluded from a fresh-install run — two block-editor specs and the flow
  builder. The list lives in `tests/e2e/playwright.config.js` with the evidence for each.
- **The hidden `/wp-admin/` 404 is distinguishable from a real one by its inline styles.** It carries
  block styles for CoreX blocks that are not on the page; a real 404 does not. Structurally
  unreachable from here — `wp_should_load_separate_core_block_assets()` returns false on `is_admin()`
  before its own filter runs. Measured in DECISIONS #222.
- One bounded dependency exception (`extract-zip`), with a named upstream trigger.
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
