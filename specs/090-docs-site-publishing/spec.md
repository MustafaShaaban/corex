# Feature Specification: Publishing the documentation site

**Feature Branch**: `spec/090-docs-site-publishing`

**Created**: 2026-07-29

**Status**: Draft

**Input**: Owner direction, after v0.40.0 — enable GitHub Pages for the docs site.

## Why this spec exists

`.github/workflows/docs.yml` has regenerated the class reference and built the Astro site on every
push to `main` since spec 022, and then uploaded the result as a workflow artifact. Nothing served
it. `README.md` and `PROJECT-STATUS.md` both point readers at documentation with no public address,
which for a project being announced is the gap that undercuts the rest.

The workflow's own trailing comment described exactly what was missing. It had described it for
several releases.

### Enabling the setting alone would have published a broken site

A GitHub *project* page is served from a repository subpath — `https://mustafashaaban.github.io/corex/`
— and `astro.config.mjs` had no `site` and no `base`. Without `base`, every internal link, every
asset URL and the Pagefind search index resolve to the domain root, so the site 404s on itself. The
build also warned that `@astrojs/sitemap` was skipping, because `site` was unset.

So this is three changes, not one: the Astro config, the workflow, and the repository setting.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Somebody follows the docs link and reads the docs (Priority: P1)

**Independent Test**: the published URL serves the site, internal navigation works, and search
returns results.

### User Story 2 — The published reference cannot drift from the code (Priority: P1)

The existing guarantee must survive: the reference is regenerated from source *before* the build.

**Independent Test**: the deploy job depends on the build job, which runs `composer docs:generate`
first. No path publishes a stale reference.

### Edge Cases

- **A deployment interrupted mid-flight** would serve pages and assets from different builds. The
  workflow declares a `pages` concurrency group with `cancel-in-progress: false`.
- **Serving the same build somewhere else** — a dedicated domain, or the local WAMP vhost — must not
  require editing the config. `site` and `base` read from `COREX_DOCS_SITE` / `COREX_DOCS_BASE`.

## Requirements *(mandatory)*

- **FR-001**: The docs site MUST be published at a public URL from `main`.
- **FR-002**: `base` MUST match the path the site is served from, so internal links resolve.
- **FR-003**: `site` MUST be set, so the sitemap generates.
- **FR-004**: Both MUST be overridable by environment variable, so the same build serves elsewhere.
- **FR-005**: Publishing MUST NOT bypass the reference regeneration.
- **FR-006**: Concurrent deployments MUST NOT interleave.
- **FR-007**: The downloadable build artifact MUST remain, for self-hosting and inspection.
- **FR-008**: Every document claiming Pages is disabled MUST stop claiming it.

## Success Criteria *(mandatory)*

- **SC-001**: `https://mustafashaaban.github.io/corex/` serves the site.
- **SC-002**: The build emits **286 pages** with `/corex/`-prefixed asset and link URLs.
- **SC-003**: The build produces no sitemap warning.
- **SC-004**: `PROJECT-STATUS.md`, its docs mirror and `docs/en/05-deployment/ci-cd.md` no longer
  describe Pages as unavailable.

## Out of scope

- A custom domain. The default `github.io` address is sufficient and needs no DNS.
- Arabic locale content. The RTL scaffolding exists; translated pages are additive.
