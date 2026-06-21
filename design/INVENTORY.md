# Corex Design Inventory

Use this inventory to track design coverage. Add rows as Claude Design outputs are reviewed; do not treat unreviewed concepts as approved implementation scope.

| Area | Screen/component | Status | Priority | Handoff | Notes |
|---|---|---|---|---|---|
| Brand foundation | Logo, color, typography, spacing, radius, borders, shadows, focus, icons | approved | high | [M2 brand foundation](handoffs/brand-foundation.md) | Approved dark-first CoreX direction with brass/gold accent, Core X logo, dark/light behavior, accessibility, brandability, and RTL typography. |
| Navigation | Headers, mobile navigation, mega menus, search, language, account/cart | approved | high | [M3 navigation and footer](handoffs/navigation-footer.md) | Structural/behavioral handoff composed from M2 tokens; keyboard/focus/escape/outside-click, sticky/transparent, responsive, and RTL behavior defined. WooCommerce category mega menu deferred to M9. |
| Footer | Company, product, WooCommerce, newsletter, locations, legal | approved | high | [M3 navigation and footer](handoffs/navigation-footer.md) | Composable column/region patterns ending in a legal/utility row; RTL and reflow defined. WooCommerce-specific footer deferred to M9. |
| Company kit | Full v1 page set and kit setup UX | approved | high | [M4 company site kit](handoffs/company-kit.md) | Structural/content handoff composed from M2 tokens + M3 nav/footer; preview/apply, demo levels, safe reset/adopt/skip/conflict, brand-aware setup, SEO starter, a11y/RTL/responsive. Portfolio/Woo kits excluded (M8/M9). |
| Blocks/components | Front-end and admin component batches | needs revision | high | - | Prefer native WordPress blocks/patterns where sufficient. |
| Admin product UI | Dashboard, add-ons, data, settings, setup, readiness | approved | medium | [M6 admin experience](handoffs/admin-experience.md) | Approved dark-first CoreX admin surface (scoped --corex-admin-*), truthful add-on state model, state-aware settings + captcha, light/RTL/responsive. Marketplace/Pro licensing excluded. |
| Forms/email | Field, validation, submission, anti-spam, upload, email templates | needs revision | medium | - | Accessible errors and resilient email-client behavior. |
| Portfolio kit | Portfolio page set and archive filters | missing | medium | - | Reuse company/navigation foundations. |
| WooCommerce kit | Store page set and commerce states | missing | medium | - | Wait for stable gating and Woo-specific design review. |
| Docs/marketing | Docs, references, search, README/OG/preview/release assets | missing | medium | - | Required before public/commercial launch. |
| Pro/commercial | Licensing, portal, advanced verticals | future | low | - | Do not advance before Free/Core and kits stabilize. |

Allowed statuses: `approved`, `needs revision`, `missing`, and `future`.
