# Design Intake — New Design Gap Implementation (Spec 063)

**Status:** Intake recorded · design authority frozen for the approved bands only
**Recorded:** 2026-07-02
**Engineering target:** Spec 063 — New Design Gap Implementation
**Mode:** CoreX Framework Mode

> This is the design-to-engineering intake for the new CoreX design package. It records what was
> inspected and, per the package's own truthfulness rule, what is **frozen**, **ready for owner
> review**, **needs another design pass**, **future-only**, **hidden until implemented**, and
> **legacy/reference-only**. It is an input to the reviewed engineering spec (Spec 063); it does not
> by itself authorize implementation. The active spec's scope section is the authority for what code
> this pass writes.

## 1. Design path used

- **Package:** `F:\Work\CoreX.zip` (owner-supplied, dated 2026-07-02), extracted and inspected.
- The package is the "Corex Final Design Gap-Closure" pass — a truthful design audit, **not** an
  instruction to build everything. It explicitly states: *"CoreX is not commercial-ready,
  marketplace-ready, or Pro-purchase-ready … deliberately avoids 'everything is frozen / build it
  all now' language."*

## 2. Files inspected

**Design authority order (top wins):**
1. `Corex Final Design Gap-Closure Package.dc.html` — the truthful inventory + status model + freeze-language
   corrections + decision buckets + phase mapping. **Primary authority.**
2. `Corex Design Closure & Freeze Pack.dc.html` — freeze/decision pack.
3. `Corex Final Design Inventory.dc.html` — full inventory.
4. Area-specific final screens: `Corex Admin Overview`, `Corex Forms & Flows Foundation`,
   `Corex Submissions Inbox`, `Corex Email Studio`, `Corex Data Models`,
   `Corex Operations Mode & Security`, `Corex Access & Abilities`,
   `Corex Settings - Media, Retention & Advanced`, `Corex Setup Wizard`, `Corex Insights`,
   `Corex Theme - Blog`, `Corex Theme - States`, `Corex Blocks & Components`,
   `Corex Site Kit System`.
5. Reference/older prototypes (lower authority; used only where they do not conflict): `Corex Admin
   Dashboard`, `Corex Admin - Add-ons & Data`, `Corex Admin Login & Settings`, `Corex Options
   Round 2`, `Corex Email Templates` / `Corex Email Templates Admin`, `Corex Forms System`,
   `Corex Header & Nav System`, `Corex Mega Menu System`, `Corex Component Library`, `Corex Blocks`,
   `Corex Brand System`, `Corex Logo System`, `Corex Blog Pro & Analytics`,
   `Corex Theme - Auth & Profile`, `Corex Theme - Pages`, `Corex Docs`, `Corex Landing`,
   `Corex Marketing Assets`, `Corex Site Kit System`, `Corex Motion Hero`.
6. Embedded briefs: `uploads/design.md` (the 31-section design completion brief that produced the
   package) and `uploads/promp.md` (the original roadmap brief). Screenshots under `screenshots/`.

**Repo design state inspected:** `design/INVENTORY.md`, `design/ROADMAP.md`,
`design/handoffs/{admin-experience,brand-foundation,navigation-footer,company-kit}.md`.

## 3. Frozen / implementation-ready items (build against)

From the gap-closure package's `frozen` band — these are settled and safe to build against. They are
**already largely implemented** in the merged milestones (M2 tokens, M3 nav, M6 admin shell/login):

| Area | Repo status | Note |
|---|---|---|
| Brand system, Logo system, Typography, Color tokens | Implemented (Spec 057 / M2) | Confirm accent + exports locked. |
| Light / dark modes, RTL/LTR conventions | Implemented (M2) | Apply per new screen. |
| Admin shell (full-bleed dark), Login | Implemented (Spec 060 / M6) | Locked; reuse the shell for new screens. |
| Core component & block kit | Partly implemented | Popover/drawer/stepper finishing outstanding. |
| Blog social sharing | Design frozen (privacy-friendly, no counts) | Not yet implemented in the theme. |

## 4. Owner-review items (design coherent; owner sign-off is the gate before committed scope)

