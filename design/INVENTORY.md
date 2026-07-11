# Corex Design Inventory

Use this inventory to track design coverage. Add rows as Claude Design outputs are reviewed; do not treat unreviewed concepts as approved implementation scope.

> **Owner approval update (2026-07-03):** all 44 primary files in the "Corex Final Design Gap-Closure" package
> (`F:\Work\CoreX.zip`) were inventoried for Spec 068. Approved current product surfaces are required
> functionality, not reference screens. Implementation state is tracked in
> `specs/068-admin-product-functional-completion/evidence.md`; this inventory records design approval only.
> Commercial marketplace/licensing concepts remain non-current and may not appear as fake purchasable behavior.

| Area | Screen/component | Status | Priority | Handoff | Notes |
|---|---|---|---|---|---|
| Brand foundation | Logo, color, typography, spacing, radius, borders, shadows, focus, icons | approved | high | [M2 brand foundation](handoffs/brand-foundation.md) | Approved dark-first CoreX direction with brass/gold accent, Core X logo, dark/light behavior, accessibility, brandability, and RTL typography. |
| Navigation | Headers, mobile navigation, mega menus, search, language, account/cart | approved | high | [M3 navigation and footer](handoffs/navigation-footer.md) | Structural/behavioral handoff composed from M2 tokens; keyboard/focus/escape/outside-click, sticky/transparent, responsive, and RTL behavior defined. WooCommerce category mega menu deferred to M9. |
| Footer | Company, product, WooCommerce, newsletter, locations, legal | approved | high | [M3 navigation and footer](handoffs/navigation-footer.md) | Composable column/region patterns ending in a legal/utility row; RTL and reflow defined. WooCommerce-specific footer deferred to M9. |
| Company kit | Full v1 page set and kit setup UX | approved | high | [M4 company site kit](handoffs/company-kit.md) | Structural/content handoff composed from M2 tokens + M3 nav/footer; preview/apply, demo levels, safe reset/adopt/skip/conflict, brand-aware setup, SEO starter, a11y/RTL/responsive. Portfolio/Woo kits excluded (M8/M9). |
| Blocks/components | Front-end and admin component batches | approved | high | [Spec 068](../specs/068-admin-product-functional-completion/spec.md) | Approved inventory and interaction states are functional requirements; prefer native WordPress blocks/patterns where sufficient. |
| Admin product UI | Login, Overview, add-ons, data, settings, setup, readiness/insights | approved | medium | [M6 admin experience](handoffs/admin-experience.md) | PR #58 delivered the truthful-state foundation; the corrective Spec 060 implementation applies the complete approved dark-first/light, scoped `--corex-admin-*`, RTL/responsive/accessibility layer across every current CoreX admin surface. Marketplace/Pro licensing excluded. |
| Forms/email | Field, validation, submission, anti-spam, upload, flows, Inbox, Email Studio | approved | high | [Spec 068](../specs/068-admin-product-functional-completion/spec.md) | Full builder, pipeline, Inbox operations, and safe environment-aware Email Studio are required current behavior. |
| Operations & Security | Operations modes, Security Center / Login Protection, Access & Abilities | approved | high | [Spec 068](../specs/068-admin-product-functional-completion/spec.md) | Required editable, reversible, audited, and lockout-safe behavior; never renames WordPress core. |
| Data Models | Models, records, CRUD, import/export, migrations | approved | high | [Spec 068](../specs/068-admin-product-functional-completion/spec.md) | Management follows declared write adapters, dry-run/preview, audit, snapshot, and rollback contracts. |
| Insights & Setup | Insights widgets, nine-step Setup Wizard, Launch Checklist | approved | high | [Spec 068](../specs/068-admin-product-functional-completion/spec.md) | Real checks/providers/history and safe backup/apply/conflict/rollback behavior; no fake scores or planned widgets. |
| Portfolio kit | Portfolio page set and archive filters | approved | medium | [Spec 068](../specs/068-admin-product-functional-completion/spec.md) | Approved current theme/setup coverage; reuse native posts/taxonomy and company/navigation foundations. |
| WooCommerce kit | Visible kit/layout dependency-gated surfaces | approved | medium | [Spec 068](../specs/068-admin-product-functional-completion/spec.md) | Required visible design surfaces remain dependency-gated when WooCommerce is absent; no hard dependency or fake store state. |
| Authentication/profile | Login, registration, recovery, profile, notifications, sessions | approved | medium | [Spec 068](../specs/068-admin-product-functional-completion/spec.md) | Front-office account behavior belongs in an optional product add-on; theme remains presentation-only and back-office recovery stays separate. |
| Docs/marketing | Docs sidebar, search, command palette, version/reference/copy/navigation | approved | medium | [Spec 068](../specs/068-admin-product-functional-completion/spec.md) | Approved Docs product behavior is required; public commercial marketing/purchase claims remain excluded. |
| Pro/commercial | Licensing, portal, advanced verticals | future | low | - | Do not advance before Free/Core and kits stabilize. |

Allowed statuses: `approved`, `needs revision`, `missing`, and `future`.
