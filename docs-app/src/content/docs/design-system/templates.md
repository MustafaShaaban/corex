---
title: Templates
description: The Corex FSE page-type templates — the defaults plus the landing, contact, and form page types.
---

Templates are **FSE page-type layouts** in the theme. They assemble template parts (header/footer) and patterns —
presentation only, no business logic (constitution Principle I). Assign a custom template from the page editor's
**Template** panel.

## Default templates

| Template | Page type |
|---|---|
| `front-page` | the homepage |
| `page` | a standard inner page (title + content) |
| `single` | a single post / detail page |
| `archive` / `index` | a listing |
| `search` | search results |
| `404` | not found |

The Portfolio kit adds `archive-project` and `single-project`.

## Custom page templates (spec 054)

Assignable to any page (registered in `theme.json` `customTemplates`):

| Template | Composes | Use it for |
|---|---|---|
| **Landing page** (`page-landing`) | hero → features → stats → testimonial → CTA patterns | a marketing landing page |
| **Contact page** (`page-contact`) | section-header + contact (form) patterns | a contact page |
| **Form page** (`page-form`) | title + content + the `corex/form` block | a page built around a form |

**When to use which:** the default `page` for ordinary content; **Landing** for a conversion-focused page;
**Contact**/**Form** when the page's purpose is a form. Build new page types as templates (compositions), not as
blocks.
