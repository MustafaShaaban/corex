# Corex — what works, what is partial, what is not built

**Version 0.39.0** · updated 2026-07-29

This page exists so you do not have to read the git history to find out what you are adopting.
Everything below is traceable to something in this repository — a spec, a policy file, a test
exclusion, a source directory. Nothing here is stated from memory, and the sources are named so you
can check any line of it.

Three statuses, and they mean specific things:

| | Meaning |
|---|---|
| **Stable** | Built, tested, and used. Its contract is not expected to change. |
| **Partial** | Real and working, with a named gap. The gap is stated, not implied. |
| **Planned** | Not built. Listed so you can tell "we chose not to yet" from "we forgot". |

One thing worth knowing about how this list is written. The failure this project keeps catching in
itself is **a framework describing a capability it does not have**: an upload validator whose
docblock referenced a store nobody had written, a mail add-on documenting `attach()` against a driver
that took four arguments, a notification action whose label the client could never render. Each was
found by comparing a claim to the code, and each is closed. This page is written to be checkable for
the same reason — so the next such gap is found by a reader, not by a customer.

---

## Foundation

| Module | Status | Notes |
|---|---|---|
| `corex-core` | **Stable** | Container (PSR-11), layered config (`.env` → options → defaults), routing, middleware, security, cache, jobs, notifications, mail seams, admin shell. |
| `corex-config` | **Stable** | The admin product: settings, data models, submissions inbox, access, operations, notifications, insights, blog tools. |
| `corex-forms` | **Stable** | Schema, validation, flow builder, submission pipeline, routing, delivery. File uploads closed in spec 081. |
| `corex-blocks` | **Stable** | Server-rendered blocks with the shared provider/renderer split. |
| `theme/` | **Stable** | FSE block theme. Presentation only — deactivating it breaks presentation, never data. |
| `packages/cli` | **Stable** | `wp corex make:*` generators, `version`, `docs:generate`, release packaging. |

## Add-ons

| Add-on | Status | What is missing |
|---|---|---|
| `corex-email` (Corex Mail) | **Stable** | Templates, routes, queue, attempt log, Email Studio. Live delivery is fail-closed by default — that is deliberate, not a gap. |
| `corex-guides` | **Stable** | Extendable guide registry + support contact (specs 084, 087). |
| `corex-media` | **Stable** | WebP pipeline behind an activation gate that only serves the converted file when it passes. |
| `corex-captcha` | **Stable** | Honeypot, reCAPTCHA, Turnstile, hCaptcha. |
| `corex-ui` | **Stable** | Modal, drawer, tabs, accordion and layout blocks. |
| `corex-careers` | **Stable** | Job posts, applications, CV upload through the protected store (spec 081). |
| `corex-profile` | **Partial** | Registration, an auth gateway and session listing (`src/Account`, `src/Session`). The full profile system is explicitly deferred — `ROADMAP.md` §15. |
| `corex-newsletter` | **Partial** | Signed-token subscribe/confirm, a subscriber store, and a notifier that mails the list on publish (`src/Subscriber`, `src/Subscription`). No campaign composer, no scheduling, no segmentation. |
| `corex-bookings` | **Partial** | Call-request capture, routing to a leader directory, and mail (`src/CallRequest*`). No calendar, availability or rescheduling. |
| `corex-kit-company` | **Partial** | The most complete kit: setup wizard, page coverage, demo levels, SEO. `ROADMAP.md` M4 records the remaining section blocks and `make:site` token inheritance as open. |
| `corex-kit-portfolio` | **Partial** | Project post type and two blocks. Not the page coverage M4 defines — `ROADMAP.md` M8. |
| `corex-kit-woo` | **Planned** | Four files: a blueprint, a gate and a provider. It reserves the seam; it is not a store kit. `ROADMAP.md` M9, waiting on Woo design and stable gating. |

## Known open items

Each of these is recorded somewhere in the repository already. The source is the point — you can
verify every one.

### Dependency advisories — 24 bounded exceptions