These carry a `review` status in the package. They are the substance of Spec 063's later batches and
must be built **truthfully** (real state only, no fabricated data):

- **Add-ons** — finish setup-required / missing-dependency / Woo-missing polish. Edition badges stay reference-only.
- **Settings** — secret-missing, locked-by-config, test-failure states; settings taxonomy.
- **Declarative option pages** — conditional fields, repeater, dependency states; canonical reusable pattern.
- **Operations Mode** — the 8-mode model (Development, Staging, Production, Maintenance, Coming Soon,
  Private/Internal, Read-only, Forms Paused); read-only + forms-paused + recovery messaging.
- **Security Center** — login protection beyond "hide wp-admin": hardening checks, activity log,
  safe-misconfig; reversible CLI/config recovery required if a login guard ships.
- **Forms & Flows** — form-vs-flow model + extension points; file upload, repeater, remaining visitor states.
- **Submissions Inbox** — anonymize, resend, spam, assignment polish; retention + anonymize behavior.
- **Email Studio** — upgrade from "Email Templates"; overview, templates, layouts + safe layout builder,
  partials, variables, logs; delivery-log failure/bounce polish.
- **Access & Abilities (AAM-lite)** — CoreX-native capability groups + role matrix for `corex_*` + audit
  log + access-denied; **not** a full Advanced Access Manager clone.
- **Data Models / CRUD** — models, records + drawer, import wizard, export, migrations; field/schema
  editor, XLSX export outstanding; safe model-manager scope only.

## 5. Needs-another-pass items (direction exists; states/polish/truthfulness incomplete)

Build the truthful states; do not present them as finished until states land:

- **Overview / Dashboard** — honest empty/loading/error/permission states; **no fake data anywhere**.
- **Data explorer** — read-only, locked-by-code, production-warning states. Data = system explorer,
  distinct from the Forms/Submissions workspace.
- **Import / export / migration** — dry-run, mapping, validation report, rollback messaging (CSV-first;
  XLSX future).
- **Flow Pages / Landing Patterns** — closed, expired, already-submitted, staging-warning states.
- **Insights** — connected/disconnected/not-configured everywhere; **zero fake scores/metrics**.
- **Setup Wizard + Launch Checklist** — blocked-by-dependency, apply-failure, existing-content;
  production-ready vs production-blocked gating; preview-then-apply UX.
- **Company Site Kit** — secondary pages, demo-content levels, conflict handling.
- **Blog / News** — author/date archives, search results (native WP posts; no custom blog engine).
- **State screens** — canonical reusable state pack (404, maintenance, empty/error, read-only,
  forms-paused, closed/expired flow).
- **Blocks** — rich tabs, slider primitive, map, before/after.
- **Header / nav / footer** — footer variants, product/resources mega, full RTL audit.
- **Mobile / Accessibility / Performance** — cross-cutting; verify per screen (table→card,
  focus/labels/error-summaries, lazy media, no heavy admin animation).

## 6. Future-only / deferred items (reference/roadmap only — never a live capability this pass)

- **Blog Pro / Editorial Workflow** (P5) — future add-on on native posts; keep reference-only.
- **Portfolio Kit** (P5) — reference; reuses Site Kit + components.
- **WooCommerce Kit** (P6) — gated, not active core; dual-gated behind Woo availability + CoreX add-on.
- **Docs / Marketing productization** (P5) — reference pages, palette, release cards.
- **Pro / Commercial** (P6) — reference badges + license states only; **no active purchase/license/upgrade flow**.
- **Auth / Account Kit** (P6) — presentation only; no real auth/portal/membership.
- **Advanced Access Manager** (P6) — policy builder, client portal, white-label roles; Pro candidate, not core.

## 7. Hide-until-implemented items

No dead entry points may appear in admin UI. Any area not built in a given batch must be **hidden or
honestly gated** (disabled with a truthful reason), never shown as a working link. Specifically:
Operations Mode, Security Center, Access & Abilities, Data Models CRUD, Forms & Flows, Submissions
Inbox, Email Studio, Insights widgets, and Setup Wizard steps must each be gated to their real state
until their batch lands.

## 8. Legacy / reference-only prototype warnings

