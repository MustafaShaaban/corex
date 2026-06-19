# Implementation Plan: Design Roadmap and Inventory Integration

**Branch**: `docs/056-roadmap-refresh` | **Date**: 2026-06-19 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/056-design-roadmap-inventory/spec.md`

## Summary

Replace the outdated spec-history roadmap with a milestone-based product and engineering roadmap, establish a lightweight and separate design-planning area, and define the approval-to-handoff-to-spec contract. The implementation is documentation-only: it updates durable planning surfaces, validates their responsibilities and vocabulary, and changes no product code, runtime behavior, release history, or architectural decisions.

## Technical Context

**Language/Version**: Markdown (CommonMark/GitHub-flavored Markdown)

**Primary Dependencies**: Git, Spec Kit planning conventions, existing Corex continuity documents

**Storage**: Version-controlled text files; no runtime or database storage

**Testing**: Requirement-presence checks, path/reference checks, `git diff --check`, scope audit, `docs-guard`

**Target Platform**: Repository documentation consumed by maintainers and coding agents

**Project Type**: Documentation governance and planning integration

**Performance Goals**: A new maintainer can identify current state and the next bounded action from the root roadmap and progress entry in one reading session

**Constraints**: Planning-only change; no product code; no release or architecture-history mutation; one engineering roadmap; only the next three specs listed; external design does not authorize implementation

**Scale/Scope**: Root roadmap, immediate progress entry, three lightweight design-planning files, one feature spec and its planning artifacts

## Constitution Check

*GATE: Passed before Phase 0 research and re-checked after Phase 1 design against Corex Constitution v1.2.1.*

- [x] **I. Theme is a skin - N/A**: no theme or presentation implementation changes.
- [x] **II. Plugins boot themselves - N/A**: no plugin boot or runtime changes.
- [x] **III. Thin controllers, fat services - N/A**: no production architecture changes.
- [x] **IV. Everything injected - N/A**: no dependencies or runtime objects are introduced.
- [x] **V. Runtime tokens - N/A**: token work is a future milestone and explicitly excluded here.
- [x] **VI. Conditional assets - N/A**: no assets are introduced.
- [x] **VII. Declarative security - N/A**: no route, admin, input, or output behavior changes.
- [x] **VIII. RTL-first - PASS**: design handoff requirements explicitly require RTL and mixed-script behavior before implementation.
- [x] **IX. No optional dependency is hard - PASS**: the roadmap preserves optional WooCommerce gating and adds no dependency.
- [x] **X. Spec is source of truth - PASS**: Spec 056 defines and bounds this planning-only change; later design areas require handoffs and their own specs.
- [x] **Guard Gate + Definition of Done - PASS**: documentation guard, formatting, scope, and requirement checks are required; `PROGRESS.md` is updated; no product tests are falsely claimed.

## Project Structure

### Documentation (this feature)

```text
specs/056-design-roadmap-inventory/
|-- spec.md
|-- plan.md
|-- research.md
|-- data-model.md
|-- quickstart.md
|-- contracts/
|   `-- design-handoff-contract.md
|-- checklists/
|   `-- requirements.md
`-- tasks.md
```

### Planning surfaces (repository root)

```text
ROADMAP.md
PROGRESS.md
design/
|-- ROADMAP.md
|-- INVENTORY.md
`-- handoffs/
    `-- README.md
```

**Structure Decision**: Keep the root roadmap as the single product/engineering roadmap. Keep design exploration and approval status in a small `design/` subtree. Keep detailed delivery requirements in the numbered Spec Kit directory. No product, package, add-on, theme, test, changelog, or decision file is part of the implementation scope.

## Phase 0 - Research

Research resolves planning-boundary and status-semantics questions in [research.md](research.md). No external technology research is required because this feature is governed by existing repository conventions and the user-approved roadmap structure.

## Phase 1 - Design and Contracts

- [data-model.md](data-model.md) defines the planning entities, allowed states, relationships, and lifecycle transitions.
- [contracts/design-handoff-contract.md](contracts/design-handoff-contract.md) defines the minimum evidence required before an approved external design can become an engineering spec.
- [quickstart.md](quickstart.md) defines the end-to-end validation sequence for this documentation-only feature.
- The managed Spec Kit context in `CLAUDE.md` points to this plan after the planning artifacts exist.

## Post-Design Constitution Re-check

All gates remain PASS or N/A. The design introduces no runtime behavior, optional dependency, token value, theme logic, asset, security surface, or product implementation. The handoff contract strengthens Principle VIII and Principle X by making RTL/accessibility coverage and spec-before-code explicit.

## Complexity Tracking

No constitution violations or complexity exceptions are required.
