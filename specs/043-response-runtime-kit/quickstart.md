# Quickstart — Validate 043 Response contract + Runtime kit

Runnable checks that prove the feature end-to-end. Details live in [contracts/](./contracts) and
[data-model.md](./data-model.md); this is the run/verify guide.

## Prerequisites

- The monorepo mapped into a working WP 7.0+ install (junctions on Windows / symlinks elsewhere) with
  `corex-core`, `corex-forms`, `corex-config` active — the project's Environment Gate.
- `composer install` and `npm install` done at repo root.

## 1. Unit — the envelope (Pest, headless)

```bash
composer test -- --filter=ResponseEnvelope
composer test -- --filter=EnvelopeResponder
```

**Expected**: `success()/error()/validation()` produce the documented shapes; `toArray()` exposes only the
contract keys (no secret); the responder maps `validation_failed`→422, forbidden→403, generic→400, success→200.

## 2. Unit — the runtime (Jest, headless)

```bash
npm run test:js -- corex-runtime
```

**Expected**: `Corex.api` normalises a bare body to an envelope and a network/timeout/HTML response to an error
`Result` (never throws); `Corex.forms.bind` blocks submit on a client error and renders field messages; a valid
submit posts once (dedupe blocks the second) and renders server errors; `corex:request:start/end` and
`corex:form:success/error` fire.

## 3. Contract — every endpoint conforms (SC-002)

```bash
# Form submit (invalid → 422 validation envelope)
curl -s -X POST "$SITE/wp-json/corex/v1/forms/contact" \
  -H 'Content-Type: application/json' -H "X-WP-Nonce: $NONCE" \
  -d '{"email":"nope"}' | jq

# Insights run + Data list (admin nonce) → envelope-shaped bodies
```

**Expected**: failure bodies carry `ok:false`, `code`, `message`, and (validation) `errors{}`; success bodies carry
`ok:true`, `message`, `data{}`. No key outside the contract; no secret. The legacy `errors`/`values` keys still
present (backward compat).

## 4. Browser smoke (environment-gated — run when Apache + a browser are available)

1. Open a page with a Corex contact form. Submit empty → the required fields show inline errors, **no** network
   request leaves (Network tab), focus lands on the first invalid field.
2. Fill validly + submit → the button disables, a spinner shows, the region reports busy to a screen reader
   (`aria-busy`), and on success the form resets with a success status. Double-clicking submit sends **one**
   request.
3. In wp-admin → **Corex → Insights**, click "Run check" → the card shows a loading state then the result, driven
   through the shared runtime. Repeat on **Corex → Data** (list + delete).
4. Switch the site to RTL (Arabic) → spinner/overlay/status mirror correctly (logical properties).
5. On a page with **no** Corex form/screen, confirm `corex-runtime.js`/`.css` are **not** loaded (Principle VI).

```bash
npm run test:e2e   # tests/e2e Playwright smoke (needs Apache up + npx playwright install)
```

## 5. Guard Gate (before presenting any diff)

```text
clean-code-guard   → envelope/responder/runtime PHP+JS
wp-guard           → enqueue (conditional dep), nonce, escaping, REST mapping
test-guard         → Pest + Jest additions
docs-guard         → docs-app guide + READMEs
```

**Done when**: §1–3 pass headlessly, the Guard Gate is clean, docs updated, and §4 is confirmed (or recorded as
environment-gated). `PROGRESS.md` + `DECISIONS.md` updated; NEXT STEP block present.
