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

### C. Blog & Blog Pro — ✅ DONE + RENDER-VERIFIED (this branch)
- **Blog core:** ✅ single/archive/index designed (v0.33.0) with social-share + newsletter + cards + empty
  states. No separate core-Blog settings surface was invented; the required Blog Pro tabs are the admin surface.
- **Blog Pro:** ✅ visible under CoreX with the four designed tabs. Design: `Corex Blog Pro & Analytics.dc.html`.
- **Required:** a visible Blog Pro admin surface with tabs **Analytics · Editorial queue · Comments ·
  Authors**, shown **honestly** — no fake live analytics; reference/sample values only if clearly labelled
  demo/reference; gated actions with the exact reason; no purchase/licensing. Comments tab may reflect real
  WP comment state where supported.
- **Delivered:** `corex-blog-pro` uses an explicit future-add-on/reference banner. Analytics values are labelled
  sample/reference (never live); Editorial queue, Comments, and Authors use real WordPress counts/users. No
  purchase/licensing behavior was added. Dark/light render-verified on 2026-07-03 (commit `e9515fa`).

### D. Data Models — ✅ DONE + RENDER-VERIFIED (this branch)
- **Now:** the designed Models/Records/Import/Export/Migrations structure wraps the existing real catalog,
  read-only record views, import dry-run, CSV export, and truthful migration state.
- **Design:** `Corex Data Models.dc.html` — tabs **Models · Records · Import · Export · Migrations**.
- **Required:** promote to tabs; **Records** = records table + record detail/view + create/edit/delete only
  where a real write adapter exists, else read-only/disabled with reason; **Import** = the existing dry-run +
  validation + rejected-rows report; **Export** = CSV (exists); **Migrations** = the truthful overview
  (exists). Add empty/loading/error/permission + mutation-confirmation states.
- **Delivered:** all five tabs are discoverable and preserve the existing safe mutation boundaries; unsupported
  writes remain disabled with their adapter requirement. Dark/light render-verified on 2026-07-03 (commit
  `6a3e0f3`).

### E. Email Studio & templates — ✅ DONE + RENDER-VERIFIED (this branch)
- **Now:** five discoverable Studio tabs plus six template-detail tabs, all server-rendered from real engine state
  or an exact gated reason.
- **Design:** `Corex Email Studio.dc.html` + `Corex Email Templates Admin.dc.html` — Studio tabs
  **Overview · Templates · Layouts · Partials · Variables**; template-detail tabs **Edit · Preview · Plain
  text · Test send · Routing · Delivery logs**.
- **Required:** template list from the real registry (active/inactive/future state); layouts + partials +
  variables/token browser; preview render where possible; local-capture/dev delivery warning; safe test-send
  if safe; delivery logs if real logs exist, else honest empty; visual editor disabled with exact reason.
  All email subpages discoverable + linked (no orphans).
- **Delivered:** Overview uses the environment-derived delivery advisory plus real sent/failed log counts;
  Templates lists the real registry + rendered subjects and labels every row Registered; Layouts renders the real
  active brand wrapper in a sandboxed iframe; Partials exposes the real system boundary, while Variables derives
  only detectable `{{ path }}` placeholders from registered template output and names their templates. Template
  detail follows the approved Edit/Preview/Plain text/Test send/Routing/Delivery logs order. Preview/Plain text use
  `TemplateRenderer`; logs use `EmailLogRepository` and explicitly state that they are site-wide, not filtered by
  template. Editing/routing/partials remain code-defined with exact reasons. Test Send is disabled because the
  `Mailer` seam returns no per-send result for truthful feedback (rather than pretending a send succeeded).
- **Evidence:** all 11 Studio/detail routes returned HTTP 200 with no page errors/fatals in dark + light; desktop
  screenshots captured for each. Templates/Variables/Preview also verified at 375px with no page overflow; mobile
  template-row overlap was found and fixed, and the variables table now uses a contained horizontal scroller.

### F. Access & Abilities — ✅ DONE + RENDER-VERIFIED (this branch)
- **Now:** the four designed tabs, all real or honestly gated.
- **Design:** `Corex Access & Abilities.dc.html` — tabs **Overview · Role matrix · Audit log · Access
  denied**.
- **Required:** promote to tabs; **Audit log** = real change log if any exists, else honest empty; **Access
  denied** = the designed denied state; keep read-only (no full AAM, no lockout). "Request access" action
  only if it has a real behaviour, else disabled with reason.
- **Delivered:** **Overview** = real role summary cards (count_users per role, CORE/CUSTOM origin,
  granted/total tracked abilities), the tracked capability groups with risk + locked-by-code labels, a
  permissions-plugin conflict notice shown ONLY when a known role-manager plugin is really active, plus the
  existing requirement/your-permissions cards and the read-only statement. **Role matrix** = design legend
  (Allowed/Not granted/Locked-by-code) over the real matrix + no-lockout note. **Audit log** = REAL events
  only: a new `AccessAuditLog` (30-day window, 100-entry cap, autoload-off option) subscribes to a new
  `corex_admin_access_denied` action; honest empty state names exactly what is recorded and why grant/revoke
  entries cannot exist (CoreX never mutates roles). **Access denied** = the designed surface is now the REAL
  denial: `AccessDeniedGate` hooks WordPress's `admin_page_access_denied` so a capability-refused user on any
  `corex-*` page gets the designed content at a true HTTP 403 and the attempt is logged; the shared
  `AdminPage::permissionDenied()` renders the same designed surface in-shell (defense-in-depth) and the tab
  embeds it as a labelled preview. "Request access" is visibly disabled with the exact reason (no workflow
  exists); "Back to Dashboard" replaces the design's "Back to Overview" because a refused user cannot open
  the CoreX Overview either — the truthful target.
- **Also fixed while verifying (shared shell):** the body-level canvas paint referenced `--corex-admin-*`
  tokens that only existed on the descendant `.corex-admin` scope, so it silently never applied — a light
  band leaked below the shell on pages shorter than the wp-admin menu. Tokens now also bind to
  `body.corex-admin-screen` (with `corex-appearance-*` mirroring for pinned themes, like the login), and
  `#wpwrap` is painted. On phones the collapsed matrix table leaked min-content width into the document
  scroll area (page panned sideways at 375px) — fixed via mobile `minmax(0, 1fr)` shell track +
  `contain: layout` on the matrix scroller.
- **Evidence:** all four tabs rendered dark + light at 1440px (screenshots inspected); Overview/matrix/denied
  verified at 375px with no horizontal pan (probe: scrollWidth 375, scrollX locked at 0). REAL denied path
  E2E-verified: a live editor-role user requesting `corex-access` received HTTP **403** with the designed
  content, and the audit tab then showed that real entry ("corex_editor_test tried to open Access &
  Abilities · DENIED"). Pest 929 / 4061 assertions; lint:css clean; token contracts green.

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
- **A–E are done and render-verified:** the full-bleed shell, tokenized links/focus, Blog Pro reference surface,
  Data Models tabs, and Email Studio/detail tabs now match their approved structures while keeping real/gated
  states explicit.
- **F–K remain** (Access tabs, Insights states, Setup scenarios, Operations/Security polish, Forms tabs, Settings
  parity). Each is a real,
  designed, multi-tab screen; they are scoped here and delivered in reviewed batches, honestly gated where a
  safe mutation does not yet exist. This is deliberately **not** faked or stubbed as "done".
