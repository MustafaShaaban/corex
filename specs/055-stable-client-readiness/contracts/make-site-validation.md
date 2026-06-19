# Contract: make:site Validation

## Purpose

Prove generated client sites are isolated from Corex and contain the governance/token scaffolding needed for real
company-identity websites.

## Inputs

- Client name
- Output directory
- Mode: `minimal`, `starter`, `plugin-only`, `theme-only`
- Existing Corex framework root

## Expected Scaffold

- Isolated client plugin
- Isolated client theme unless `plugin-only`
- Client namespace distinct from `Corex\`
- Client CSS prefix distinct from `--corex-`
- Client option prefix
- `AGENTS.md`, `CLAUDE.md`, `PROGRESS.md`, `DECISIONS.md`
- `specs/` and docs placeholders
- `brand.json` or documented theme token strategy
- Starter example slice only when `--starter`

## Compliance Rules

- Client-specific branding must not edit `plugins/corex-*`, `addons/corex-*`, `packages/`, or the Corex theme.
- Generated placeholders must be resolved; unresolved placeholders fail validation.
- Minimal mode omits starter example files.
- Starter mode includes removable example docs/tests/assets.

## Required Tests

- Minimal scaffold includes governance and isolated plugin/theme
- Starter scaffold includes example slice
- Prefix/namespace values are client-specific
- Framework-folder branding edits are flagged
- Existing no-overwrite behavior remains intact unless `--force`
