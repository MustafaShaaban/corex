# Implementation Plan: Design Language System

**Branch**: `feature/051-design-language-system` | **Date**: 2026-06-14 | **Spec**: [spec.md](./spec.md)

## Summary

Formalize Corex's UI into a documented **Design Language System** living in `corex-ui` (no new `corex-dls`): a pure
`DesignSystemCatalog` organizes the elements into Components / Blocks / Patterns / Templates / Guidelines and is
**drift-checked** against the real `corex/*` blocks; the component layer gains `corex/alert` + `corex/badge`
(server-rendered, token-only, accessible, RTL, following the established block pattern); and the system is documented
in the docs app. Reuses 027/029/033/035 (the block library), 004 (block engine), 033 (tokens).

## Constitution Check

PASS — V (token-only new blocks), VI (dynamic/conditional blocks), VII (escaped, no secret), VIII (RTL/logical),
III (pure catalog + thin renderers), X (traces to 051; reuses 004/027/033). Guard Gate acknowledged.

## Project Structure

```text
addons/corex-ui/src/
├── DesignSystemCatalog.php          # NEW — pure: entries() by category; blockNames() drift-checked vs disk
└── Blocks/
    ├── alert/{block.json,index.js,style.scss} + AlertRenderer.php   # NEW
    └── badge/{block.json,index.js,style.scss} + BadgeRenderer.php   # NEW
docs-app/.../guides/design-system.md   # NEW — the taxonomy + catalog + guidelines
tests/Unit/Ui/ (Pest)                  # NEW — DesignSystemCatalog (drift), AlertRenderer, BadgeRenderer
```

**Structure Decision**: The DLS home is `corex-ui` (US4). The catalog is a pure declared registry, drift-tested
against the on-disk `corex/*` block.json (no runtime scan, no invented entry). The new alert/badge blocks follow the
spec-004/027 dynamic `BlockRenderer` pattern (auto-discovered, token-only, accessible).

## Complexity Tracking

> No violations.
