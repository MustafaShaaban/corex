---
title: Foundations
description: The design tokens CoreX ships (colour, type, spacing, shadow, radius, tracking, motion) in the theme and in the admin, written from the files themselves, and the guidelines that go with them.
---

Every CoreX component consumes **design tokens**, never a hardcoded value. The theme's tokens live in
`theme/theme.json` and reach the page as CSS custom properties at runtime; a per-site `brand.json` overrides them
without a recompile (spec 006). The CoreX admin has its own scoped set, described below.

The tables on this page are written from the shipped files by `node scripts/foundations-tokens.mjs --write`. The
docs build and the test suite run `--check`, which fails naming each token whose row no longer matches the files,
so this page cannot describe a token CoreX does not ship.

## One brand, several copies

The brand's colours, shadows, radius and motion are written once, in `design/tokens/brand-contract.json`, with
the values of the admin set as shipped. The admin token stylesheet, the email palette
(`Corex\Email\Design\EmailPalette`) and the theme's radius and motion each hold a copy, and
`tests/Unit/Theme/BrandContractTest.php` fails, naming the token and the copy, when one of them holds another
value. The theme's colours and shadows are bound to the contract when the theme moves to graphite and brass
(US-BRD-01); until then the theme tables below show the palette the theme ships today.

A `brand.json` overrides the **theme only**. It never reaches the admin, the login screen or the emails.

## Theme tokens

| Group | Custom property |
|---|---|
| Colour | `--wp--preset--color--<slug>` |
| Font family | `--wp--preset--font-family--<slug>` |
| Font size | `--wp--preset--font-size--<slug>`, with a letter after a digit starting a word (`2xl` is `2-xl`) |
| Spacing | `--wp--preset--spacing--<slug>` |
| Shadow | `--wp--preset--shadow--<slug>` |
| Radius, tracking, motion | `--wp--custom--<group>--<key>` |

WordPress's own spacing sizes, font sizes and shadows are turned off for the CoreX theme, so only these are
printed. Large font sizes scale down fluidly between a 375px and a 1200px window and reach the size shown at
the theme's wide size. Headings h1 to h6 use Heading 1, Heading 2, Heading 3, Title, Lead and Body, at weight 600
with the tracking tokens.

<!-- tokens:theme -->
| Colour | Custom property | Default | Dark variation | Use |
| --- | --- | --- | --- | --- |
| `primary` | `--wp--preset--color--primary` | `#0B1F3B` | `#00C2FF` | Primary |
| `primary-dark` | `--wp--preset--color--primary-dark` | `#06121F` | `#7DD3FC` | Primary dark |
| `accent` | `--wp--preset--color--accent` | `#007A9E` | `#7DD3FC` | Accent |
| `accent-dark` | `--wp--preset--color--accent-dark` | `#006080` | `#38BDF8` | Accent dark |
| `surface` | `--wp--preset--color--surface` | `#FFFFFF` | `#0B1F3B` | Surface |
| `surface-alt` | `--wp--preset--color--surface-alt` | `#F4F7FA` | `#102A4C` | Surface alt |
| `border` | `--wp--preset--color--border` | `#8795A3` | `#71869C` | Border |
| `ink` | `--wp--preset--color--ink` | `#11181C` | `#F1F5F9` | Ink |
| `ink-soft` | `--wp--preset--color--ink-soft` | `#5A6772` | `#C3CEDA` | Ink soft |
| `success` | `--wp--preset--color--success` | `#1A7F4B` | `#4ADE80` | Success |
| `warning` | `--wp--preset--color--warning` | `#875400` | `#FACC15` | Warning |
| `error` | `--wp--preset--color--error` | `#C1352B` | `#F87171` | Error |
| `info` | `--wp--preset--color--info` | `#1F6FEB` | `#60A5FA` | Info |
| `inverse` | `--wp--preset--color--inverse` | `#FFFFFF` | `#06121F` | Inverse text |
| `overlay` | `--wp--preset--color--overlay` | `rgba(6, 18, 31, 0.72)` | `rgba(0, 0, 0, 0.78)` | Overlay |
| `selection` | `--wp--preset--color--selection` | `#BFEFFF` | `#075985` | Selection |
| `selection-text` | `--wp--preset--color--selection-text` | `#06121F` | `#F0F9FF` | Selection text |
| `surface-raised` | `--wp--preset--color--surface-raised` | `#FFFFFF` | `#102A4C` | Raised surface |
| `surface-strong` | `--wp--preset--color--surface-strong` | `#E8EEF5` | `#183B61` | Strong surface |
| `background` | `--wp--preset--color--background` | `#FFFFFF` | `#0B1F3B` | Background (compatibility) |
| `foreground` | `--wp--preset--color--foreground` | `#11181C` | `#F1F5F9` | Foreground (compatibility) |
| `danger` | `--wp--preset--color--danger` | `#C1352B` | `#F87171` | Danger (compatibility) |
| `corex-danger` | `--wp--preset--color--corex-danger` | `#C1352B` | `#F87171` | CoreX danger (compatibility) |
| `corex-info` | `--wp--preset--color--corex-info` | `#1F6FEB` | `#60A5FA` | CoreX info (compatibility) |
| `corex-primary` | `--wp--preset--color--corex-primary` | `#0B1F3B` | `#00C2FF` | CoreX primary (compatibility) |
| `corex-success` | `--wp--preset--color--corex-success` | `#1A7F4B` | `#4ADE80` | CoreX success (compatibility) |
| `corex-warning` | `--wp--preset--color--corex-warning` | `#875400` | `#FACC15` | CoreX warning (compatibility) |
| `white` | `--wp--preset--color--white` | `#FFFFFF` | `#06121F` | White (compatibility) |

