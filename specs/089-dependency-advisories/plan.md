# Implementation Plan — Spec 089

**Branch**: `spec/089-dependency-advisories` · **Spec**: `./spec.md`

## Order, and why it is this order

Root first, docs second, and that sequence is the whole finding. The docs exceptions were recorded
as blocked by a lockfile question — regenerating `docs-app/package-lock.json` expands the
`corex-framework "file:.."` workspace root into the docs tree and audits the root's dev tooling. That
is only a problem while the root tooling is dirty. **Fixing the root removes the docs blocker**, and
the policy had recorded the two as independent.

## 1. Measure before assuming

`npm audit` on the root: **64 findings**, all dev tooling. Reading `fixAvailable` for each is what
turned the vague "pinned wp-scripts constraint" into something actionable:

- 37 propose `@wordpress/scripts@19.2.4` or `@wordpress/env@11.8.0`. Installed are `^33.0.0` and
  `^11.11.0` — those are **downgrades**, which `CONTRIBUTING.md` forbids outright.
- 27 report `fixAvailable: true`, and `npm audit fix` moves none of them, because each is a
  transitive dependency its parent pins below the patched version.

So neither of npm's two suggestions was usable, which is exactly why the exceptions existed.

## 2. `overrides`, one at a time, verified after each

`overrides` raises a transitive dependency without touching its parent. Not `--force`, not a
downgrade. Nine were added; **one was backed out**:

| Override | Result |
|---|---|
| `brace-expansion` ^5.0.8 · `serialize-javascript` ^7.0.7 · `uuid` ^14.0.1 · `webpack-dev-server` ^6.0.0 · `@opentelemetry/core` ^2.10.0 · `markdown-it` ^14.3.0 · `linkify-it` ^6.1.0 · `adm-zip` ^0.6.0 | kept |
| `minimatch` ^10.2.6 | **backed out** — `eslint-plugin-jsx-a11y` calls `minimatch`'s CommonJS default export, which 10.x removed. `npm run lint:js` died with `TypeError: (0, _minimatch.default) is not a function`. Build and Jest both passed; only the lint caught it. |
| `markdownlint-cli` → `minimatch` ^3.1.5 (nested) | kept — the last three findings were one nested `node_modules/markdownlint-cli/node_modules/minimatch` on a v3 line. 3.1.5 is past the vulnerable ceiling (`<=3.1.3`) and is the same major, so the API `markdownlint-cli` uses is unchanged. |

The minimatch backout is the reason FR-004 says an override must be *proven* compatible by the
parent's own tooling. A blanket `minimatch` bump improved the advisory count and broke the linter.

Result: **64 → 0**.

## 3. Astro 7 for `docs-app`

With the root clean, delete `docs-app/node_modules` and `package-lock.json` and reinstall on
`astro@^7.1.5` + `@astrojs/starlight@^0.41.5`. The in-place upgrade cannot work (`ERESOLVE`: the
installed starlight 0.40 peers astro 6), which is what "npm cannot bump in place" meant.

Verified afterwards that the feared expansion did **not** occur: the regenerated lockfile contains
zero references to `webpack`, `jest` or `eslint`, and `corex-framework` resolves as a plain link.

Result: **7 → 0**, and the site still builds **286 pages**.

## 4. Empty the policy

With every advisory closed, all 24 exceptions become **stale**, and the gate fails closed on a stale
exception exactly as it should — `verify:dependencies` reported 24 `STALE` lines and `FAIL` before
the list was emptied. That failure is the gate working, not a problem to route around.

## 5. Documentation

`PROJECT-STATUS.md`, its docs-site mirror, `ROADMAP.md` and `README.md` all described 24 open
advisories. They no longer exist, so those passages go — replaced by the state that is now true.

## Verification

```powershell
npm run verify:dependencies   # PASS, 0 findings, 0 exceptions, all three ecosystems
npm run build                 # blocks + admin JS
npm run test:js               # 431
npm run lint:js; npm run lint:css
php -d memory_limit=1G ./vendor/bin/pest        # 1711
php -d memory_limit=1G ./vendor/bin/pest -c phpunit-integration.xml.dist
npm run test:e2e              # 120
npm run build:dist; npm run verify:dist
cd docs-app; npm run build    # 286 pages
```

The whole toolchain is under test here, not a feature. `lint:js` in particular is not optional: it is
the only check that caught the one override that broke something.
