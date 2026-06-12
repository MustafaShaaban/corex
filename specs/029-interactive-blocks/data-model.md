# Data Model: Interactive, inline-editable blocks (029)

## Block attributes (after this spec)

### corex/stat
| Attr | Type | Editing | Render |
|---|---|---|---|
| `value` | string (rich) | inline RichText | `wp_kses_post` |
| `label` | string (rich) | inline RichText | `wp_kses_post` |
| `description` | string (rich) | inline RichText | `wp_kses_post` |

### corex/testimonial
| `quote` | string (rich) | inline RichText | `wp_kses_post` |
| `author` | string (rich) | inline RichText | `wp_kses_post` |
| `role` | string (rich) | inline RichText | `wp_kses_post` |

### corex/pricing
| `plan` | string (rich) | inline RichText | `wp_kses_post` |
| `price` | string (rich) | inline RichText | `wp_kses_post` |
| `period` | string | inline RichText | `wp_kses_post` |
| `features` | array<string(rich)> | repeatable RichText rows | per-item `wp_kses_post` (`<li>`) |
| `ctaText` | string | inline RichText | `wp_kses_post` |
| `ctaUrl` | string (url) | InspectorControls (URL) | `esc_url` |

### corex/accordion
| `items` | array<{title:string(rich), content:string(rich)}> | repeatable RichText rows | per-item `wp_kses_post` |
| _(legacy)_ `items` as delimited string | string | — | parsed by the **fallback** so old blocks still render |

### corex/form
| `formSlug` | string | **SelectControl** (list of forms) — unchanged attribute name | server render (unchanged) |

## Form option (the selector's items)

```json
{ "slug": "contact", "label": "Contact" }
```

Produced by `FormRegistry` → exposed by `GET corex/v1/forms` (cap-gated, read-only).

## Rendering rules

- **Rich** fields (text a builder types, possibly with bold/italic/link) → `wp_kses_post`.
- **Plain** fields (a URL, a numeric/structural value) → `esc_url` / `esc_attr` / `esc_html`.
- Empty rich field → omit the element (graceful default), never output an empty tag that breaks layout.
- Token-only classes + logical CSS preserved (no style change in this spec).