- Older prototype files (`Corex Admin Dashboard`, `Corex Admin - Add-ons & Data`, `Corex Email
  Templates`, `Corex Forms System`, `Corex Options Round 2`, etc.) are **reference only**. Where they
  conflict with the closure/gap-closure package, the closure package wins.
- **Freeze-language corrections** (apply across repo copy/labels):
  - "Fully frozen — design complete" → "Frozen: brand, core visual system, and approved admin
    foundation. Other areas carry their real state."
  - "Commercial-ready / Pro-ready / Marketplace-ready / ThemeForest-ready / License flow ready" →
    "future/reference only — not part of CoreX Core now."
  - "Everything should be built now" → "Not for implementation now: full builders, full marketplace,
    full Pro purchase flow, advanced Woo internals, full client portal."
  - Any "Blade/template layer" (Laravel) wording → "WordPress / FSE / block-first — templates,
    template parts, patterns, blocks."
- **Placeholder discipline:** neutral placeholders only (e.g. "Acme Company"). No real client/company
  name becomes framework content. No stale version numbers in design-derived copy.

## 9. Exact engineering scope for this implementation pass (Spec 063)

Spec 063 keeps **one parent goal** — *finish the implementation-ready CoreX design gaps from the new
package and apply the approved design language consistently* — split into safe, independently
shippable batches that mirror the prompt's Phase 0–8. Each batch is spec-first, guard-gated, tested,
i18n/RTL/a11y-verified, and documented before it ships. The batches:

- **Phase 0 — Intake, truthfulness, gates.** This document; ROADMAP/PROGRESS/DECISIONS updates;
  neutralize marketplace/Pro/ThemeForest/license wording; mark future-only; truthful-state acceptance
  criteria. *(No fake data / fake integrations anywhere is a hard acceptance gate for every later phase.)*
- **Phase 1 — Admin Overview + global state language.** Truthful Overview (env/mode badge, ops-mode
  status, launch readiness, security/add-on/forms/submissions/email/captcha/media/data/insights
  summaries, wizard progress, docs links, honest empty activity) across the required states.
- **Phase 2 — Forms & Flows + Submissions Inbox + Email Studio** (M7-ready; first, per roadmap).
- **Phase 3 — Data Models, CRUD, import/export, migrations** (safe model manager; not a DB admin tool).
- **Phase 4 — Operations Mode + Security Center + Access & Abilities** (truthful, reversible, lockout-safe).
- **Phase 5 — Settings, media, retention, advanced** (provider-specific captcha; secrets write-only).
- **Phase 6 — Insights + Setup Wizard** (only real checks; no overclaim).
- **Phase 7 — Blog + social sharing + Company Site Kit gaps + core blocks** (free, company-site-safe only).
- **Phase 8 — Docs, docs-app, screenshots, verification, PR, handoff.**

**Hard boundaries (from the package + constitution):** no fake data/charts/records/integrations/Pro/
marketplace/licensing; no full page/form/nav builder; no custom blog engine; no full AAM clone; no full
auth/client portal; WordPress/FSE/block-first; optional add-ons self-disabling; WooCommerce dual-gated;
tokens/`theme.json`/CSS variables/logical properties only; every dangerous mutation nonce+capability
gated with confirmation; every new secret write-only. Framework Mode — no edits to `sites/<client>/`.

## 10. Owner decisions still open (do not block Phase 0–1)

Recorded so the phases that need them stop for owner sign-off rather than inventing scope:

- Approve the Operations Mode 8-mode model and which behaviors actually change (Phase 4).
- Approve Security Center scope beyond "hide wp-admin" + the reversible recovery contract (Phase 4).
- Approve the Access & Abilities CoreX-native scope (not full AAM) (Phase 4).
- Approve the Forms-vs-Flow model + extension points; retention/anonymize behavior (Phases 2).
- Approve "Email Templates → Email Studio" upgrade and the safe layout-builder boundary (Phase 2).
- Approve the safe Data-model-manager scope and CSV-first / XLSX-future import-export (Phase 3).
- Confirm Company Site Kit page coverage + adopt/skip/reset UX (Phase 7).
