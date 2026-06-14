# Quickstart — Validate 044 Admin control panel & integrations

Runnable checks. Details in [contracts/](./contracts) and [data-model.md](./data-model.md).

## Prerequisites

- The monorepo mapped into a working WP 7.0+ install with corex-core + corex-config (+ corex-captcha addon for the
  captcha test) active — the project's Environment Gate. `composer install` done.

## 1. Unit — pure services (Pest, headless)

```bash
composer test -- --filter="DomainStatus|OnboardingChecklist|CaptchaDiagnostic|PsiDiagnostic|SiteUrlReachability|AddonManifest"
```

**Expected**:
- `DomainStatus` returns `configured`/`needs_setup`/`error` per the D2 rules (honeypot → configured; recaptcha
  without keys → needs_setup; a recorded failed test → error).
- `OnboardingChecklist` lists exactly the not-configured domains and reports `allSet` when none remain.
- `SiteUrlReachability::isPublic` is false for `localhost`, `*.local`, loopback, and private ranges; true for a
  public host.
- `PsiDiagnostic` classifies local-url / http-error / quota / invalid-key / invalid-response / ok, and scrubs
  `key=`/tokens from `detail`.
- `CaptchaDiagnostic` classifies ok / missing_keys / invalid_keys / network_error / not_applicable.
- The extended `Addon` manifest exposes the new fields and `missingKeys()` reflects Config.

## 2. Contract — test actions emit the envelope, no secret (SC-003 / SC-006)

```bash
# Captcha test (manage_options nonce) — bad keys → a specific failure, no secret in the body
curl -s -X POST "$SITE/wp-json/corex/v1/captcha/test" -H "X-WP-Nonce: $NONCE" -H 'Content-Type: application/json' -d '{}' | jq
# Insights test against a local URL → code:"local_url"
curl -s -X POST "$SITE/wp-json/corex/v1/insights/test" -H "X-WP-Nonce: $NONCE" -H 'Content-Type: application/json' -d '{"url":"http://corex.local"}' | jq
```

**Expected**: both return the spec-043 envelope; the captcha body contains **no secret**; the local-URL case
returns `code:"local_url"` with an actionable message; `details` is present only for an admin and carries no key.

## 3. Browser smoke (environment-gated — Apache up + a browser)

1. **Corex** settings → the cards are grouped by domain, each with a status badge; an unconfigured domain shows a
   warning + "how to set this up" link; the dashboard shows the onboarding checklist; completing a domain ticks it.
2. Captcha card → pick reCAPTCHA v3 → the site/secret + threshold + action fields reveal; **Test verification**
   reports pass/fail; the secret is never shown.
3. Insights card → **Test** against the local site → the local-URL explanation; against a public URL → reachable.
4. Add-ons screen → each add-on shows summary/description/provides/requires/enable-disable/docs; an add-on needing
   config flags the missing keys.
5. RTL (Arabic) → cards/badges/checklist mirror correctly. Status is legible without color (icon + text).

## 4. Guard Gate (before presenting any diff)

```text
clean-code-guard · wp-guard (cap+nonce/AdminGuard, escaping, remote-get, NO secret in any response) ·
test-guard (Pest/Jest) · docs-guard (configuration + insights guides + add-on docs)
```

**Done when**: §1–2 pass headlessly, the Guard Gate is clean, docs updated, §3 confirmed or recorded as
environment-gated; PROGRESS + DECISIONS updated; NEXT STEP present.