| Font family | Custom property | Stack |
| --- | --- | --- |
| `heading` | `--wp--preset--font-family--heading` | `'Space Grotesk', system-ui, sans-serif` |
| `arabic` | `--wp--preset--font-family--arabic` | `'IBM Plex Sans Arabic', system-ui, sans-serif` |
| `body` | `--wp--preset--font-family--body` | `system-ui, sans-serif` |
| `mono` | `--wp--preset--font-family--mono` | `'JetBrains Mono', ui-monospace, 'Cascadia Code', monospace` |

| Font size | Custom property | Size | Smallest on a phone | Role |
| --- | --- | --- | --- | --- |
| `xs` | `--wp--preset--font-size--xs` | `0.75rem` | fixed | Caption |
| `sm` | `--wp--preset--font-size--sm` | `0.875rem` | fixed | Small |
| `base` | `--wp--preset--font-size--base` | `1rem` | fixed | Body |
| `lg` | `--wp--preset--font-size--lg` | `1.375rem` | `1.125rem` | Lead |
| `xl` | `--wp--preset--font-size--xl` | `1.75rem` | `1.375rem` | Title |
| `2xl` | `--wp--preset--font-size--2-xl` | `2.25rem` | `1.75rem` | Heading 3 |
| `3xl` | `--wp--preset--font-size--3-xl` | `3rem` | `2.125rem` | Heading 2 |
| `hero` | `--wp--preset--font-size--hero` | `4rem` | `2.5rem` | Heading 1 |
| `display` | `--wp--preset--font-size--display` | `5.5rem` | `3rem` | Display |
| `small` | `--wp--preset--font-size--small` | `0.875rem` | fixed | Small (compatibility) |
| `medium` | `--wp--preset--font-size--medium` | `1rem` | fixed | Medium (compatibility) |

| Spacing | Custom property | Size |
| --- | --- | --- |
| `10` | `--wp--preset--spacing--10` | `0.25rem` |
| `20` | `--wp--preset--spacing--20` | `0.5rem` |
| `25` | `--wp--preset--spacing--25` | `0.75rem` |
| `30` | `--wp--preset--spacing--30` | `1rem` |
| `40` | `--wp--preset--spacing--40` | `1.5rem` |
| `50` | `--wp--preset--spacing--50` | `2rem` |
| `60` | `--wp--preset--spacing--60` | `3rem` |
| `70` | `--wp--preset--spacing--70` | `4rem` |
| `80` | `--wp--preset--spacing--80` | `6rem` |

| Shadow | Custom property | Value |
| --- | --- | --- |
| `sm` | `--wp--preset--shadow--sm` | `0 1px 2px rgba(11, 31, 59, 0.08)` |
| `md` | `--wp--preset--shadow--md` | `0 4px 12px rgba(11, 31, 59, 0.10)` |
| `lg` | `--wp--preset--shadow--lg` | `0 12px 32px rgba(11, 31, 59, 0.14)` |

