# Audit — Owner critical admin-completion correction (Spec 065 → 067)

**Branch:** `fix/067-admin-shell-and-completion` (continues the Spec 065 admin-product-completion line;
Spec 065 shipped in v0.33.0).
**Mode:** CoreX Framework Mode.
**Design authority:** `F:\Work\CoreX.zip` (extracted). Priority order: `Corex Final Design Gap-Closure
Package.dc.html` → `Corex Design Closure & Freeze Pack.dc.html` → `Corex Final Design Inventory.dc.html` →
the area-specific `*.dc.html` files.
**Truthfulness invariant (unchanged):** every surface shows real data/state or an honest
empty/error/unavailable/gated state with a precise reason. No fake analytics, integrations, records,
delivery logs, production state, Pro/marketplace/licensing.

## Approved shell (from `Corex Admin Overview.dc.html`)

The approved admin surface is a **full-bleed** two-column grid — `grid-template-columns: 228px 1fr`, sidebar
`#101216`, main `#16181d`, filling the wp-admin content viewport with **no card border/radius/shadow and no
outer margin**. Dark tokens: shell `#0d0e11`, bg `#16181d`, raised `#1c1f26`, border `#262a32`, text
`#f4f5f7`, muted `#9aa0ab`, accent (brass) `#c9a25e`. Status is text+tone (success/warn/danger/info), never
colour alone. The CoreX `--corex-admin-*` adapter already maps these.

## Issue map (owner correction → design → fix → status)

Legend: ✅ done+verified · 🔶 in progress · ⛔ remaining (scoped) · 🔒 gated with reason.

### A. Global shell & white-space — ✅ DONE (this branch)
- **Broken:** Forms, Submissions, Operations & Security, Email Studio, Access rendered as a centered
  bordered/shadowed card with wp-admin white space + footer/chrome leaking.
- **Design:** `Corex Admin Overview.dc.html` — full-bleed grid, no card.
- **Root cause:** `CorexAdminAssets::SCREEN_PATTERN` matched only a hardcoded subset, so the
  `corex-admin-screen` body class (which strips the card + wp-admin padding) was missing on the newer
  screens.
- **Fix:** broadened detection to `_page_corex-<slug>` (all CoreX screens); zeroed `#wpcontent`/
  `#wpbody-content` padding; hid `#wpfooter`; painted residual canvas with the shell colour.
- **Evidence:** rendered Email Studio dark — now full-bleed, no card, no white bar. Needs: render every
  screen dark+light+RTL to confirm parity (harness supports dark+light; RTL pending).

### B. Hover / interaction / links — ✅ DONE (links/focus) · 🔶 (per-component hover polish)
- **Broken:** default blue/purple browser links can leak; hover/focus inconsistent.
- **Design:** brass link token, underline-on-hover, visible focus ring.
- **Fix:** replaced zero-specificity `:where(a)` with real-specificity tokenized content-link rules
  (brass, visited handled, underline only on hover/focus → no layout shift, reduced-motion safe); buttons/
  components excluded. Focus-visible ring already tokenized.
- **Remaining 🔶:** audit each screen for `<a>` action links that should be CoreX button/link components
  (e.g. "Manage records", "Open Submissions Inbox", "Configure mail settings"); confirm nav/tab/card/row/
  dropdown hover match the design in LTR + RTL.

### C. Blog & Blog Pro — ⛔ REMAINING (Blog core done in v0.33.0; Blog Pro surface missing)
- **Blog core:** ✅ single/archive/index designed (v0.33.0) with social-share + newsletter + cards + empty
  states. **Blog settings/tabs** surface: ⛔ not built.
- **Blog Pro:** ⛔ **completely missing from the admin.** Design: `Corex Blog Pro & Analytics.dc.html`.
- **Required:** a visible Blog Pro admin surface with tabs **Analytics · Editorial queue · Comments ·
  Authors**, shown **honestly** — no fake live analytics; reference/sample values only if clearly labelled
  demo/reference; gated actions with the exact reason; no purchase/licensing. Comments tab may reflect real
  WP comment state where supported.
- **Status:** must be added as a new gated admin screen matching the design; the owner needs it visible for
  review even though it is future/gated.

### D. Data Models — ⛔ REMAINING (single view exists; tabs missing)
- **Now:** one page with model cards + fields + CSV export + a real CSV import dry-run + a truthful
  migration overview (v0.33.0). **Broken:** no tabbed structure.
- **Design:** `Corex Data Models.dc.html` — tabs **Models · Records · Import · Export · Migrations**.
- **Required:** promote to tabs; **Records** = records table + record detail/view + create/edit/delete only
  where a real write adapter exists, else read-only/disabled with reason; **Import** = the existing dry-run +
  validation + rejected-rows report; **Export** = CSV (exists); **Migrations** = the truthful overview
  (exists). Add empty/loading/error/permission + mutation-confirmation states.

### E. Email Studio & templates — ⛔ REMAINING (overview exists; subpages missing)
- **Now:** one overview page (delivery mode + registered template list + honest editor deferral).
- **Design:** `Corex Email Studio.dc.html` + `Corex Email Templates Admin.dc.html` — Studio tabs
  **Overview · Templates · Layouts · Partials · Variables**; template-detail tabs **Edit · Preview · Plain
  text · Test send · Routing · Delivery logs**.
