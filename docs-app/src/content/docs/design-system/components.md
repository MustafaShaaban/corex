---
title: Components
description: The Corex UI atoms — what each is, how it's delivered, its attributes, and when to use (and not use) it.
---

Components are the UI atoms. Each is delivered by the lightest correct mechanism (a `corex/*` block, a block
style on a core block, a documented core block, or the runtime) — see the [gap analysis](./gap-analysis) for the
full decision table. All are token-only, accessible, i18n-ready, and RTL-correct.

## Feedback

### Alert — `corex/alert`
An accessible status message with an info/success/warning/error variant (`role="alert"`).
**Attributes:** `message`, `variant`. **Use** for an inline, contextual message. **Don't use** for a transient
notification — use the runtime toast (below).

### Badge — `corex/badge`
A small labelled status token. **Use** to tag status/category inline. **Don't use** as a button.

### Toast / notification — runtime
Transient notifications come from the `window.Corex.notices` runtime (spec 043), not a placed block. **Use** for
async feedback after a form submit or AJAX action.

### Modal / dialog — `corex/modal`
A trigger button + a native `<dialog>` (focus-trap, ESC, and `::backdrop` for free), labelled by its heading,
with a close button. **Attributes:** `title`, `triggerLabel`, `content`. Degrades without JS (the content stays
in the DOM). **Use** for a focused, interruptive task or confirmation. **Don't use** for content that should be
on the page, or for non-essential interruptions.

## Navigation

### Breadcrumbs — `corex/breadcrumbs`
An accessible trail to the current page. **Use** on deep pages. **Don't use** on the homepage.

### Tabs — `corex/tabs`
CSS-only accessible tabbed content. **Use** to group peer content. **Don't use** to hide essential content from
search/no-JS readers.

### Accordion — `corex/accordion`
Native `<details>` disclosure, accessible, no JS. **Use** for FAQs / progressive disclosure. **Don't use** when
all content should be visible at once.

### Pagination / Dropdown menu — core blocks
Use `core/query-pagination` for content paging and `core/navigation` submenus for menus — documented, not
rebuilt.

## Actions & forms

### Button — `core/button` (+ Corex block styles)
The primary button is the theme.json default; the **Secondary** (`is-style-corex-secondary`) and **Ghost**
(`is-style-corex-ghost`) block styles add the variants. **Use** the primary for the main action, one per view.
**Don't use** more than one primary action in a section.

### Link — `core/` rich-text link
Styled via `theme.json` `elements.link`. **Use** for navigation within prose.

### Form controls — corex-forms `FieldRenderer`
Text/email/number/tel/url/password/date/file/textarea/select/radio/checkbox-group/checkbox/toggle, accessible by
construction. See the [forms guide](../guides/forms). **Use** the form block; don't hand-roll inputs.

## Layout & content

### Card — block style `is-style-corex-card` (on `core/group`)
A surface panel (border, radius, shadow tokens). **Use** to group related content. **Don't use** as the only way
to convey grouping for assistive tech — keep a heading.

### Section — block style `is-style-corex-section` (on `core/group`)
Vertical rhythm + container. **Use** to wrap a page section.

### Empty state — block style `is-style-corex-empty` (on `core/group`)
A muted, centred placeholder. **Use** when a list/area has no content yet.

### Table — `core/table` (+ `is-style-corex-striped`)
**Use** core/table; apply the striped style for scannability. **Don't use** a table for layout.

### Skeleton / loading — `.corex-skeleton` (CSS utility) / Spinner
A token-only shimmer placeholder; for React admin screens the `@wordpress/components` Spinner is used. **Use**
while content loads. **Don't use** indefinitely — show an error or empty state on failure.

## Deferred (not yet built)

Drawer, popover, JS tooltip, stepper, and a forms validation-summary are **deferred** with reasons — see the
[gap analysis](./gap-analysis).
