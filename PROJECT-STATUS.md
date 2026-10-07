# Corex — what works, what is partial, what is not built

**Version 0.43.1** · updated 2026-10-04

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
| `corex-config` | **Stable** | The admin product: settings, data models, submissions inbox, access, operations, notifications, insights, blog tools. Operations has five modes; Coming soon serves a launch page the theme owns, with a preview link for people without an account (spec 101). |
| `corex-forms` | **Stable** | Schema, validation, flow builder, submission pipeline, routing, delivery. File uploads closed in spec 081. |
| `corex-blocks` | **Stable** | Server-rendered blocks with the shared provider/renderer split. |
| `theme/` | **Stable** | FSE block theme. Presentation only — deactivating it breaks presentation, never data. |
| `packages/cli` | **Stable** | `wp corex make:*` generators, `version`, `docs:generate`, release packaging. `make:site` generates what a client repository needs to take framework updates; `npm run verify:framework` checks it (spec 102). |

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

### Arabic typography has layout proof, not type proof

The RTL acceptance matrix forces `dir="rtl"` onto English strings, so it proves the layout holds and
proves nothing about Arabic typography. Bidi artifacts visible in those cells belong to the fixture,
not the product. An Arabic catalogue and its own pass are still owed.
*Source: `specs/079-admin-errors-access-request/evidence/after/acceptance-matrix.md`, DECISIONS #196.*

### Development installs may hold leaked test fixtures

`AccessRequestFormTest` used to create a subscriber per test and never delete it. Fixed in spec 091,
but an install that ran the suite before that still holds them — 311 on the one where this was found.
Each carries a pending access request, and the denied surface renders its *pending* state when one
exists, so enough of them make `access-request.spec.js` find a confirmation where it expects a form.

Repository state is correct; this is only about installs that predate the fix:

```bash
wp user list --field=user_login | grep '^corex-079-requester-' | xargs -r -n1 wp user delete --yes
```

*Source: `specs/091-rtl-overflow-and-fixture-leak/`.*

## Closed, and worth knowing were closed

Two entries stood under *Known open items* in v0.39.0 and no longer do. They are kept, briefly,
because "was this ever a problem, and how was it dealt with" is a fair question to ask of a project
you are evaluating.

### Dependency advisories — six bounded exceptions

`npm run verify:dependencies` reports **PASS** across Composer, the root npm workspace and the
docs-site npm workspace as of 2026-10-07: zero findings in Composer and the docs site, and in the
root, **six findings covered by six exceptions**. That date matters. An advisory is published by
somebody else against a tree that did not move, so this is a measurement, not a property — the
day before, with nothing changed here, the gate failed on thirteen advisories, eleven of them
published on 2026-10-05 or 2026-10-06.

None of the six is a fix that was skipped. Each is an advisory with no release this tree can take:

| Package | Advisory | Why it cannot be closed |
|---|---|---|
| `simple-git` 3.36.0 | GHSA-x6jw-m9v5-85vh (critical), GHSA-g4wm-2vf7-vfgr, GHSA-858h-whjf-mvg5 | Fixed in 4.x only. It is installed for `@wordpress/env`, whose latest release requires `^3.32.3` and calls the package as a function; 4.x exports an object, so an override breaks `wp-env start` instead of fixing it. Tried, and it failed. |
| `@simple-git/argv-parser` 1.1.1 | GHSA-v5rq-49vh-5v5c (critical) | The parser `simple-git` 3 depends on; the fixed 2.0.1 belongs to `simple-git` 4. |
| `sprintf-js` 1.0.3 | GHSA-hp3w-g68c-fv3c | Every published version is in range. Installed for `argparse`, which only `js-yaml`'s command-line entry point loads, and nothing here runs that command. |
| `braces` 3.0.3 | GHSA-vfj7-8cjw-p6xm | 3.0.3 is the latest release and the advisory covers `<=3.0.3`. It arrives through `micromatch`, which the latest `fast-glob`, `stylelint` and `http-proxy-middleware` all still require. |

