# Corrective admin visual evidence

**Branch:** `fix/060-admin-design-implementation`

**Runtime DOM evidence:** WordPress 7.0 at `http://corex.local` returned HTTP 200 for the native login and
lost-password actions. Both retained their native WordPress forms and carried the `corex-login` body class; the login
response loaded `corex-admin-tokens` and `corex-admin-login`. The native check-email message action also loaded both
CoreX styles and retained WordPress message markup.

## Rendered browser matrix

The in-app browser reported no available browser instance. The standalone Playwright launcher was also unavailable
in the managed workspace (`EPERM` while resolving the user-profile path). No screenshots or visual assertions are
claimed from source inspection.

| Surface / mode | Status | Evidence needed when a browser is available |
|---|---|---|
| Login | ENVIRONMENT-GATED | Login, lost password, reset, error/message, focus order, contrast |
| Overview | ENVIRONMENT-GATED | Header, stat cards, onboarding/domain states |
| Add-ons | ENVIRONMENT-GATED | All seven text-labelled statuses and installed-only controls |
| Data | ENVIRONMENT-GATED | Toolbar/table/drawer plus loading/empty/error views |
| Settings | ENVIRONMENT-GATED | Section states, disabled controls, write-only secret indicators |
| Setup Wizard | ENVIRONMENT-GATED | Progress, kit cards, empty and success states |
| Readiness / Insights | ENVIRONMENT-GATED | Loading/error/result cards and environment-gated findings |
| RTL | ENVIRONMENT-GATED | Mirroring, bidi content, logical spacing and drawer edge |
| Light mode | ENVIRONMENT-GATED | Complete semantic mapping and WCAG 2.2 AA contrast |
| Dark mode | ENVIRONMENT-GATED | Default semantic mapping and WCAG 2.2 AA contrast |
| Narrow / 200% zoom | ENVIRONMENT-GATED | Reflow without page-level horizontal scrolling |

These checks must be rerun against the live branch when a compatible browser runtime is available.
