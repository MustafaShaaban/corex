# Data Model: Block library expansion (027)

Each block is a `block.json` (attributes + renderer FQCN) + a pure `BlockRenderer` (attributes → escaped HTML).
Renderers take `render(array $attributes, string $content, object $block): string`.

## corex/stat — StatRenderer

| Attribute | Type | Default | Notes |
|---|---|---|---|
| `value` | string | `''` | the figure (e.g. "98%") — rendered prominently |
| `label` | string | `''` | what it measures |
| `description` | string | `''` | optional supporting line (omitted when empty) |

Render: `<div class="corex-stat"><span class="corex-stat__value">…</span><span class="corex-stat__label">…</span>
[<p class="corex-stat__desc">…</p>]</div>`. Empty `value` **and** `label` → renders nothing.

## corex/testimonial — TestimonialRenderer

| Attribute | Type | Default | Notes |
|---|---|---|---|
| `quote` | string | `''` | the testimonial text |
| `author` | string | `''` | attribution |
| `role` | string | `''` | optional role/company |

Render: `<figure class="corex-testimonial"><blockquote>…quote…</blockquote><figcaption>— author[, role]</figcaption></figure>`.
Empty `quote` → renders nothing.

## corex/pricing — PricingRenderer

| Attribute | Type | Default | Notes |
|---|---|---|---|
| `plan` | string | `''` | plan name (heading) |
| `price` | string | `''` | the price (e.g. "$29") |
| `period` | string | `''` | optional billing period (e.g. "/mo") |
| `features` | string | `''` | newline-delimited; each non-empty line → an `<li>` |
| `ctaText` | string | `''` | optional CTA label |
| `ctaUrl` | string | `''` | optional CTA href (`esc_url`) |

Render: a `<div class="corex-pricing">` card with `<h3>` plan, price + period, a `<ul class="corex-pricing__features">`,
and (when both ctaText + ctaUrl set) a CTA `<a class="corex-pricing__cta">`.

## corex/accordion — AccordionRenderer

| Attribute | Type | Default | Notes |
|---|---|---|---|
| `items` | string | `''` | one `Title \| Content` per line; lines without a `\|` use the whole line as the title |

Render: `<div class="corex-accordion">` containing one native `<details class="corex-accordion__item">
<summary>Title</summary><div class="corex-accordion__content">Content</div></details>` per line — accessible,
keyboard-operable, **no JS**. Empty `items` → renders nothing.

## Shared rules

- All text escaped with `esc_html`; the CTA href with `esc_url`.
- Token-only classes; styling lives in each `style.scss` using theme.json CSS variables + logical properties.
- Each renderer degrades gracefully (sane defaults, never a notice/fatal).
