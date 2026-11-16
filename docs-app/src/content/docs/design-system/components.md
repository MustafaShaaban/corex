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
The primary button is the theme.json default: brass with a graphite label, a 10px corner, and a quiet surface
when it is disabled. The **Secondary** (`is-style-corex-secondary`, an outline in the text colour around it) and
**Ghost** (`is-style-corex-ghost`, text in the darker brass) block styles add the variants. **Use** the primary for
the main action, one per view. **Don't use** more than one primary action in a section.

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

### Bands: block styles `is-style-corex-band-quiet`, `-dark` and `-brass` (on `core/group`)
The visitor kit's grounds for a section (spec 134). The **Quiet band** is the quiet grey ground. The **Dark band**
is dark in both the default style and Dark, and hands everything inside it the band's own colours, so a button, a
link, a card or a field in it is drawn by its ordinary rules. The **Brass band** is brass with graphite text and a
graphite button. **Use** the dark band for a hero and the brass band for one call to action a page at most.
**Don't** give a heading or a button inside a band a colour of its own: the band draws them. The bands are offered
under a theme that defines the `band` custom colour (the CoreX theme, whatever palette a `brand.json` gives it), or
whose palette has every one of `primary`, `surface`, `surface-alt`, `accent`, `accent-dark` and `inverse`. Under a
theme with a palette of its own the hero and call-to-action patterns take that theme's look (DECISIONS #642).

### Empty state — block style `is-style-corex-empty` (on `core/group`)
A muted, centred placeholder. **Use** when a list/area has no content yet.

### Table — `core/table` (+ `is-style-corex-striped`)
**Use** core/table; apply the striped style for scannability. **Don't use** a table for layout.

### Skeleton / loading — `.corex-skeleton` (CSS utility)
A token-only pulsing placeholder for the public site. **Use** while content loads. **Don't use**
indefinitely — show an error or empty state on failure.

The admin has its own, on the admin's tokens and under another name (`.corex-admin-skeleton`), so that neither
stylesheet can style the other's: see [Loading states](../admin-experience/#loading-states). The Submissions
screen still shows the `@wordpress/components` Spinner until it is moved to it.

## Deferred (not yet built)

Drawer, popover, JS tooltip, stepper, and a forms validation-summary are **deferred** with reasons — see the
[gap analysis](./gap-analysis).
