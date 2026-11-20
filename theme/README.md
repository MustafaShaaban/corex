# Corex Theme

The CoreX FSE block theme — presentation only. It ships templates, template parts,
patterns, and `theme.json` design tokens; all domain logic lives in the plugins and
add-ons (constitution Principle I). Nothing here reads a database or defines business
behavior.

## Templates (`templates/`)

| Template | Covers |
| --- | --- |
| `front-page` | Home |
| `page-about` | About (custom template) |
| `page-services` | Services (custom template) |
| `page-contact` | Contact (custom template) |
| `page-landing` | Landing (custom template) |
| `page-account` | Account — hosts the `corex/account` block (custom template) |
| `page-form` | A single embedded form (custom template) |
| `index` / `archive` | Blog and archives, with a truthful `query-no-results` state |
| `single` | Single post — includes comments and a related-posts row |
| `single-corex_project` / `archive-corex_project` | Portfolio / project |
| `search` | Search results, with a no-results fallback |
| `404` | Not found |

Custom page templates are declared in `theme.json` → `customTemplates`.

## Parts (`parts/`)

`header` and `footer` — the two template-part areas. Header and footer **variants** are
patterns (below) the site owner assigns to these parts.

## Patterns (`patterns/`)

- **Headers** — `header-simple`, `header-corporate` (top bar), `header-saas`
  (mega-menu + search overlay), `header-docs`, `header-transparent`, `header-minimal`,
  and `header-sticky` (solid sticky header with a scrolled-elevation state).
- **Mega menus** — `megamenu-simple`, `megamenu-product`, `megamenu-services`,
  `megamenu-docs` (native `<details>`; JS adds single-open + Escape/outside-close).
- **Footers** — `footer-simple`, `footer-corporate`, `footer-saas`, `footer-legal`,
  `footer-locations`, `footer-newsletter`.
- **Sections** — `section-services-grid`, `section-process-steps`, `section-selected-work`,
  `section-contact-info`, `section-logo-cloud`, `section-newsletter`, plus `project-card`.
- **States** — `maintenance` (mirrors the live `Corex\Admin\StandalonePage` surface) and
  `loading` (an accessible, animation-free skeleton).

Richer composition patterns (`corex/hero`, `corex/features`, `corex/cta`,
`corex/testimonial`, `corex/contact`, `corex/stats`, `corex/faq`, `corex/content-split`,
`corex/section-header`, `corex/news`) are registered by the **Corex UI** add-on.

## Navigation behavior (`assets/js/corex-navigation.js`)

Buildless, framework-free progressive enhancement, loaded only where a CoreX header
renders (Principle VI):

- **Mega menus** — opening one panel closes its siblings; Escape or an outside click
  closes the open panel and returns focus to its summary. Fully usable with no JS.
- **Sticky / transparent headers** — a passive, rAF-throttled scroll listener flips
  `data-corex-header-state="top|scrolled"` on `.corex-header--transparent` and
  `.corex-header--sticky` so CSS can resolve the elevation; reduced-motion is CSS-gated.
- **Search overlay** — a hidden-until-enhanced toggle reveals the search panel, moving
  focus into it; Escape or an outside click closes it and returns focus to the toggle.
  With no JS the panel is a normal inline search form.

## Tokens

All colors, sizes, spacing, radii, shadows, and fonts come from `theme.json` presets and
custom properties. No raw literals ship in CSS (enforced by the token-consumer contract
tests). Layout is RTL-first via logical properties.

## The favicon (`functions.php`, `inc/favicon.php`, `assets/favicon/`)

`functions.php` holds one presentation hook: when the site has no site icon, `wp_head` links the CoreX favicon
(SVG, 16, 32, 512 and an apple-touch 180) from the theme's own copies in `assets/favicon/`. A site icon chosen in
WordPress always wins, and the Coming soon page links none. The copies are written by
`scripts/generate-logo-assets.py`, and `LogoAssetContractTest` holds them equal to the logo folder's (spec 134,
FR-022; DECISIONS #637, #647).

## The preview image (`screenshot.png`)

The card in Appearance, Themes shows `screenshot.png`: the front page at 1200 by 900, in the default style,
with the Company kit applied and its neutral sample content. `screenshot.fingerprint.json` beside it holds a
SHA-256 hash of the colours of `theme.json` and of `styles/dark.json` when the image was taken: each palette
(slug to colour), `settings.custom.color` and the shadows (slug to shadow), with keys sorted and no whitespace,
so only a changed value changes it. `tests/theme-preview.test.js` recomputes the hashes and fails, naming the
command, when a colour changed after the capture; it also reads the image's size from its header (spec 134,
FR-025 to FR-027; DECISIONS #638).

