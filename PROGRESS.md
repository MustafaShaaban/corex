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

- **Latest published release:** v0.40.0 — tag `v0.40.0`, reachable from `main`.
- **`main` is green** on all six required checks: PHP unit, JavaScript + both linters, integration
  against a WordPress that CI provisions itself, Playwright, and both CodeQL contexts.
- **Verified on the most recent `main` run:** Pest unit **1727** · integration **356** · Jest **433**
  · Playwright **141** on a fresh install (144 locally; three specs are excluded from a fresh-install
  run, see below) · dependency gate PASS with 0 findings and 0 exceptions.

**CI is the authority for the integration and browser suites.** A long-lived development install
accumulates rows a freshly provisioned one does not, which is why three integration specs fail
locally and pass in CI. Check a claim against a CI run, not against your machine.

## In flight

- **v0.41.0 is prepared but not published.** No `v0.41.0` tag and no GitHub release exist yet.
  Nothing in this repository may describe it as released until they do —
  `tests/repo-hygiene.test.js` fails the build if something does.

## Next

The next *feature* spec is an owner decision; [`ROADMAP.md`](ROADMAP.md) §17 lists the candidates in
order. **M3 (navigation and template parts) and M4 (the company-page contract)** are the substantive
product direction — everything else on that list is remediation or productization.

Roadmap presence does not authorize implementation (`ROADMAP.md` §16).

## Open, and not hidden

Each is stated with the file that records it in [`PROJECT-STATUS.md`](PROJECT-STATUS.md):

- Three browser specs are excluded from a fresh-install run — two block-editor specs and the flow
  builder. The list lives in `tests/e2e/playwright.config.js` with the evidence for each, and the
  comment there is explicit that every reason stays a hypothesis until CI disproves it.
- Arabic typography is proved for layout, not for type.
- Development installs predating spec 091 may hold leaked fixture users.

No security items. No open dependency exceptions.

## Before you edit anything

1. [`.specify/memory/constitution.md`](.specify/memory/constitution.md) — the non-negotiable rules.
   They override everything, including this file.
2. [`AGENTS.md`](AGENTS.md) — the precedence order, the Role Gate, and the required handoff format.
3. The active spec in [`specs/`](specs/) for whatever you are about to touch.
