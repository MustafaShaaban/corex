# Corrective admin visual evidence

**Branch:** `fix/060-admin-design-implementation`

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