After a change to a colour, a custom colour or a shadow, take the image again and commit both files:

```bash
npm run theme:preview -- --url=http://127.0.0.1:8917 --wp-path=/path/to/scratch/wordpress
```

`--url` and `--wp-path` (or `COREX_BASE_URL` and `COREX_WP_PATH`) name the scratch site and the WordPress
folder that serves it; neither has a default, and a folder with no `wp-load.php` is refused. The command runs
no write of its own: it loads WordPress once through WP-CLI and requests the front page once, as a visitor
would. It needs Playwright's Chromium (`npx playwright install chromium`) and WP-CLI, which it starts with no
shell, so a path with spaces is passed as it is. It runs `wp`; on Windows, where `wp` is a batch file, it runs
`php` with the WP-CLI behind the first `wp.bat` on the `PATH` (Composer's `wp-cli/php/boot-fs.php`, or a
`wp-cli.phar` beside it). Set `COREX_WP_CLI` to a WP-CLI phar or `boot-fs.php` to choose it, and `WP_CLI_PHP`
for another PHP.

The scratch site is a clean WordPress made for the capture, never a copy of a real site:

1. Install WordPress with a neutral title (for example "Example Company"), `WP_ENVIRONMENT_TYPE` set to
   `local` in `wp-config.php`, and pretty permalinks.
2. Link this repository's `theme/` as `wp-content/themes/corex` and activate it. Link and activate
   `corex-core` first, then `corex-config`, `corex-blocks`, `corex-forms` and the add-ons `corex-ui`,
   `corex-email` and `corex-kit-company`.
3. Apply the Company kit at Minimal, which makes its pages and sets its Home as the front page, with the
   Setup Wizard or with this command, which reads the same in Bash and in PowerShell (run it from this
   repository, with the WordPress folder as `--path`):

   ```bash
   wp --user=admin --path=/path/to/scratch/wordpress eval-file scripts/theme-preview-site.php
   ```

4. Serve it at the address you pass as `--url`.

The command refuses, and writes nothing, when:

- the folder holds no WordPress, or WP-CLI fails (its own error is printed) or reads a site at another
  address than `--url`;
- CoreX does not read the site as development (production, staging, Coming soon or Maintenance);
- the active theme is not `corex`, or is not this checkout's `theme/` folder (a copied theme could be older);
- a style such as Dark is chosen or the site has styles of its own, or a template or part was changed in
  the Site Editor;
- the Company kit is not applied, or the front page is not the page the kit makes the front page (its Home);
- a published post, page or menu is neither the kit's nor WordPress's own sample (the "Hello world!" post,
  the Sample Page, and the menu the Navigation block writes when it lists the pages);
- the colours the page serves differ from `theme.json`, so the image and the fingerprint would disagree;
- the page shows CoreX product copy: "CoreX" or "Core X", "Discipline, at every layer", or "framework".

Chromium draws the text with greyscale edges (`--disable-lcd-text`), so the image does not take the colour
fringes of the screen it was made on. The build for a host (`npm run build:dist`) leaves the fingerprint out;
the image ships.

The image shows the top of the front page as the release leaves it. The releases that build the kit's blocks
and templates each take a new one.

## The gutter

The space between what a visitor reads and each edge of the window is the theme's root
padding: `styles.spacing.padding` in `theme.json`, left and right, both
`var(--wp--preset--spacing--40)`. WordPress gives it to every constrained block, so a
template, a part or a pattern gets it by being constrained and writes no side padding of
its own. A block set to full width runs to both edges and keeps the gutter for what is
inside it.

Two rules follow:

- A template that prints a post's content prints it full width
  (`<!-- wp:post-content {"align":"full","layout":{"type":"constrained"}} /-->`). Inside
  the constrained `<main>` a content block that is not full width holds a full-width block
  in it to the text column. `tests/Unit/Theme/ThemePageCoverageTest.php` holds this for
  every template.
- A full-width section placed inside a block that is neither full width nor constrained
  keeps the gutter as padding and stands out of line with the text around it. Make the
  blocks around it full width, as `templates/search.html` does for what it shows when
  nothing is found.

`tests/e2e/theme-gutter.spec.js` measures every template and every registered pattern at
375 and 320 pixels, left to right and right to left.
