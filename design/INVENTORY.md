# Corex Design Inventory

Use this inventory to track design coverage. Add rows as Claude Design outputs are reviewed; do not treat unreviewed concepts as approved implementation scope.

| Area | Screen/component | Status | Priority | Handoff | Notes |
|---|---|---|---|---|---|
| Brand foundation | Logo, color, typography, spacing, radius, borders, shadows, icons | needs revision | high | - | Confirm dark/light, accessibility, brandability, and RTL typography. |
| Navigation | Headers, mobile navigation, mega menus, search, language, account/cart | missing | high | - | Define keyboard, responsive, sticky, transparent, and RTL behavior. |
| Footer | Company, product, WooCommerce, newsletter, locations, legal | missing | high | - | Keep variants composable and content-safe. |
| Company kit | Full v1 page set and kit setup UX | missing | high | - | First real website dependency. |
| Blocks/components | Front-end and admin component batches | needs revision | high | - | Prefer native WordPress blocks/patterns where sufficient. |
| Admin product UI | Dashboard, add-ons, data, settings, setup, readiness | needs revision | medium | - | Cover all dependency, entitlement, loading, empty, error, and success states. |
| Forms/email | Field, validation, submission, anti-spam, upload, email templates | needs revision | medium | - | Accessible errors and resilient email-client behavior. |
| Portfolio kit | Portfolio page set and archive filters | missing | medium | - | Reuse company/navigation foundations. |
| WooCommerce kit | Store page set and commerce states | missing | medium | - | Wait for stable gating and Woo-specific design review. |
| Docs/marketing | Docs, references, search, README/OG/preview/release assets | missing | medium | - | Required before public/commercial launch. |
| Pro/commercial | Licensing, portal, advanced verticals | future | low | - | Do not advance before Free/Core and kits stabilize. |

Allowed statuses: `approved`, `needs revision`, `missing`, and `future`.
