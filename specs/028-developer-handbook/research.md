# Research: Developer & operations handbook (028)

## R1 — Relationship to docs-app (the central decision)

**Decision**: **Split by audience.** `docs-app/` (Astro + Starlight, spec 022) remains the published
product/API documentation site and the home of the **generated** class reference. The new in-repo `/docs`
handbook holds the contributor/operations content docs-app lacks (multi-OS setup, Docker, deployment, CI/CD,
team workflow, cookbooks) and **links** to docs-app for architecture and the class reference.

**Rationale**: A second copy of getting-started/architecture/reference would drift — the exact failure
docs-guard exists to prevent. Splitting by *audience* (product-docs reader vs project-operator/contributor)
gives each a home with zero content overlap. In-repo Markdown also renders on GitHub without a build, which is
where operators/contributors actually read ops docs.

**Alternatives considered**: extend docs-app with everything (rejected — loses GitHub-native Markdown; Mermaid
needs a Starlight plugin; bigger build surface); a standalone `/docs` that *replaces* docs-app incl. a
hand-written class reference (rejected — duplicates a released site and re-introduces reference drift,
contradicting DECISIONS #50).

## R2 — The class reference stays generated (not hand-written)

**Decision**: Do **not** author per-class pages by hand. The class reference is produced by `wp corex
docs:generate` (spec 019) into docs-app; the handbook links to it. Where richer per-class guidance is genuinely
useful (worked examples, pitfalls), it lives as a **cookbook** page (task-oriented), not a parallel reference.

**Rationale**: DECISIONS #50 settled this — "hand-writing ~190 class pages would rot immediately." Honoring it
keeps one source of truth for the API surface.

## R3 — Docker dev stack + the monorepo mapping

**Decision**: Document a dev `docker compose` stack (php-fpm, a web server, a database, a cache, a mail
catcher) where the monorepo `plugins/`, `theme/`, and `addons/` are **bind-mounted into `wp-content/`** (the
container analogue of the junctions used on Windows today — DECISIONS #18 / PROGRESS "folder-rename gotcha").
WordPress core stays a container/volume concern, never committed. A multi-stage Dockerfile builds the lean
production image (strip dev tooling/source maps, per FRAMEWORK §19 build→package).

**Rationale**: The monorepo-into-wp-content mapping is the part teams get wrong; bind-mounts reproduce the
existing junction strategy so the same single source tree serves dev. Cache/mail services are **dev
conveniences**, documented as optional — never added as framework runtime dependencies (FR-004 guardrail,
Principle IX).

**Alternatives considered**: copying the monorepo into the image for dev (rejected — breaks live editing,
diverges from the junction model); committing WP core (rejected — constitution Environment Gate / gitignore).

## R4 — Diagrams: Mermaid that renders on GitHub

**Decision**: All diagrams are fenced ` ```mermaid ` blocks (architecture-link pages, request lifecycle via
`sequenceDiagram`, Docker dev/prod topology, each deploy topology, CI/CD flow, the Spec-Kit workflow).

**Rationale**: GitHub renders Mermaid natively — no image pipeline, no external tool, diagrams version with the
text. Matches the "no new build tool" guardrail.

## R5 — i18n scaffolding for the future Arabic phase

**Decision**: Author `docs/en/` now; scaffold `docs/ar/` as a file-for-file placeholder mirror (front-matter +
`> TODO: translation pending`). Maintain `_glossary.md` (term → plain English; Arabic column later) and
`_translation-memory.md` (locked English terms). **Code identifiers, inline code, env vars, hook names, CLI
flags, file paths are never translated.**

**Rationale**: Unblocks translation later without restructuring; the locked-term list prevents a translator
from breaking code references. Note: docs-app uses Starlight's own locale system — the two i18n mechanisms are
independent and that is fine (different surfaces).

**Alternatives considered**: defer all i18n structure (rejected — restructuring later is costly; the mirror is
cheap now).
