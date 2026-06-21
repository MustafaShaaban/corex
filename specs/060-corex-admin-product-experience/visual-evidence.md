# Corrective admin visual evidence

**Branch:** `fix/060-admin-design-implementation`

## Owner-review correction pass 3 (2026-06-22)

**1 — Real wp-login.php now shows the approved (dark) CoreX design by default.** Delivery was
confirmed correct (the site's `corex-core` plugin is a symlink to the repo — same inode; the real
login loads `corex-admin-tokens`/`corex-admin-login` at filemtime versions with the `corex-login`
body class). The remaining gap was that the login rendered *light* by default (appearance=system
+ a light OS), while the approved design is dark — so it read as "not the CoreX design". The login
is now treated as a **dark-first brand surface**: appearance `light` opts into the light design;
`dark`/`system`/unset show the canonical dark login. A pristine incognito browser (no scheme
emulation, default settings) now loads the dark design.
- Proof (real, pristine logged-out wp-login.php): `tests/e2e/clip/real-login-default.png`
  (default → dark, body class `corex-appearance-dark`, page bg `rgb(22,24,29)`),
  `tests/e2e/clip/real-login-light.png` (appearance=Light → light, bg `rgb(246,247,249)`). Shows
  the mark + brass wordmark, "Sign in to your workspace", SSO slot + "or" divider, styled card,
  styled fields, password reveal, brass Log In, Remember Me, and footer links.

**2 — Captcha settings reference links.** The Captcha tab fields now carry concise helper copy +
official references: Captcha driver (provider/None helper); Site key + Secret key →
`google.com/recaptcha/admin/create` ("Create reCAPTCHA keys"); v3 score threshold → the v3 score
docs (with "0.5 is a common starting point — adjust after reviewing traffic"); v3 action → the v3
action docs (with "contact_form or login"). All links `target="_blank"` + `rel="noopener
noreferrer"`; the Secret key stays write-only (no saved value rendered).
- Proof: `tests/e2e/clip/captcha-links.png`.

---


## Owner-review correction pass 2 (2026-06-22)

| # | Blocker | Root cause + fix | Proof |
|---|---|---|---|
| 1 | Real wp-login.php unchanged | The CoreX login hooks/classes/CSS **were** applied (curl confirms `corex-login` body class + `corex-admin-login`/`corex-admin-tokens` loaded + subtitle + SSO slot), but those core styles were versioned by the static `COREX_CORE_VERSION` (0.27.0) — on a production-env WAMP the browser served the **cached old CSS**. Now filemtime-versioned (busts every edit). Login also follows the saved appearance option logged-out. | `tests/e2e/login-dark/login-light.png` (appearance=Dark on a light OS → dark login), `tests/e2e/login-light/login-dark.png` (Light on a dark OS → light login); `?ver=<mtime>` on the real login. |
| 2 | Spacing fixed only on Overview | The full-bleed body class only matched Overview because the hook list used `corex_page_*`, but WordPress derives the submenu screen id from the "COREX FRAMEWORK" menu title → real id is `corex-framework_page_*`. `supports()` now matches by the slug after `_page_`. | Live: `corex-admin-screen` body class on all of Overview/Settings/Add-ons/Data/Insights/Setup; full-bleed at 1920 in `tests/e2e/fix2/`. |
| 3 | Dark dropdown unreadable | Native `<select>` option popups are OS-rendered/unreliable in dark mode → replaced both CoreX selects with an accessible in-DOM ARIA listbox (React for the Data filter; progressive enhancement for the Settings/Captcha selects, native kept hidden for submission + no-JS). | `tests/e2e/clip/dark-select-open.png` (Data filter), `tests/e2e/clip/captcha-select-open.png` (Settings appearance) — open dropdowns fully readable. |
| 4 | Key fields need reference links | Added concise helper copy + official reference links (PSI / Cloudflare token / Cloudflare account ID) opening in a new tab with `rel="noopener noreferrer"`; secrets stay write-only. | `tests/e2e/clip/settings-links.png`. |

Cache-bust note: hand-authored CoreX admin source CSS/JS (core shell/tokens/login + the
control-panel/settings assets) are now versioned by filemtime so the owner always sees the
latest on the real site, independent of the release-pinned framework version.