| Custom property | Value |
| --- | --- |
| `--wp--custom--radius--xs` | `0.1875rem` |
| `--wp--custom--radius--sm` | `0.375rem` |
| `--wp--custom--radius--md` | `0.625rem` |
| `--wp--custom--radius--lg` | `1rem` |
| `--wp--custom--radius--full` | `999px` |
| `--wp--custom--tracking--tightest` | `-0.035em` |
| `--wp--custom--tracking--tighter` | `-0.03em` |
| `--wp--custom--tracking--tight` | `-0.025em` |
| `--wp--custom--tracking--snug` | `-0.02em` |
| `--wp--custom--tracking--close` | `-0.015em` |
| `--wp--custom--tracking--slight` | `-0.01em` |
| `--wp--custom--motion--duration--instant` | `80ms` |
| `--wp--custom--motion--duration--fast` | `140ms` |
| `--wp--custom--motion--duration--base` | `200ms` |
| `--wp--custom--motion--duration--slow` | `320ms` |
| `--wp--custom--motion--easing--standard` | `cubic-bezier(0.2, 0, 0, 1)` |
| `--wp--custom--motion--easing--emphasized` | `cubic-bezier(0.3, 0, 0, 1)` |
<!-- /tokens:theme -->

### Consuming a token

```css
.my-component {
	padding-block: var(--wp--preset--spacing--25);
	padding-inline: var(--wp--preset--spacing--40);
	border-radius: var(--wp--custom--radius--sm);
	transition: opacity var(--wp--custom--motion--duration--fast) var(--wp--custom--motion--easing--standard);
}
.my-component:focus-visible {
	outline: var(--wp--custom--focus--width) solid var(--wp--custom--focus--color);
	outline-offset: var(--wp--custom--focus--offset);
}
```

Never write a raw hex value, size, font, radius, shadow or duration in a component; add a token if one is
missing (constitution principle V). Use logical properties (`padding-inline`, `margin-block-start`), so the same
rule reads correctly right to left.

## Type

| Role | Family | Where |
|---|---|---|
| Headings and interface labels (buttons, tabs, navigation, form labels) | Space Grotesk | site, admin, login |
| Long body text | the system font | site, admin, login, emails |
| Code and technical labels | JetBrains Mono | site, admin |
| Arabic | IBM Plex Sans Arabic | site and admin, on pages in a language written in Arabic script |

On a page whose language is written in Arabic script (Arabic, Persian, Urdu and others), body text and
headings use IBM Plex Sans Arabic, at line height 1.8 for body text and 1.3 for headings. The Arabic faces
declare the Arabic character range, so a page downloads them only when it has Arabic text to draw, and the Latin
words inside an Arabic sentence stay in the Latin face. The admin and the login do the same right to left.

Four self-hosted WOFF2 files, OFL-licensed, with `font-display: swap` and no external font service. The theme and
the core plug-in each keep a copy (the theme must work without the plug-in); the font test counts both and
requires them identical. Provenance is recorded in `theme/assets/fonts/manifest.json`.

## Admin token adapter

CoreX's own admin screens, its login screen and the Coming soon bar read a scoped set, `--corex-admin-*`, from
`plugins/corex-core/assets/css/corex-admin-tokens.css`:

- **Scope.** The set is declared on `.corex-admin`, `body.login.corex-login` and `body.corex-admin-screen`, never
  on `:root`, `html` or a bare `body`. It is registered and never enqueued globally: only CoreX's own admin styles
  load it, and no front-end theme stylesheet is loaded in wp-admin.
- **Dark by default.** The values the set declares first are the dark ones.
- **Light.** Choosing Light (`[data-corex-theme="light"]` on the admin, `.corex-appearance-light` on the login and
  the full-width admin body) applies the light values. Choosing System applies the same light values when the
  computer is in light mode; the two are one list, checked by the brand contract test.
- **Right to left.** Body and display text switch to IBM Plex Sans Arabic, at line heights 1.8 and 1.3.