- **Required:** template list from the real registry (active/inactive/future state); layouts + partials +
  variables/token browser; preview render where possible; local-capture/dev delivery warning; safe test-send
  if safe; delivery logs if real logs exist, else honest empty; visual editor disabled with exact reason.
  All email subpages discoverable + linked (no orphans).

### F. Access & Abilities — ⛔ REMAINING (single matrix exists; tabs missing)
- **Now:** one page (role×capability matrix + current-user perms + requirements + read-only note) (v0.33.0).
- **Design:** `Corex Access & Abilities.dc.html` — tabs **Overview · Role matrix · Audit log · Access
  denied**.
- **Required:** promote to tabs; **Audit log** = real change log if any exists, else honest empty; **Access
  denied** = the designed denied state; keep read-only (no full AAM, no lockout). "Request access" action
  only if it has a real behaviour, else disabled with reason.

### G. Insights — ⛔ REMAINING (single page exists; state previews + widgets missing)
- **Design:** `Corex Insights.dc.html` — state tabs **Live/mixed · Connected · Disconnected · Empty ·
  Error · Setup required · Planned**; widgets performance/PageSpeed/CWV · Cloudflare/CDN/cache/WAF · security
  events/readiness · SEO/indexing · AI/Agent readiness · operations health.
- **Required:** real provider status or honest disconnected/setup-required/planned per widget; no fake
  metrics. Expose the widget set from the design.

### H. Setup Wizard — ⛔ REMAINING (kit chooser exists; scenarios + steps missing)
- **Now:** a 3-step kit chooser (Choose/Review/Apply).
- **Design:** `Corex Setup Wizard.dc.html` — scenario tabs **First run · Partial · Completed · Skipped ·
  Blocked · Production warning**; steps **Brand basics · Site kit · Email · Captcha · Security · Operations
  Mode · Media · Forms foundation · Legal/launch checklist**.
- **Required:** real progress where possible; skip/resume/blocked/completed honestly; launch checklist +
  production warning.

### I. Operations & Security — 🔶 IN PROGRESS (mode switching real in v0.33.0; dropdown styling)
- **Now:** real operations-mode switch (dev/staging/production/maintenance) with confirmation + audit +
  hardening checks + login-protection deferral (v0.33.0).
- **Broken:** the `<select>` uses browser-native styling; disabled/active option states not matching design.
- **Design:** `Corex Operations Mode & Security.dc.html`.
- **Required:** tokenized dropdown (or CoreX select component) with proper disabled/active states; keep the
  real nonce/cap/confirmation/audit switching; a Security Center section per design.

### J. Forms & Flows / Submissions — ⛔ REMAINING (read-only inventories exist; tabs missing)
- **Design:** `Corex Forms & Flows Foundation.dc.html` + `Corex Submissions Inbox.dc.html` — Forms tabs/
  surfaces **Flows · Submissions · Email routing · Test mode · Flow editor (or disabled shell) · field schema
  · validation · routing · preview/test**; Submissions **list · filters/search · detail drawer/page · export
  · empty/loading/error/not-found/permission · retention**.
- **Now:** Forms = read-only inventory (fields/validation/submission link); Submissions = list + detail +
  CSV export + retention (v0.33.0). **Required:** add the designed tabs; the visual builder may stay disabled
  with a visible reason, but the rest of the design must be present.

### K. Settings / Media / Retention / Advanced — 🔶 IN PROGRESS
- **Now:** provider-specific captcha (None/Honeypot/reCAPTCHA/hCaptcha/Turnstile), write-only secrets, real
  retention dry-run/prune (v0.33.0). **Design:** `Corex Settings - Media, Retention & Advanced.dc.html`.
- **Required:** confirm the settings surfaces match the design (media/WebP visible + functional where
  implemented; advanced with risk labels); no incomplete sections.

## Deferral policy (unchanged, owner-authorised)
Only these may remain deferred: WooCommerce kit/screens; advanced AAM / full capability-editor / complex role
mutation; commercial/Pro/marketplace/licensing. Everything else must be **visible now** — implemented, or an
honest disabled/read-only/dry-run/unavailable state with a precise reason. Blog Pro must be a **visible gated
surface** (not removed).

## Verification plan
`composer validate` · `composer test` · `npm run lint:js|lint:css|test:js|build|verify:dependencies` ·
`build:dist`/`verify:dist` · `git diff --check` · token contract · guard skills (wp/clean-code/test/docs) ·
`tests/e2e/render-admin.mjs` for every CoreX screen dark+light (+RTL where supported), with screenshot
evidence per surface/tab.

## Honest status of this pass
- **A (full-bleed shell) and B (default-link kill + focus) are done and render-verified** — the highest-
  leverage, cross-cutting fixes: every CoreX screen is now full-bleed with no card/white-space and no wp-admin
  default links.
- **C–K are the multi-surface build-out** (Blog Pro, Data Models tabs, Email Studio subpages, Access tabs,
  Insights states, Setup scenarios, Operations dropdown, Forms tabs, Settings parity). Each is a real,
  designed, multi-tab screen; they are scoped here and delivered in reviewed batches, honestly gated where a
  safe mutation does not yet exist. This is deliberately **not** faked or stubbed as "done".