Render output is gitignored — recreate via `node tests/e2e/render-admin.mjs` (set the
`corex_brand_admin_appearance` option to dark/light for the login-appearance proof).

---


## Correction pass (2026-06-21) — screenshot-driven fixes

Rendered with `node tests/e2e/render-admin.mjs` (dark+light) against the live site; clips captured for
specific controls. All proven by live DOM where behaviour matters.

| # | Fix | Proof |
|---|---|---|
| 1 | Full-bleed surface | Body class `corex-admin-screen` strips wp-admin outer padding/margins (CoreX screens only); shell border-radius-0, fills to content-area edges. No outer gaps at 1440 and 1920. |
| 2 | Inner rail = 6 pages | Rail built from live `$submenu['corex-settings']`; shows Overview/Settings/Add-ons/Data/Insights/Setup Wizard with real icons; matches the WP submenu. |
| 3 | Toolbar/select | All toolbar controls normalised to one height (select was 64px → 40px); explicit option colours keep the dropdown readable; New record stays on the row, intentionally disabled. |
| 4 | Real 14-day chart | `TrendableDataSource` → `SubmissionsSource::trend()` from a prepared grouped `$wpdb` query; live DOM: 14 bars, 42 total, zero-filled days. No fabricated values. |
| 5 | Rail is the source control | The rail drives the active source; the select is only the form filter (one real source — rendered intentionally). |
| 6 | Real schema | `SchemaAwareDataSource` → live DOM schema = Record ID(id)/Submitted(datetime)/Form(form)/Name(text)/Email(email)/Message(textarea) + "Derived from captured submissions" note. |
| 7 | Bulk + actions | Select-all + bulk Delete-selected (confirm+nonce); Export-selected disabled with honest reason; New/Edit disabled truthfully; focus-trapped drawer with metadata/footer/Escape/return-focus. |
| 8 | Login follows appearance | Proven: appearance=Dark on a **light** OS → login bg `rgb(22,24,29)`; appearance=Light on a **dark** OS → light. Uses the saved option logged-out, not OS/auth alone. |
| 9 | Setup Wizard works | Real Choose → Review → Apply → Applied flow; stepper tracks the step; apply runs the BlueprintActivator (cap+nonce) then PRG to a truthful applied summary. Rendered all three steps. |
| 10 | Settings | Appearance changes shell + login (scoped, nothing else); logo preview shows the saved image / placeholder when empty; footer current-value renders and updates the admin footer (verified). |
| 11 | Add-ons toggles | Accessible `role="switch"` + aria-checked, keyboard, On/Off label at 12px (readable), disabled+reason for non-actionable. |

---


## Capture-fidelity 20-blocker pass (2026-06-21)

Re-runnable harness: `node tests/e2e/render-admin.mjs <out> [--screens=…] [COREX_W/H]` — authenticates
with an injected administrator session cookie (`tests/e2e/.auth/admin.json`, minted via
`wp eval wp_generate_auth_cookie`), renders every CoreX admin surface in dark + light against the live
site (`http://corex.local`), and is compared to the approved `.dc.html` captures on
`F:/Work/Design project questions answered (3)/`. Throwaway PNG output is gitignored.

| Surface / behaviour | Status | Evidence |
|---|---|---|
| Full-width shell | VERIFIED | Surface fills the wp-admin content area (rendered 1440 + 1920); no centered panel / dead canvas |
| Add-ons toggles | VERIFIED | `role="switch"` + `aria-checked` reflecting real state, On/Off label, keyboard, disabled+reason for non-actionable (live-DOM checked) |
| Settings tabs | VERIFIED | Brand → Mail → Forms → Captcha → Insights; click + arrow/Home/End keyboard; one panel at a time (live-DOM) |
| Brand tab | VERIFIED | Admin-logo framed preview / "No logo set" placeholder, footer value, appearance select, SSO checkbox, Save bar |
| Appearance System/Light/Dark | VERIFIED | Pinning `data-corex-theme="light"` on an OS-dark page forces the light surface (computed bg confirmed) |
| Login | VERIFIED | "Sign in to your workspace" subheading; SSO slot rendered disabled "not configured yet" only when the setting is on (rendered both states) |
| Data explorer | VERIFIED | Rail-driven source/schema/metrics/table; checkboxes + select-all + bulk bar ("20 records selected" / Delete selected); New & Edit disabled+honest; drawer footer (Close/Edit-disabled/Delete) + source/ID meta + Escape-close + focus return (live-DOM) |
| Overview | VERIFIED | Real stat cards + integration cards + all-set state + designed "Recent activity" honest empty panel |
| Insights | VERIFIED (pre-existing) | Grade badges, readiness rows, recommendations, Run check + timestamp, honest environment-gated/error states |
| Setup Wizard | VERIFIED (gated) | Truthfully gated behind the corex-kit-company add-on; when active it inherits the full-width shell, step badges, and brass buttons (rendered by temporarily activating the kit, then restoring) |