<!-- tokens:admin -->
| Custom property | Dark (default) | Light |
| --- | --- | --- |
| `--corex-admin-shell` | `#0d0e11` | `#eceef1` |
| `--corex-admin-canvas` | `#16181d` | `#f6f7f9` |
| `--corex-admin-surface` | `#16181d` | `#f6f7f9` |
| `--corex-admin-surface-alt` | `#1a1d23` | `#f3f4f6` |
| `--corex-admin-surface-raised` | `#1c1f26` | `#fff` |
| `--corex-admin-text` | `#f4f5f7` | `#14151a` |
| `--corex-admin-text-muted` | `#9aa0ab` | `#5b616d` |
| `--corex-admin-text-subtle` | `#646b78` | `#8b9099` |
| `--corex-admin-border` | `#262a32` | `#e2e5ea` |
| `--corex-admin-border-soft` | `#20242b` | `#eef0f3` |
| `--corex-admin-border-strong` | `#343b47` | `#c2c8d1` |
| `--corex-admin-action` | `#c9a25e` | `#ad8643` |
| `--corex-admin-action-hover` | `#e6cf9f` | `#8b6427` |
| `--corex-admin-action-ink` | `#14151a` | `#fff` |
| `--corex-admin-action-subtle` | `rgb(201 162 94 / 14%)` | `rgb(173 134 67 / 12%)` |
| `--corex-admin-link` | `#e2c188` | `#76521d` |
| `--corex-admin-link-hover` | `#f0d4a4` | `#573a10` |
| `--corex-admin-success` | `#5fb787` | `#2f8f5b` |
| `--corex-admin-warning` | `#e0a33a` | `#8a5b13` |
| `--corex-admin-error` | `#e0645c` | `#b23530` |
| `--corex-admin-info` | `#6e9be6` | `#176b96` |
| `--corex-admin-focus` | `#f0c97d` | `#8b6427` |
| `--corex-admin-success-soft` | `rgb(95 183 135 / 13%)` | `rgb(47 143 91 / 12%)` |
| `--corex-admin-warning-soft` | `rgb(224 163 58 / 13%)` | `rgb(181 120 31 / 12%)` |
| `--corex-admin-error-soft` | `rgb(224 100 92 / 13%)` | `rgb(194 67 59 / 12%)` |
| `--corex-admin-info-soft` | `rgb(110 155 230 / 13%)` | `rgb(23 107 150 / 12%)` |
| `--corex-admin-overlay` | `rgb(7 9 12 / 72%)` | `rgb(15 17 22 / 54%)` |
| `--corex-admin-login-backdrop` | `radial-gradient(circle at 20% 0%, rgb(201 162 94 / 18%), transparent 34%)` | `radial-gradient(circle at 20% 0%, rgb(139 100 39 / 15%), transparent 34%)` |
| `--corex-admin-font-display` | `"CoreX Space Grotesk", "Segoe UI", sans-serif` | `"CoreX Space Grotesk", "Segoe UI", sans-serif` |
| `--corex-admin-font-body` | `system-ui, -apple-system, "Segoe UI", sans-serif` | `system-ui, -apple-system, "Segoe UI", sans-serif` |
| `--corex-admin-font-mono` | `"CoreX JetBrains Mono", ui-monospace, monospace` | `"CoreX JetBrains Mono", ui-monospace, monospace` |
| `--corex-admin-font-arabic` | `"CoreX Arabic", "Segoe UI", sans-serif` | `"CoreX Arabic", "Segoe UI", sans-serif` |
| `--corex-admin-font-ui` | `var(--corex-admin-font-display)` | `var(--corex-admin-font-display)` |
| `--corex-admin-font-xs` | `0.75rem` | `0.75rem` |
| `--corex-admin-font-sm` | `0.875rem` | `0.875rem` |
| `--corex-admin-font-md` | `1rem` | `1rem` |
| `--corex-admin-font-lg` | `1.25rem` | `1.25rem` |
| `--corex-admin-font-xl` | `1.5rem` | `1.5rem` |
| `--corex-admin-font-2xl` | `clamp(1.75rem, 3vw, 2.5rem)` | `clamp(1.75rem, 3vw, 2.5rem)` |
| `--corex-admin-line-tight` | `1.2` | `1.2` |
| `--corex-admin-line-normal` | `1.6` | `1.6` |
| `--corex-admin-letter-wide` | `0.12em` | `0.12em` |
| `--corex-admin-space-2xs` | `0.25rem` | `0.25rem` |
| `--corex-admin-space-xs` | `0.5rem` | `0.5rem` |
| `--corex-admin-space-sm` | `0.75rem` | `0.75rem` |
| `--corex-admin-space-md` | `1rem` | `1rem` |
| `--corex-admin-space-lg` | `1.5rem` | `1.5rem` |
| `--corex-admin-space-xl` | `2rem` | `2rem` |
| `--corex-admin-space-2xl` | `3rem` | `3rem` |
| `--corex-admin-radius-xs` | `0.1875rem` | `0.1875rem` |
| `--corex-admin-radius-sm` | `0.375rem` | `0.375rem` |
| `--corex-admin-radius-md` | `0.625rem` | `0.625rem` |
| `--corex-admin-radius-lg` | `1rem` | `1rem` |
| `--corex-admin-radius-pill` | `999px` | `999px` |
| `--corex-admin-border-width` | `1px` | `1px` |
| `--corex-admin-state-border` | `4px` | `4px` |
| `--corex-admin-focus-width` | `3px` | `3px` |
| `--corex-admin-focus-offset` | `2px` | `2px` |
| `--corex-admin-control-height` | `2.5rem` | `2.5rem` |
| `--corex-admin-touch-target` | `2.75rem` | `2.75rem` |
| `--corex-admin-content-max` | `92rem` | `92rem` |
| `--corex-admin-measure` | `68ch` | `68ch` |
| `--corex-admin-page-min-height` | `calc(100vh - 7rem)` | `calc(100vh - 7rem)` |
| `--corex-admin-login-inline-size` | `min(25rem, calc(100% - 2rem))` | `min(25rem, calc(100% - 2rem))` |
| `--corex-admin-login-logo-height` | `3rem` | `3rem` |
| `--corex-admin-login-card` | `var(--corex-admin-surface)` | `#fff` |
| `--corex-admin-login-field` | `var(--corex-admin-surface-raised)` | `#f6f7f9` |
| `--corex-admin-disabled-opacity` | `0.58` | `0.58` |
| `--corex-admin-shadow-sm` | `0 1px 2px rgb(0 0 0 / 20%)` | `0 1px 2px rgb(20 21 26 / 8%)` |
| `--corex-admin-shadow-md` | `0 8px 24px rgb(0 0 0 / 24%)` | `0 8px 24px rgb(20 21 26 / 10%)` |
| `--corex-admin-shadow-lg` | `0 18px 48px rgb(0 0 0 / 28%)` | `0 18px 48px rgb(20 21 26 / 14%)` |
| `--corex-admin-z-modal` | `var(--wp--custom--z--modal, 1000)` | `var(--wp--custom--z--modal, 1000)` |
| `--corex-admin-motion-instant` | `80ms` | `80ms` |
| `--corex-admin-motion-fast` | `140ms` | `140ms` |
| `--corex-admin-motion-base` | `200ms` | `200ms` |
| `--corex-admin-motion-slow` | `320ms` | `320ms` |
| `--corex-admin-motion-spin` | `900ms` | `900ms` |
| `--corex-admin-ease` | `cubic-bezier(0.2, 0, 0, 1)` | `cubic-bezier(0.2, 0, 0, 1)` |
| `--corex-admin-ease-emphasized` | `cubic-bezier(0.3, 0, 0, 1)` | `cubic-bezier(0.3, 0, 0, 1)` |
| `--corex-admin-skeleton` | `var(--corex-admin-border)` | `var(--corex-admin-border)` |
| `--corex-admin-skeleton-shine` | `var(--corex-admin-border-strong)` | `var(--corex-admin-border-soft)` |
| `--corex-admin-motion-shimmer` | `1400ms` | `1400ms` |
| `--corex-admin-loading-delay` | `160ms` | `160ms` |
<!-- /tokens:admin -->

## Guidelines

- **Right to left.** Style with logical properties so Arabic layouts are correct by default; `postcss-rtlcss`
  handles edge cases only (principle VIII).
- **Accessibility (WCAG 2.2 AA).** Convey state by more than colour; every interactive element is keyboard
  operable with a visible focus ring; text and controls meet contrast in light and dark. The admin's pairs are
  checked by `ContrastMatrixTest` and every screen by the quality checks.
- **Motion.** Use the motion tokens and respect `prefers-reduced-motion`.
- **Icons.** Inline, accessible SVG (`aria-hidden` when decorative, a label when meaningful); no icon font.

## Overriding for a brand

Put a client's palette and scales into its own theme's `theme.json`, or a per-site `brand.json` for overrides, and
run `wp corex brand:apply`. The theme tokens above are what a brand overrides; a rebrand is configuration, not a
recompile.
