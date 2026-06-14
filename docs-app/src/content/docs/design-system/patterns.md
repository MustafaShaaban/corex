---
title: Patterns
description: Corex section patterns — composed, ready-to-edit page sections built only from real registered blocks.
---

Patterns are **compositions** of real blocks — ready-made sections an editor inserts and fills in. They live in
corex-ui's `PatternLibrary`, register under the **Corex** pattern category, are token-only and RTL-correct, and a
pattern-accuracy test fails on drift (a pattern can only compose blocks that actually exist).

| Pattern | Composes | Use it for |
|---|---|---|
| **Hero** (`corex/hero`) | group + heading (h1) + paragraph + button | the top of a landing/home page |
| **Features** (`corex/features`) | columns of heading + paragraph | a 3-up services/feature list |
| **Section header** (`corex/section-header`) | heading + supporting paragraph | introducing any section |
| **Content split** (`corex/content-split`) | `core/media-text` + heading + paragraph | narrative beside an image |
| **Stats** (`corex/stats`) | columns of `corex/stat` | headline metrics |
| **Testimonial** (`corex/testimonial`) | `core/quote` | a single client quote |
| **FAQ** (`corex/faq`) | `corex/accordion` | frequently asked questions |
| **Latest news** (`corex/news`) | `corex/posts` | a recent-posts teaser |
| **CTA** (`corex/cta`) | accent section + heading + button | a closing call to action |
| **Contact** (`corex/contact`) | heading + `corex/form` | a contact section with the form |

**When to use a pattern vs a block:** reach for a pattern when you want a whole *section* laid out; reach for a
block (or block style) when you want a single *element*. Patterns are starting points — edit the text, swap the
image, then make it yours.
