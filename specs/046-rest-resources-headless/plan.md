# Implementation Plan: REST resources & headless

**Branch**: `feature/046-rest-resources-headless` | **Date**: 2026-06-14 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/046-rest-resources-headless/spec.md`

## Summary

Make REST/headless Laravel-like but WP-native by **reusing** the spec-003 generator engine, the spec-005
middleware, and the spec-043 envelope. `make:api-resource <Name>` scaffolds a complete secured resource
(controller + routes + request + response resource + test) via a pure multi-file `ApiResourceScaffolder` (modelled
on `BlockScaffolder`). A pure `RouteDescriptor` + formatter powers `routes:list` (reading WP's registered routes
filtered to the Corex/app namespaces at the runtime boundary), and an `ApiDocsGenerator` (descriptors → OpenAPI 3)
powers `api:docs`. Headless mode is the documented, envelope-shaped, cap-gated read surface (content/CPTs/forms/
options/menus) authenticated by nonce / application password — **JWT/OAuth out of scope**. Every generated/exposed
route declares the spec-005 middleware + a permission callback and answers with the envelope; no secret leaks.

## Technical Context

**Language/Version**: PHP 8.3. Generator engine + readers are pure; WP-CLI commands are `class_exists('WP_CLI')`-gated.

**Primary Dependencies**: existing only — spec-003 `GeneratorEngine`/`StubRenderer`/`Naming`/`MakeCommand`, spec-005
`Pipeline`/`MiddlewareResolver`, spec-002/030 Models/Repositories/Resources, spec-043 `ResponseEnvelope`, and WP's
own REST server (`rest_get_server()`) for route discovery. No new runtime/build dependency.

**Storage**: none new — headless exposes existing data.

**Testing**: Pest — the `ApiResourceScaffolder` (render+write, headless, incl. `php -l` of the generated controller),
the `RouteDescriptor`/formatter, the `ApiDocsGenerator` (OpenAPI shape). Live route discovery + headless auth are
env-gated.

**Target Platform**: WP-CLI (generators/list/docs) + REST (generated resources, headless).

**Project Type**: WordPress framework monorepo — `packages/cli` (generators/commands) + corex-core (route reader,
docs emitter, headless surface).

**Performance Goals**: generation is one-shot; route reading is on-demand admin/CLI; no per-request front-end cost.

**Constraints**: generated controllers thin + middleware-declared + permission-gated; resources expose only declared
fields; **no secret** in any response or generated doc; WP-CLI/headless fully optional (Principle IX).

**Scale/Scope**: 1 multi-file scaffolder + 5 stubs + `make:api-resource` wiring; a route reader + `routes:list`; an
OpenAPI emitter + `api:docs`; a documented headless surface + auth doc.

## Constitution Check

*GATE: pass before Phase 0; re-check after Phase 1.* (Corex Constitution v1.2.1.)

- [x] **I. Theme is a skin** — N/A (engine/CLI/REST).
- [x] **II. Plugins boot themselves** — PASS. Route reader/headless register on REST/CLI hooks; no theme dep.
- [x] **III. Thin controllers, fat services** — PASS. The **generated** controller is thin (route→validate→service→
  resource→envelope); the scaffolder/reader/emitter are pure; WP-CLI commands are thin gated boundaries.
- [x] **IV. Everything injected** — PASS. Scaffolder/readers are container-wired; descriptors are value objects.
- [x] **V. Runtime tokens** — N/A (no styling).
- [x] **VI. Conditional assets** — N/A (no front-end assets).
- [x] **VII. Declarative security** — PASS. Generated routes **declare** the spec-005 middleware + a permission
  callback (never public for a writing route); resources expose only declared fields; **no secret** in any response
  or the OpenAPI doc (FR-002/FR-007/FR-011).
- [x] **VIII. RTL-first** — N/A.
- [x] **IX. No optional dep is hard** — PASS. WP-CLI gated; headless optional; the framework runs fully without
  either; no new hard dependency.
- [x] **X. Spec is source of truth** — PASS. Traces to spec 046; reuses 003/005/002/030/043 without re-speccing.
- [x] **Guard Gate + DoD** — clean-code (pure engine, thin command), wp-guard (the generated route/permission/
  escaping + the reader), test-guard (Pest, incl. generated-`php -l`), docs-guard (the headless + api:docs guides);
  PROGRESS/DECISIONS; NEXT STEP.

**Result: PASS — no violations.**

## Project Structure

```text
packages/cli/
├── src/Generators/
│   ├── ApiResourceScaffolder.php       # NEW — pure multi-file scaffolder (controller+routes+request+resource+test)
│   └── ApiResourceScaffoldResult.php   # NEW — created/skipped/error result (like BlockScaffoldResult)
├── stubs/api-resource/
│   ├── controller.stub · routes.stub · request.stub · resource.stub · test.stub   # NEW
├── src/Routes/
│   ├── RouteDescriptor.php             # NEW — pure VO: method, path, permission, namespace
│   └── RouteList.php                   # NEW — pure: descriptors → readable lines
├── src/Commands/MakeCommand.php        # CHANGE — wire `make:api-resource`
├── src/Commands/RoutesCommand.php      # NEW — `routes:list` (WP-CLI-gated; reads rest_get_server() at the boundary)
└── src/Docs/ApiDocsGenerator.php       # NEW — pure: descriptors + envelope schema → OpenAPI 3 JSON; `api:docs` command

plugins/corex-core/...                  # headless surface = the documented exposed read routes (envelope, cap-gated)
docs-app/.../guides/(rest|headless).md  # NEW — make:api-resource, routes:list, api:docs, headless + auth
tests/Unit/Cli/ (Pest)                  # NEW — scaffolder, RouteDescriptor/list, ApiDocsGenerator
```

**Structure Decision**: Follow the **spec-003 pattern** exactly — a **pure** generator/reader/emitter (render, plan,
shape) with a thin `class_exists('WP_CLI')`-gated command layer, so everything is unit-tested headlessly. The
`ApiResourceScaffolder` mirrors `BlockScaffolder` (render-all-before-write). Route discovery reads WP's own
`rest_get_server()->get_routes()` at the command boundary (runtime), filtered to the Corex/app namespaces, into pure
descriptors. Headless mode is the **documented exposed surface** over existing data — no new store.

## Complexity Tracking

> No Constitution Check violations — section intentionally empty.
