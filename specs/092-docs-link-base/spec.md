# Feature Specification: Every link on the published docs site resolves

**Feature Branch**: `spec/092-docs-link-base`

**Created**: 2026-07-29

**Status**: Draft

**Input**: Owner report — seven documentation URLs return 404, all of them missing the `/corex/`
segment.

## Why this spec exists

Spec 090 gave `docs-app` a `base` because a GitHub project page is served from a repository subpath.
That fixed assets, the sidebar and the Pagefind index — all of which Astro owns and rewrites. It did
nothing to the 84 links a person had typed into the content, because Astro does not rewrite a
hand-written `[Getting Started](/getting-started/overview/)`. Those shipped verbatim and resolved off
the base path.

`dist/index.html` carried the contradiction in one file: `href="/corex/project-status/"` from the
sidebar, and `href="/project-status/"` from the body, on the same page.

### Nothing checked

Spec 090 verified the deployment against six URLs — the root, a deep page, a hashed asset, the
Pagefind index, the sitemap, a generated reference page. Every one of them is a form Astro rewrites.
The check was real and it could not have caught this, because it sampled only the half of the site
that was working.

**That is the actual defect.** A link that 404s is a bug; a documentation site with no link checking
is the condition that lets 85 of them publish at once. The test is the deliverable here, and the
rewrite is what makes it pass.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — A reader follows a link and arrives (Priority: P1)

**Independent Test**: fetch the seven reported URLs, plus a sample of the other 78, and get 200.

### User Story 2 — This cannot happen again silently (Priority: P1)

**Independent Test**: the link test fails against the current (broken) build and passes against the
fixed one. Both directions verified — a link test that has never failed proves nothing.

### User Story 3 — An operator clicking "Documentation" in wp-admin reaches documentation (Priority: P2)

`DocsUrl` fell back to a GitHub `blob` URL into `docs-app/src/content/docs`, handing an operator raw
Markdown with front matter showing. Correct when no site existed; wrong since v0.40.0.

### Edge Cases

- **A link that already carries the base** must not gain a second. The rewrite is idempotent, and a
  separate assertion checks for `/corex/corex/`.
- **Protocol-relative `//host/path`** is already absolute; prefixing it would rewrite somebody
  else's domain into a path on ours. Skipped explicitly.
- **`COREX_DOCS_BASE` must stay honest.** Hardcoding `/corex/` into 84 links would make the override
  decorative. The rewrite reads the same value the config does.
- **Frontmatter is not markdown.** `hero.actions[].link` never reaches rehype and Starlight does not
  prefix it either, so it is the one literal path — commented as such.

## Requirements *(mandatory)*

- **FR-001**: Every internal URL in the built site MUST resolve under the configured base.
- **FR-002**: The rewrite MUST be idempotent and MUST NOT touch scheme-relative or absolute URLs.
- **FR-003**: `COREX_DOCS_BASE` MUST remain a real override.
- **FR-004**: A test MUST fail when any internal URL is missing the base, and MUST assert against
  the **built output** — the source is not where the bug lives.
- **FR-005**: The admin docs fallback MUST resolve to the published site, not to Markdown source.

## Success Criteria *(mandatory)*

- **SC-001**: The link test reports 29 failing files before the fix and zero after.
- **SC-002**: The seven reported URLs return 200 once deployed.
- **SC-003**: The site still builds 286 pages.
- **SC-004**: `DocsUrl` resolves `/guides/media/` to `https://mustafashaaban.github.io/corex/guides/media/`.

## Out of scope

- Rewriting the 84 links by hand — the rewrite is one place and survives the next author.
- Checking **external** links. A different job with different failure modes (rate limits, flaky
  hosts), and not the defect being fixed.