**The `simple-git` four are the ones to read carefully: two are rated critical, and the code
runs.** `npm run env:start` clones WordPress through it. Each advisory is a way round
`simple-git`'s refusal of dangerous git options, which matters where somebody else chooses what it
is given. Here that is `wp-env.json` in this repository — one git source, `WordPress/WordPress` —
and a person who can change that file can already run any command through wp-env's own
`lifecycleScripts`. It runs on a developer's machine only: no workflow uses wp-env, and nothing
under `node_modules` is shipped. The policy allows a critical finding to be excepted when its
exposure is neither the shipped runtime nor CI; these are reviewed a month sooner than the rest,
on 2026-11-30, and are removed by a lockfile update the day `@wordpress/env` moves to
`simple-git` 4.

**`braces` also runs** — in `npm run lint:css` and `npm run build`, locally and in CI. The defect
is a stack overflow on a deeply nested brace pattern, and the only glob patterns that reach it here
are the built-in defaults of `@wordpress/scripts`: no tracked file imports a glob library and there
is no custom webpack, stylelint or dev-server proxy configuration. Triggering it would take a
commit to this repository's tooling configuration, and the result would be one failed job. It is
not in any built asset. The policy forbids excepting a high finding whose exposure is CI, so this
rests on a judgement that the exposure is build tooling with repository-authored input — the same
judgement spec 056 made for `minimatch` and `brace-expansion`. The exception says what would
overturn it.

The two `extract-zip` exceptions are gone, with the package. `lighthouse` is overridden to 13,
which brings `puppeteer-core` 25 and `@puppeteer/browsers` 3.x, and that unpacks without
`extract-zip`. Nothing here runs that chain — the browser suite drives `@playwright/test`
directly — so the override was checked for what can be checked: the tree installs, and the one
package that depends on `lighthouse` loads against it. `@wordpress/scripts` 36 installs the same
versions by itself, and the override goes when that toolchain upgrade (ESLint 10, stylelint 17) is
done.

Every exception names its dependency path, compensating control, review date and upstream removal
trigger in `.github/dependency-security-policy.json`.

**This section has been wrong twice by standing still.** It said "none open" for a month while the
gate failed on `main`, and after that was corrected it went on saying "one bounded exception"
while the policy file held three. Both were true when written. The gate has run on every pull
request and weekly on `main` since DECISIONS #224, and is **not a required check**, so a red result
on `main` blocks nothing and is only seen by whoever looks. (DECISIONS #224, #226, #227)

This was 24 bounded exceptions as recently as v0.39.0. Closing them needed `overrides` rather than
`npm audit fix`: every vulnerable package was a *transitive* one whose parent pinned it below the
patched version, and what npm proposed instead was a **downgrade** of `@wordpress/scripts` from 33 to
19 — which `CONTRIBUTING.md` forbids. The docs-site half was recorded as blocked by a lockfile
question that turned out to depend on the root being clean, so fixing the root unblocked it and the
Astro 7 migration landed with it. (Spec 089, DECISIONS #206)

### Branch protection

All six checks that run on every pull request are **required** on `main`: the PHP unit suite, the
JavaScript suite, integration against a provisioned WordPress, Playwright, and both CodeQL contexts.
Force-pushes and branch deletion are blocked.

Two things are deliberately *not* set, and the reasons matter more than the settings:

- **`dependency-security` is not required.** It runs on every pull request, but it depends on a
  third-party advisory service, and a required check that an outage can fail blocks every merge
  until somebody re-runs it. Requiring it is deferred until it can survive that (DECISIONS #224).
- **Reviews are not required and admins are not enforced.** This is a single-maintainer repository;
  both would lock the maintainer out of their own `main` rather than add a reviewer.

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

Nothing here is authorized by appearing here. That is `ROADMAP.md` §16, and it is the rule this
project has kept to.

---

This page answers one question — *what works today*. `ROADMAP.md` answers what is planned,
`CHANGELOG.md` what changed, `PROGRESS.md` where to resume, and `DECISIONS.md` why. `README.md`
carries the table of which is which.
