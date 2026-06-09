# Corex Roadmap

The agreed module sequence and packaging beyond the released foundation. This is the durable
"what's next" picture; `PROGRESS.md` holds the immediate next step, `DECISIONS.md` the rationale.
Order and scope are recommendations — adjust per project need.

## Packaging principle

- **`plugins/`** — the free **core**: `corex-core` (engine, container, config, data layer, events,
  security), `corex-blocks` (block engine), `corex-config` (settings/admin). Always present.
- **`addons/`** — optional **features** (`corex-email`, plus the feature add-ons below). This is the
  commercial layer (free core + paid add-ons) and the marketplace surface.
- **`theme/`** — the neutral skin (design tokens + universal FSE templates).

A **feature** is an add-on; a **foundation/seam** lives in core. "Everything is blocks": designs are
composed of `corex/*` blocks, not hand-written markup.

## Released

- **v0.6.0** — foundation (specs 001–006: core, data layer, CLI generators, block engine,
  middleware/security, theme tokens).
- **v0.7.0** — Forms engine (spec 007, `corex-forms`).
- **v0.8.0 / v0.8.1** — Corex Mail MVP (spec 008, `corex-email`) + a cross-platform autoload hotfix.

## Planned (recommended order)

| Spec | Module | Package | Notes |
|---|---|---|---|
| **009** | **Corex UI Block Library** | addon `corex-ui` | Section + dynamic blocks under `corex/*` (hero, section, feature-grid, cta, team, testimonials, stats, logos, faq, pricing, steps, posts, breadcrumbs). Token-only, RTL, WCAG, i18n. Designed via `ui-ux-pro-max`. The foundation every kit/design composes. |
| **010** | **Company Website Kit** | addon `corex-kit-company` + theme templates | Patterns (compositions of 009 blocks) + universal FSE templates (front-page, page, single, archive, search, 404, index, header/footer parts) + page compositions (Home/About/Services/Team/Contact/Blog) + a neutral style variation + a Blueprint manifest. Neutral/un-branded; composes modules, no business logic. |
| **011** | **Custom Tables + TableRepository** | core (corex-core data) | Migrations/schema builder + `TableRepository` + casts. Data foundation for subscribers, applications, bookings (many queryable rows, not post-shaped). |
| **012** | **Captcha drivers + Secure uploads** | addon `corex-captcha` + core upload util | Captcha behind one interface (honeypot/reCAPTCHA/Turnstile/hCaptcha, optional Akismet) + path-safe, MIME/size-validated file uploads. Both anti-spam/security enablers for newsletter + careers. |
| **013** | **Newsletter / Subscriptions** | addon `corex-newsletter` | Double opt-in, `newsletter_topic` taxonomy, secure confirm/unsubscribe tokens, suppression list, GDPR consent, on-publish trigger (post in topic → email confirmed subscribers via the mail queue). Subscribers in a custom table. |
| **014** | **Careers** | addon `corex-careers` | Job entity + taxonomies (department/location/type), `corex/jobs` block + single-job template, application form with secure CV upload, application pipeline (new→hired), notifications. Needs 011/012. |
| **015** | **Call Request** | addon `corex-bookings` | Request-a-call flow (pick a leader, preferred time, contact) → store → notify leader + confirm visitor. Needs forms + mail; calendar integration later. |
| **016** | **Corex Brand Identity + Admin Branding** | `corex-config` | Define Corex's identity (navy `#0B1F3B` + electric cyan `#00C2FF`, geometric sans, a layered-core SVG mark) and a configurable logo that replaces the WP logo on the admin bar, login, and admin footer. Small + independent — can move earlier. |
| **017** | **Corex Admin Dashboard / Settings** | `corex-config` (React/DataViews) | Top-level "Corex" menu: dashboard (status/health/modules) + settings (Theme & Brand, Modules, Mail, Forms, Subscribers, Integrations, Tools). Modern WP admin (`@wordpress/components`/DataViews) — first JS build step. |

## Cross-cutting (added inside the specs that need them)

- **Mail queue** (Action Scheduler) → with Newsletter (013): bulk sends must not block a publish.
- **Mail attachments** → with Careers (014): secure CV attachments reuse the upload util.
- **Marketplace / distribution** (free core + paid add-ons) → a parallel business track, not a code spec.

## First real consumer — Blackstone EIT

Will use Corex with a **Figma design + full sitemap** from the client's design team (arriving later).
The kit (010) stays **neutral**; Blackstone's identity is applied via **`brand.json` + a style variation**
matching the Figma — zero markup edits. Blackstone needs: 009, 010, 013 (newsletter w/ topics), 014
(careers), 015 (call request).

## v1.0.0 definition

"Usable for a real client website end-to-end" = **009 + 010 + 013** (+ 014/015 as the project needs)
+ **016/017** (Corex identity + control panel).