Truthful state preserved throughout: installed-only add-ons; write-only secrets; no fake records/sources/
SSO providers/Pro/marketplace; real asset files only (no `data:`/base64, guarded by a test). RTL/200%/full
keyboard remain part of the manual acceptance pass.

---


**Runtime DOM evidence:** WordPress 7.0 at `http://corex.local` returned HTTP 200 for the native login and
lost-password actions. Both retained their native WordPress forms and carried the `corex-login` body class; the login
response loaded `corex-admin-tokens` and `corex-admin-login`. The native check-email message action also loaded both
CoreX styles and retained WordPress message markup.

## Rendered browser matrix

Unlike the first corrective pass (which was source-inspection only), this revision was verified by actually rendering
each CoreX admin surface. A headless Chrome run (Playwright, viewport 1440×900, authenticated as an administrator via
an injected WordPress auth cookie) captured every screen in both `prefers-color-scheme: dark` and `light`, and the
native login screen logged-out. Screenshots were compared against the approved `.dc.html` design captures
(`design/handoffs/admin-experience.md`).

### Defects found by rendering — and fixed in this revision

1. **Form controls were unstyled.** Text inputs rendered with WP's white background and primary buttons rendered in
   WP blue, because the shell styled them through `:where(...)` (zero specificity), which WP core admin CSS overrode.
   Controls are now scoped with specificity that wins: dark input wells, brass primary buttons, themed secondary and
   Gutenberg (`.components-button`) buttons, a brass focus ring, and a single custom select chevron (RTL-aware).
2. **Card/section headings were near-invisible.** Headings without an explicit colour (integration-status cards,
   readiness cards) inherited WP's dark heading colour against the dark surface. All `.corex-admin` headings now take
   the CoreX text token — a fidelity and WCAG 2.2 AA contrast fix.
3. **Palette drift.** Borders, raised surfaces, and semantic colours were lighter/bluer than the approved package;
   the `--corex-admin-*` adapter (dark + light) was realigned to the approved tokens.

### Verified surfaces (dark + light unless noted)

| Surface / mode | Status | Evidence |
|---|---|---|
| Login | VERIFIED | Brass wordmark, themed inputs, brass primary, muted reveal control, ambient grid+glow backdrop |
| Overview | VERIFIED | Eyebrow + display title, stat cards, truthful "all set" state, readable integration-status cards |
| Add-ons | VERIFIED | Installed-only cards, truthful state badges, brass enable/disable controls, dependency gate notices |
| Data | VERIFIED | Dark search/select, mono uppercase table head, brass row actions, themed pagination chips |
| Settings | VERIFIED | Section cards, dark inputs, brass save, disabled captcha section, write-only "Not set" secret indicators |
| Readiness / Insights | VERIFIED | Readable card titles, grade badges, brass run controls, environment-gated findings preserved |
| Light mode | VERIFIED | Complete semantic mapping rendered; links/focus kept darker for AA |
| Dark mode | VERIFIED | Default semantic mapping rendered |

Truthful-state behaviour was preserved throughout: add-on states, write-only secrets, installed-add-ons-only
controls, and environment-gated readiness remain unchanged — only the visual layer was corrected.

### Re-running

Render with a headless Chromium against the live branch at `http://corex.local`, authenticated as an administrator,
in both colour schemes; compare each surface against the matching `.dc.html` design capture. RTL, full keyboard
operation, and 200% reflow remain part of the manual acceptance pass.
