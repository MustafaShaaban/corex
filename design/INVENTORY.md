# Corex Design Inventory

Use this inventory to track design coverage. Add rows as Claude Design outputs are reviewed; do not treat unreviewed concepts as approved implementation scope.

> **New design package (2026-07-02):** the "Corex Final Design Gap-Closure" package (`F:\Work\CoreX.zip`)
> was inspected and recorded in [063 new-design gap intake](handoffs/063-new-design-gap-implementation.md).
> Its truthful seven-state model governs the rows below. **Frozen means brand + core visual system +
> approved admin foundation only** — every other area carries its real state (owner-review / needs-pass /
> future). CoreX is **not** commercial/marketplace/Pro-purchase ready; no such language is authorized.

| Area | Screen/component | Status | Priority | Handoff | Notes |
|---|---|---|---|---|---|
| Brand foundation | Logo, color, typography, spacing, radius, borders, shadows, focus, icons | approved | high | [M2 brand foundation](handoffs/brand-foundation.md) | Approved dark-first CoreX direction with brass/gold accent, Core X logo, dark/light behavior, accessibility, brandability, and RTL typography. |
| Navigation | Headers, mobile navigation, mega menus, search, language, account/cart | approved | high | [M3 navigation and footer](handoffs/navigation-footer.md) | Structural/behavioral handoff composed from M2 tokens; keyboard/focus/escape/outside-click, sticky/transparent, responsive, and RTL behavior defined. WooCommerce category mega menu deferred to M9. |
| Footer | Company, product, WooCommerce, newsletter, locations, legal | approved | high | [M3 navigation and footer](handoffs/navigation-footer.md) | Composable column/region patterns ending in a legal/utility row; RTL and reflow defined. WooCommerce-specific footer deferred to M9. |
| Company kit | Full v1 page set and kit setup UX | approved | high | [M4 company site kit](handoffs/company-kit.md) | Structural/content handoff composed from M2 tokens + M3 nav/footer; preview/apply, demo levels, safe reset/adopt/skip/conflict, brand-aware setup, SEO starter, a11y/RTL/responsive. Portfolio/Woo kits excluded (M8/M9). |
| Blocks/components | Front-end and admin component batches | needs revision | high | - | Prefer native WordPress blocks/patterns where sufficient. |
| Admin product UI | Login, Overview, add-ons, data, settings, setup, readiness/insights | approved | medium | [M6 admin experience](handoffs/admin-experience.md) | PR #58 delivered the truthful-state foundation; the corrective Spec 060 implementation applies the complete approved dark-first/light, scoped `--corex-admin-*`, RTL/responsive/accessibility layer across every current CoreX admin surface. Marketplace/Pro licensing excluded. |
| Forms/email | Field, validation, submission, anti-spam, upload, email templates | needs revision | medium | [063 gap intake](handoffs/063-new-design-gap-implementation.md) | Owner-review band: Forms & Flows, Submissions Inbox, Email Studio (upgraded from Email Templates). Accessible errors, resilient email-client behavior, delivery-mode suppression in dev/staging. Spec 063 Phase 2. |
| Operations & Security | Operations Mode (8 modes), Security Center / Login Protection, Access & Abilities (AAM-lite) | needs revision | medium | [063 gap intake](handoffs/063-new-design-gap-implementation.md) | Owner-review band. Truthful, reversible, lockout-safe; not a full AAM clone; never renames WP core. Spec 063 Phase 4. |
| Data Models | Models, records, CRUD, import/export, migrations | needs revision | medium | [063 gap intake](handoffs/063-new-design-gap-implementation.md) | Owner-review band. Safe model manager, CSV-first, dry-run before mutation; not a DB admin tool. Spec 063 Phase 3. |
| Insights & Setup | Insights widgets, Setup Wizard, Launch Checklist | needs revision | medium | [063 gap intake](handoffs/063-new-design-gap-implementation.md) | Needs-pass band. Only real checks; connected/disconnected/not-configured states; no fake scores. Spec 063 Phase 6. |
| Portfolio kit | Portfolio page set and archive filters | future | medium | - | Future-only (P5). Reuse company/navigation foundations when prioritized. |
| WooCommerce kit | Store page set and commerce states | missing | medium | - | Wait for stable gating and Woo-specific design review. |
| Docs/marketing | Docs, references, search, README/OG/preview/release assets | missing | medium | - | Required before public/commercial launch. |
| Pro/commercial | Licensing, portal, advanced verticals | future | low | - | Do not advance before Free/Core and kits stabilize. |

Allowed statuses: `approved`, `needs revision`, `missing`, and `future`.