`.github/dependency-security-policy.json` holds 24 entries under a documented exception gate, mostly
in the docs-site and build toolchains (`astro`, `esbuild`, `postcss`, `webpack-dev-server`,
`minimatch`, `serialize-javascript`, `markdown-it`, and others). Four of them are unblocked only by
the Astro 7 migration, which is itself held by a packaging question: regenerating
`docs-app/package-lock.json` makes npm expand the `file:..` workspace root into the docs tree, taking
that lockfile from 5 advisories to 17.

**These are open.** They are governed rather than ignored — the policy file carries a reason and a
review date for each — but a public reader should know they exist before adopting.
*Source: `.github/dependency-security-policy.json`, `ROADMAP.md` §17.*

### Three browser specs are excluded from a fresh-install run

Two block-editor specs trade a failure between them: whichever opens the editor *first* fails to see
the inserter, demonstrated in both directions. A trace rules out console errors, failed requests,
`php -S` versus nginx, worker starvation and the welcome-guide modal; timeouts from 30s to 150s all
failed, and 150s destabilised neighbouring specs. A third, the flow-builder spec, times out
mid-interaction and — unlike the editor pair — has **not** been shown to be environmental, so it may
be a real slow path.

The editor itself works; `smoke.spec.js` clicks that inserter successfully whenever it is not first.
These are real coverage gaps, diagnosed down to "needs fresh eyes".
*Source: `tests/e2e/playwright.config.js`, `CANNOT_RUN_ON_A_FRESH_INSTALL`.*

### A one-pixel RTL overflow on the access screen

`corex-access` overflows horizontally by 1px in RTL at 375px. Measured, reproducible, open.
*Source: `PROGRESS.md`.*

### Arabic typography has layout proof, not type proof

The RTL acceptance matrix forces `dir="rtl"` onto English strings, so it proves the layout holds and
proves nothing about Arabic typography. Bidi artifacts visible in those cells belong to the fixture,
not the product. An Arabic catalogue and its own pass are still owed.
*Source: `specs/079-admin-errors-access-request/evidence/after/acceptance-matrix.md`, DECISIONS #196.*

### Test-suite hygiene

`clearPendingRequests` in `access-request.spec.js` clears only the current requester's rows, so failed
runs accumulate. A local suite failed twice until 133 stuck pending requests were cleared. No product
impact; it will cost somebody an hour.
*Source: `PROGRESS.md`.*

### GitHub Pages is not enabled

`.github/workflows/docs.yml` regenerates the class reference and builds the docs site on every push
to `main`, then uploads the result as an artifact. Publishing is a repository setting, and it is off.
*Source: `.github/workflows/docs.yml`.*

### Branch protection does not require every check

Five of six CI checks are not marked *required* in branch protection. All six run on every PR; five
of them cannot block a merge.
*Source: `PROGRESS.md`. Owner action, not a code change.*

## Deliberately not built

These are decisions, not omissions. `ROADMAP.md` §15 records them, and §16 makes the rule explicit:
roadmap presence does not authorize implementation.

Front-office editor workspace · header builder · mega-menu builder · full client portal · full
auth/profile system · Pro licensing UI · animation in wp-admin · advanced WooCommerce internals.

## What is next

`ROADMAP.md` is the durable plan; this is the summary.

- **M3 / M4** — navigation, template parts and the complete company-page contract. The substantive
  product direction, and the only items that would be *feature* specs rather than remediation.
- **M5** — component batches, built when a kit proves it needs them rather than all at once.
- **M8 / M9** — portfolio and WooCommerce kit completion.
- **Capability Inspector / System Map** — every provider, seam, job and integration with live health.
  Spec 074 added a bounded version to the Models screen; the full map is its natural successor and is
  deliberately not in 074's scope.
- **Astro 7 for `docs-app`** — unblocked technically, held by the lockfile question above.

Nothing here is authorized by appearing here. That is `ROADMAP.md` §16, and it is the rule this
project has kept to.

---

## How this repository records itself

If you are wondering why `PROGRESS.md` is 420 KB and `DECISIONS.md` holds 201 numbered entries: that
is the method, not clutter. `specs/` holds the reviewed contract for every feature written before its
code. `DECISIONS.md` holds the reasoning for anything non-obvious, including the ones that turned out
wrong. `PROGRESS.md` is the running session log.

They are kept because the durable artifact is the record, and because several of the defects this
project has closed were found by reading what it had previously claimed. `README.md` has the short
version.
