---
title: DLS gap analysis
description: What exists, what is a WordPress core block to document, what is a Corex block style, what is a new Corex block, and what is deferred — the evidence behind the design system.
---

The Corex Design Language System is built **native-first**: a custom block exists only where Corex needs reusable
server-rendered data, accessibility behavior, or a repeated agency pattern that WordPress core cannot express.
Everything else is a documented core block, a Corex **block style**, a design token, or the runtime. This page is
the evidence — every candidate UI element with its single decision (spec 054, US1).

## Decision vocabulary

- **exists-good** — already a `corex/*` block, good as is.
- **document-core** — WordPress core already provides it; document the usage (don't rebuild).
- **block-style** — appearance only on a core/Corex block via `register_block_style()`; no new block.
- **new-block** — a justified custom `corex/*` block.
- **runtime** — provided by the `window.Corex` runtime (spec 043), not a placed block.
- **deferred** — out of the current scope, with a reason.

## Foundations

radius and grid/layout tokens already exist in `theme.json`; the genuinely missing groups — **motion**, **focus**,
and **z-index** — were added in 054. All groups are documented under [Foundations](./foundations).

## Components

| Candidate | Decision | Delivered as |
|---|---|---|
| Alert, Badge, Breadcrumbs, Copyright | exists-good | `corex/*` blocks |
| Accordion, Tabs | exists-good | `corex/accordion` (native `<details>`), `corex/tabs` (CSS-only) |
| Card, Section, Empty state | block-style | `is-style-corex-card` / `-section` / `-empty` on `core/group` |
| Skeleton / loading | block-style | token-only `.corex-skeleton`; Spinner = `@wordpress/components` (documented) |
| Striped table | block-style | `is-style-corex-striped` on `core/table` |
| Button (secondary / ghost) | block-style | styles on `core/button` (primary is theme.json default) |
| Button, Link, Search, Dropdown/menu, Pagination, Table, List, Image | document-core | core blocks (`core/button`, `core/search`, `core/navigation`, `core/query-pagination`, `core/table`, …) |
| Form controls, Upload | exists-good | corex-forms `FieldRenderer` (documented, referenced) |
| Toast / notification | runtime | `window.Corex.notices` (spec 043) |
| **Modal / dialog** | **new-block** | **`corex/modal`** — native `<dialog>`, focus-trap + ESC, degrades without JS |
| Drawer, Popover, Tooltip (JS), Stepper, Validation summary | deferred | named follow-ups (see below) |

**Net new build:** one block (`corex/modal`) + a handful of block styles + a skeleton utility. The rest is
documentation of what WordPress already gives you — by design.

## Patterns & templates

- **Patterns** (compositions of real blocks): existing — hero, features, CTA, testimonial, contact; added —
  section-header, content-split, stats, FAQ, posts/news.
- **Templates** (FSE page types): existing — front-page (homepage), page (inner), single (detail), archive
  (listing), search, 404; added — landing, contact, form.

## Deferred (with reasons)

- **Drawer** — builds on the modal pattern; lower priority.
- **Popover** — core's Interactivity popover is complex and not agency-critical yet.
- **Custom dropdown/select** — the native `<select>` (forms) covers it; a styled custom control is a forms concern.
- **Stepper** — niche; revisit with a multi-step form/checkout need.
- **Validation summary** — corex-forms already shows per-field errors; a summary region is a corex-forms polish.
