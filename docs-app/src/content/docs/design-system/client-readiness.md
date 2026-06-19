---
title: Client Readiness Matrix
description: Native-first component coverage for the first Corex company-identity websites.
---

The client-readiness matrix maps minimum company-site needs to existing Corex or WordPress-native mechanisms. It is
not the final Corex visual redesign. Its purpose is to show what can ship now with FSE templates, patterns, core
blocks, Corex blocks, forms, and token-driven utilities.

## Coverage Rules

- Prefer WordPress core blocks, block styles, and patterns before adding custom block scope.
- Use Corex blocks where a reusable server-rendered component already exists.
- Keep all styling token-only through `theme.json` CSS variables and runtime `brand.json` overrides.
- Keep layout RTL-first through logical CSS and WordPress direction-aware controls.
- Treat accessibility as part of readiness: keyboard behavior, labels, focus states, semantic headings, contrast, and
  WCAG 2.2 AA checks.
- Mark optional vertical needs as deferred unless the first client sites require them.

## Matrix

| Need | Mechanism | Source | Readiness |
|---|---|---|---|
| Home | Pattern | `corex-ui` landing/home sections | Free/Core, token-only, native FSE composition |
| About | Pattern | `section-header` and `content-split` patterns | Free/Core, core blocks plus Corex pattern structure |
| Services | Pattern | Group/columns, cards, stats patterns | Free/Core, no new block required |
| Contact | Form field | `corex-forms` schema and `corex/form` block | Free/Core, labels/errors/keyboard submission |
| Careers | Deferred | `corex-careers` add-on | Optional until a client needs jobs |
| Portfolio | Deferred | `corex-kit-portfolio` add-on | Optional until a client needs projects |
| Forms | Form field | `corex-forms` validator and dynamic form block | Free/Core trust baseline |
| Listings | Corex block | `corex/posts` and query/listing renderers | Free/Core reusable listing surface |
| Cards | Corex block | pricing, stat, posts, and card styles | Free/Core reusable content cards |
| Testimonials | Corex block | `corex/testimonial` and testimonial pattern | Free/Core quote semantics |
| CTAs | Corex block | `corex/cta` and CTA patterns | Free/Core link/button semantics |
| Media | WordPress core block style | image, gallery, media-text, cover | Free/Core, prefer native media blocks |
| Navigation | WordPress core block style | navigation block and theme parts | Free/Core, keyboard and current-page state |
| Page templates | Pattern | FSE templates plus section patterns | Free/Core, composed in the Site Editor |
| Admin component | Admin component | config forms and DataViews-ready admin surfaces | Free/Core internal setup surface |
| Token utility | Utility | `theme.json`, `brand.json`, logical CSS | Free/Core branding foundation |

## Existing DLS Links

- [Components](./components/) cover the reusable Corex block layer.
- [Patterns](./patterns/) cover section composition for company pages.
- [Templates](./templates/) cover the FSE page surfaces.
- [Gap analysis](./gap-analysis/) records the native-first decisions behind the DLS.

## Readiness Command

`wp corex readiness 0.26.1` reports `component-coverage` from the same default matrix used by the release tests. A
passing result means required company-site needs are classified, no unknown mechanism is present, native mechanisms
are preferred before new custom block scope, and no final visual redesign has entered the readiness work.

