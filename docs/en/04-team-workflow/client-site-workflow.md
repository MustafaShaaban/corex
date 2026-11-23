---
title: Client-site workflow
audience: team
stability: stable
---

# Client-site workflow

How a team builds a real company site on CoreX without touching framework internals. Examples use the neutral
**Acme** placeholder: substitute your real client only inside the generated client source.

## 1. Generate the client source

```bash
wp corex make:site Acme
```

This scaffolds a client **plugin** (`acme-site`, namespace `AcmeSite\`) and **theme** (`acme-theme`) with their own
prefixes, plus governance files (`AGENTS.md`, `CLAUDE.md`, `README.md`, `PROGRESS.md`, `DECISIONS.md`) and `specs/`
+ `docs/`. The target location for client source is:

```text
sites/acme/
  acme-site/
  acme-theme/
    style.css                   Template: corex
    theme.json                  every CoreX token, with CoreX's value, for you to change
    templates/coming-soon.html  the launch page
  AGENTS.md  CLAUDE.md  README.md  PROGRESS.md  DECISIONS.md
  specs/  docs/
```

The theme is a **child of the CoreX theme**: its `style.css` names `Template: corex`. It has no header or footer
part, no front page and no index template of its own. Those, the patterns, the style variations, the block styles
and the fonts come from the CoreX theme, so the site looks like CoreX until the client's design replaces a piece
of it (section 3). `make:site` reads the CoreX theme's `theme.json` from `theme/` in the repository, or from the
installed theme `corex`; when it finds neither it stops before writing a file and names both paths.
`--plugin-only` does not need it.

> Run it with `--dir=sites/acme` so the client plugin/theme land directly under `sites/acme/` as `acme-site/`
> and `acme-theme/` (the flat layout above). **Backward compatibility:** sites generated before this layout used a
> nested `plugins/` + `themes/` structure; those keep working as-is. Only newly generated sites use the flat
> layout, and the shared-host `dist` builder packages either shape.

## 2. Work in Client Site Mode

Every session on the client site is **[Client Site Mode](./agent-roles.md#2-client-site-mode)**:

- Edit **only** `sites/<client>/`.
- Do **not** edit `plugins/`, `addons/`, `packages/`, root `theme/`, root `specs/`, `ROADMAP.md`, or root
  `PROGRESS.md`.
- Do **not** edit `wp/wp-content/` or `dist/` as source.
- Keep client specs in `sites/<client>/specs/`, progress in `sites/<client>/PROGRESS.md`, decisions in
  `sites/<client>/DECISIONS.md`.
- Follow **the CoreX loop** (specs in `sites/<client>/specs/`), the **Guard Gate**, and **UI/UX ProMax**.
- For a framework bug, **stop** and open a CoreX Framework Mode task — never patch CoreX internals for one client.

Everything outside `sites/` and `.github/workflows/site-*.yml` is framework-owned, the repository root
included, and client work never edits it: an edit there is a merge conflict in every later framework update.
`npm run verify:framework` proves it — it compares every framework-owned path with the release recorded in
`sites/<client>/corex-baseline.json` and fails on anything that differs. Run it before pushing; the generated
`site-<client>.yml` workflow runs it on every pull request. (`wp corex compliance:check` answers a narrower
question — whether a list of file names you pass it touches a framework folder — and needs no git.)

To take a new CoreX release, follow [Updating CoreX in a client site](../05-deployment/updating-a-client-site.md).

## 3. The client theme: values, structure, and what it inherits

The client theme is a child of the CoreX theme. WordPress reads the CoreX theme first and lays the client
theme over it, so the client theme holds only what is the client's.

### Values

Brand values (colours, fonts, sizes, spacing, shadows, the `custom` tokens, the content widths) go in the client
theme's `theme.json`. `make:site` writes it with every token the CoreX theme defines, each with CoreX's value at
the time of generation: change the values to the client's. Never edit the CoreX theme for one client.

**The full-list rule.** A preset list in a child's `theme.json` replaces the CoreX theme's whole list:
`settings.color.palette`, `settings.color.gradients`, `settings.color.duotone`, `settings.typography.fontFamilies`,
`settings.typography.fontSizes`, `settings.spacing.spacingSizes` and `settings.shadow.presets`. A palette of two
colours leaves the site with two colours, and every CoreX style that reads a missing one loses it. So a list you
keep must keep every entry: change an entry's value, never delete the entry. A list you remove altogether is
inherited whole from the CoreX theme, which is fine. `settings.custom` and `settings.layout` are merged key by
key, so a key you leave out keeps CoreX's value.

`npm run verify:client-theme` checks the rule for every client theme under `sites/` that names `Template: corex`:
each list its `theme.json` and its `styles/*.json` declare must name every slug of the CoreX theme's same list,
and every `file:./` font source it lists must exist in the client theme or the CoreX theme. It prints one
`MISSING` line per entry it does not find (the file, the list, the slug) and fails; add the entry to the list it
names. A theme with no `Template:` line is reported as `STANDALONE` and not checked. The generated
`site-<client>.yml` workflow runs it on every pull request.

A font family whose source is `file:./assets/fonts/...` is served from the CoreX theme's folder when the client
theme does not hold the file, so the generated list needs no copied font. Add a font of the client's own to the
client theme's `assets/fonts/` and name it in the list.

### Structure

A template or part file in the client theme replaces the CoreX theme's file of the same name, and a pattern in
the client theme's `patterns/` replaces the CoreX theme's pattern with the same `Slug:` header (a pattern with a
slug of its own is simply added). To give the client its own header, create `acme-theme/parts/header.html`; its
own home page, `acme-theme/templates/front-page.html`; and so on, when the client's design calls for it. Until
then the CoreX theme's file is used. A file you do not create is not a gap: it is the CoreX theme's.

- **Style variations.** The CoreX theme's variations (`styles/dark.json`) are offered under the client theme and
  keep CoreX's colours until the client theme ships a file of the same name, which replaces it. A variation's own
  preset list replaces the theme's when the variation is chosen, so the full-list rule applies to it too, in
  `styles/` and in any folder under it.
- **Custom templates and template parts.** `customTemplates` and `templateParts` in a child's `theme.json` merge
  with the CoreX theme's by position, so a list of one entry overwrites the CoreX theme's first. The generated
  `theme.json` declares neither. A client theme that declares one repeats every entry of the CoreX theme's, in the
  same order, then adds its own after them; `npm run verify:client-theme` checks both.
- **The launch page** is `acme-theme/templates/coming-soon.html`, the page visitors see while the site is in
  Coming soon mode. See [Coming soon mode](../03-operations/coming-soon.md).

### `brand.json`

A `brand.json` in the client theme keeps working. CoreX looks for it in the client theme first, then in the CoreX
theme's folder, and applies it at runtime to the client theme's own `theme.json` data, before WordPress merges
in the CoreX theme's, so its values win over the client theme's `theme.json`. Its palette and font families
are checked only for the roles CoreX's blocks need: a palette or family list in it that lacks one of those
roles is dropped, with a warning in the boot log. Like any list the client theme declares, a list in
`brand.json` replaces the CoreX theme's whole list, and `npm run verify:client-theme` does not read it. A
generated site puts its values in `theme.json`; a site that already has a `brand.json` can keep it.

### A site generated before the child theme

A client theme generated before this change is a standalone theme: no `Template:` line, an index, a header and a
footer of its own, and a `theme.json` without CoreX's tokens. It keeps working as it is, and nothing converts it:
`wp corex make:site --force` leaves the `style.css` and `theme.json` of any theme that already has a `style.css`
untouched, and says so. To make one a child by hand:

1. Add `Template: corex` to the header of its `style.css`.
2. In its `theme.json`, make each preset list it declares complete (every entry of the CoreX theme's, with the
   client's values), or remove the list to inherit CoreX's.
3. Run `npm run verify:client-theme` until it passes, and add it to the site's own workflow,
   `.github/workflows/site-<client>.yml`, as a step beside `node scripts/verify-framework.mjs`: a site
   generated before this change has a workflow without it.
4. Decide which of its own `templates/index.html`, `templates/front-page.html`, `parts/header.html` and
   `parts/footer.html` it still wants. Each one it keeps replaces the CoreX theme's; delete the ones the
   client's design does not need.

### New agent files for an existing site

`make:site` writes `AGENTS.md` and `CLAUDE.md` for a new site only. To take a newer version into an existing site,
generate a scratch site with the site's own name, into a scratch folder whose **last part is the site's folder
name**, and copy the two files across:

```bash
wp corex make:site Muva --dir=/tmp/scratch/muva
```

The folder name matters: the generated files name the site's folder in their paths (`sites/muva/specs/`), and
that name is the last part of `--dir`. Compare the scratch files with the site's own, keep what the site added,
and delete the scratch folder.

## 4. Reusable vs client-specific

- A block/component useful across sites → contribute it to the **framework** (`addons/corex-ui` etc.) via a CoreX
  Framework Mode task.
- A company-specific block/page/template → keep it in `sites/<client>/`.

### A block of the site's own

From the repository root, with one site under `sites/`:

```bash
wp corex make:block Banner
cd sites/acme/acme-site
npm install
npm run build
```

`make:block` writes the block into the site plugin, `acme-site/src/Blocks/banner/` with its renderer beside it,
named `acme-site/banner` and translated in the `acme-site` text domain. The plugin requires `corex-core` and
boots its provider once CoreX is up; the provider registers every folder of `src/Blocks` that has a
`block.json`, from `build/blocks` once `npm run build` has run. The plugin's `package.json` has its own
`@wordpress/scripts`, so it builds from a clean install of its own folder. A package built for deployment fails
`npm run verify:dist` when a site plugin has block sources and no `build/blocks`, so build the plugin before
packaging.

## 5. Build & deploy

The team commits **source only**. A deployable artifact is built into `dist/` (never committed) and deployed by
Azure Pipelines. See [Shared-host dist](../05-deployment/shared-host-dist.md) and
[Azure Pipelines](../05-deployment/azure-pipelines.md).
