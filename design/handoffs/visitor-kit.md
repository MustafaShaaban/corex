# Visitor Kit Handoff

**Status:** Approved

**Approved:** 2026-11-15, by the owner, in session, at Version 8 of the CoreX design system. Version 9 is the same
content with the approval written into its first page.

**Design source:** the CoreX design system in Claude Design, private to the owner:
https://claude.ai/artifact/DExY8maEuxwM6JwdZ1jw5c

**Engineering target:** `specs/134-theme-graphite-brass/spec.md` builds the foundations (tokens, base elements,
section styles, the logo set, the theme preview). The components, blocks and templates are built by later specs,
one per release, each pointing at this file.

## Approval evidence

On 2026-11-15 the owner rejected a design that only recoloured the theme's existing layout and asked for "a real
components thing" in the manner of a public design system: components, patterns and blocks, enough "to create a
full website in a consistent identity and nothing is missing". The kit was then made in four layers, each put to
him in turn. He answered "Yes, start", "Yes, do blocks", "Yes, do templates", and then "Approve the kit" on being
asked whether he approves it as the look CoreX gets built on.

The approved design is the functional contract (DECISIONS #115): nothing in it ships as a sample or a placeholder.
This file records the approval. It is an input to engineering specs, not permission to write code without one.

## Approved scope

The public side of a CoreX site, in four layers. Each item has a preview and written guidelines in the design
system: what it is for, its rules, and how it ships in WordPress.

1. **Foundations.** Colour (light and dark), type (Latin and Arabic), spacing, corners, shadows, motion, focus.
2. **Components, 30.** Button, Hyperlink; TextInput, Textarea, SelectField, Checkbox, Radio, Toggle, FileInput,
   RangeSlider; Card, Badge, Avatar, Blockquote, Accordion, Tabs, DataTable, Carousel; Alert, Banner, Toast,
   Tooltip, Popover, Modal, Progress; Navigation, Breadcrumbs, Pagination, Steps, Dropdown.
3. **Blocks, 28** (page sections built only from components). SiteHeader, MegaMenu, Hero, PageHeader, SiteFooter;
   SectionHeader, FeaturesGrid, ContentSplit, StatsBand, LogoCloud, ProcessSteps, Testimonials, Pricing, FaqGroup,
   TeamGrid, Timeline, Gallery; CallToAction, Newsletter, ContactSection, Location, VideoFeature, BlogList;
   FilterBar, PageRating, SignIn, CookieNotice, NotFound.
4. **Templates, 13** (whole pages built only from blocks). HomePage, HomePageArabic, HomePagePhone, AboutPage,
   ServicesPage, ServiceDetailPage, ContactPage, BlogPage, BlogPostPage, SearchPage, NotFoundPage, SignInPage,
   LandingPage.

## Explicit exclusions

- **The admin.** The admin tokens and the nine admin components in the design system are a record of the code as
  it was on 2026-11-15. They are redesigned with the admin (spec 133) and are not approved here.
- **The logo files.** The design system's Logos group still holds the files of 2026-11-15. The corrected lockup,
  the fixed-colour and stacked lockups, the knockout and the favicon set are specified in spec 134 and were shown
  to the owner on the earlier R2.2 canvas, which he did not approve as a whole. They need their own approval before
  slice 2 of spec 134 is built.
- **Form patterns.** Ready-made field groups (name, phone number, address, date) are not drawn yet.
- **WooCommerce blocks** and **emails**.
- **Behaviour.** The previews are static renditions with no script. Keyboard order, focus movement, opening and
  closing are written in each item's guidelines and are proven when the item is built.

## Look, in one paragraph

A white page, graphite text and headings, and brass as the one accent. The main button is brass with a graphite
label. Sections alternate four grounds: the page, a quiet grey band, a dark band and, once a page at most, a brass
band. Corners are soft (10px on controls, 16px on cards), edges are hairlines, shadows are small. Headings are
Space Grotesk at 600; text is the system face; Arabic is IBM Plex Sans Arabic for both.

## Design tokens

Every value is a token of the design system's `project/tokens.json`, under the name the code uses.

**Palette slugs** (`settings.color.palette`; light is `theme/theme.json`, dark is `theme/styles/dark.json`). Every
slug keeps its name.

| Slug | Light | Dark |
|---|---|---|
| `primary` | `#14151a` | `#f4f5f7` |
| `primary-dark` | `#0d0e11` | `#c2c8d1` |
| `accent` | `#ad8643` | `#c9a25e` |
| `accent-dark` | `#76521d` | `#e2c188` |
| `surface` | `#ffffff` | `#16181d` |
| `surface-alt` | `#f3f4f6` | `#1a1d23` |
| `surface-raised` | `#ffffff` | `#1c1f26` |
| `surface-strong` | `#e2e5ea` | `#262a32` |
| `border` | `#8b9099` | `#646b78` |
| `ink` | `#14151a` | `#f4f5f7` |
| `ink-soft` | `#5b616d` | `#9aa0ab` |
| `success` | `#287a4e` | `#5fb787` |
| `warning` | `#8a5b13` | `#e0a33a` |
| `error` | `#b23530` | `#e0645c` |
| `info` | `#176b96` | `#6e9be6` |
| `inverse` | `#ffffff` | `#14151a` |
| `overlay` | `rgba(15, 17, 22, 0.54)` | `rgba(7, 9, 12, 0.72)` |
| `selection` | `#ad8643` | `#c9a25e` |
| `selection-text` | `#14151a` | `#14151a` |

The compatibility slugs follow their role, as today.

**Custom colour tokens** (`settings.custom.color.*`, written `--wp--custom--color--<name>`). New with this kit.

| Token | Light | Dark | For |
|---|---|---|---|
| `on-accent` | `#14151a` | `#14151a` | text on brass |
| `accent-hover` | `#c9a25e` | `#e6cf9f` | the brass button on hover |
| `accent-subtle` | `rgba(173, 134, 67, 0.12)` | `rgba(201, 162, 94, 0.14)` | brass tint |
| `success-soft` | `rgba(47, 143, 91, 0.12)` | `rgba(95, 183, 135, 0.13)` | ground of a success alert or badge |
| `warning-soft` | `rgba(181, 120, 31, 0.12)` | `rgba(224, 163, 58, 0.13)` | ground of a warning alert or badge |
| `error-soft` | `rgba(194, 67, 59, 0.12)` | `rgba(224, 100, 92, 0.13)` | ground of an error alert or badge |
| `info-soft` | `rgba(23, 107, 150, 0.12)` | `rgba(110, 155, 230, 0.13)` | ground of an information alert or badge |
| `band` | `#14151a` | `#0d0e11` | ground of the dark band |
| `band-raised` | `#1c1f26` | `#16181d` | a card or field on the dark band |
| `band-ink` | `#f4f5f7` | `#f4f5f7` | text on the dark band |
| `band-ink-soft` | `#9aa0ab` | `#9aa0ab` | secondary text on the dark band |
| `band-line` | `#262a32` | `#262a32` | hairlines on the dark band |
| `band-border` | `#646b78` | `#646b78` | control edges on the dark band |
| `band-accent` | `#c9a25e` | `#c9a25e` | brass on the dark band |
| `band-accent-hover` | `#e6cf9f` | `#e6cf9f` | the brass button on the dark band, on hover |
| `band-accent-subtle` | `rgba(201, 162, 94, 0.14)` | `rgba(201, 162, 94, 0.14)` | brass tint on the dark band |
| `band-link` | `#e2c188` | `#e2c188` | links and brass text on the dark band |

**Shadows**: the brand contract's three, light and dark. **Spacing, corners, motion, sizes**: as `theme/theme.json`
holds them since R2.1 (4 to 96px; 3, 6, 10, 16px and full; 80, 140, 200 and 320ms). No new scale.

## Reusable primitives

- **Section** (`corex-section`): full width, 96px padding top and bottom on a desktop, 64px on a phone, with a
  container of 1200px (or 768px for text and forms).
- **Four grounds.** The page, the quiet band (`surface-alt`), the dark band and the brass band.
- **The dark band** is dark in both themes. It re-points the palette tokens to the `band-*` tokens for everything
  inside it, so a component is written once and works on any ground. It replaces filling a section with `primary`,
  which is light in the Dark style.
- **The brass band** re-points text to `on-accent` and uses a graphite button. One per page at most.
- **Type steps**: heading 1 to 4 classes on the theme's fluid sizes, a lead paragraph, an eyebrow label.
- **Grids** of two, three and four that fold to one column by themselves.
- **Icons**: one set, 24 by 24, 1.75px stroke, painted in the text colour; forward arrows flip in Arabic.

## Interaction and state behaviour

Each component's guidelines list its states. The ones every build must meet:

- Rest, hover, keyboard focus (a 2px brass outline, 2px away), pressed, disabled and, for a button, loading.
- A field: empty, filled, help, error (red edge, the message under it, the value kept), disabled.
- A state is said in words; colour and an icon support the word.
- Nothing opens, moves or plays by itself: a carousel, a video and a map wait to be asked.
- Sample content (`[NUMBER]`, `[PRICE]`, `[YOUR ADDRESS]`, grey image tiles) marks where the site owner's facts go.
  A block that holds only sample content is hidden from visitors.

## Desktop, tablet and mobile behaviour

- Drawn at 1280px and 375px. Between them grids fold from four or three across, to two, to one.
- Under 782px the navigation becomes one "Menu" button; the open menu lists every link and the header's actions.
- Section padding drops from 96px to 64px. Headings take the smaller end of their fluid size.
- Nothing scrolls sideways at 375px; a wide table scrolls inside its own box.
- A target is at least 44px.

## LTR, RTL and mixed-script behaviour

- Logical properties throughout: nothing is mirrored by hand. `HomePageArabic` is the proof.
- Arabic text, headings and labels are IBM Plex Sans Arabic, line height 1.8 for text and 1.3 for headings, no
  letter spacing, never uppercase. The eyebrow becomes plain small text.
- Chevrons and arrows that point forward flip; a check, a search glass and a close mark do not.
- A Latin value inside an Arabic form (an email address) stays left to right.

## Accessibility expectations

- WCAG 2.2 AA. Text 4.5:1 (3:1 from 24px); control edges, focus rings and icons 3:1.
- Brass `#ad8643` is 3.35:1 on white: it is a button ground, a ring or a band, never text on a page surface. Brass
  as text is `accent-dark`. The label on brass is graphite.
- Landmarks: one `banner`, one navigation named "Main", one `main`, one `contentinfo`; one heading 1 per page.
- Motion is colour, border and small position changes, removed under `prefers-reduced-motion`.

## Performance and conditional assets

- No CSS framework and no global asset (constitution V and VI). A block's styles load only where the block is.
- The kit's stylesheet in the design system is one file for preview only. In the code each rule belongs to
  `theme.json`, to a block's own stylesheet, or to a block style.
- No web font is added: the four font files of R2.1 stay the limit.

## What was checked, and what was not

- **Checked**, in a headless browser from the same files the design system serves: every component, block and
  template renders in the light theme at 1280px and 375px with no sideways overflow; components and blocks also in
  the dark theme; each template has exactly one heading 1.
- **Not checked**: the design system page itself as the owner sees it; keyboard behaviour; the contrast of every
  pair (the palette pairs of the contrast matrix were computed; the tightest component pair computed is success
  text on its tint, 4.6:1); the dark theme on templates other than the home page.

## Open questions

1. **The logo set** needs its own approval (see Exclusions).
2. **Six blocks have no CoreX block or pattern yet**: Timeline, Location, VideoFeature, FilterBar, PageRating,
   CookieNotice. Four of them look like the designs of stories the plan already has (a map on request, a video in
   a dialog, filtering an archive, saying whether a page helped); that was read from the story titles only and
   must be checked against their criteria. Timeline and CookieNotice have no story. Each needs one before it is
   built.
3. **Form patterns** are owed.
4. **A `brand.json` whose `accent` is dark** needs a light `on-accent`: the branding guide must say so.
5. **The docs site's design-system pages** and `ApprovedComponentInventory` should be brought into step with this
   kit, so there is one list (US-BLK-15).

## How to change this design

A change to the look starts in the design system, then reaches the code through `theme.json` (AGENTS.md, "UI, UX and
front end", step 7). The kit's working sources (one text file per group of items, the stylesheet sections and the
build and render-check scripts) were left with the session notes of 2026-11-15; the design system itself is the
record that lasts.
