# Quickstart & Validation: CLI Generators

Runnable scenarios. Types live in [contracts/cli-contracts.md](./contracts/cli-contracts.md) and
[data-model.md](./data-model.md).

## Prerequisites

- corex-core + data layer active (specs 001–002); WordPress at `./wp`; WP-CLI available there.
- `composer install`.

## Run the tests

```bash
composer test                 # headless unit: StubRenderer, Naming, GeneratorEngine (temp dir), generators
composer test:integration     # WP-CLI command registration on ./wp
```

## Scenario 1 — Scaffold a model (US1, SC-001)

```bash
wp corex make:model Career
# → Created: <app>/Models/Career.php
```
**Expected**: a read-only `Career` Model under the configured base path's `Models/`, every `{{ }}`
placeholder replaced, namespace/prefix from Config.

## Scenario 2 — The four generators (US2, SC-002)

```bash
wp corex make:repository CareerRepository
wp corex make:controller CareerController
wp corex make:service CareerService
```
**Expected**: each artifact lands in its conventional location with the right base class/contract and
constructor-injection shape; each generated file passes `clean-code-guard` + `wp-guard` unedited.

## Scenario 3 — Safety: idempotent + force + validation (US3, SC-003, SC-004)

```bash
wp corex make:model Career          # Created
wp corex make:model Career          # Skipped: already exists (unchanged)
wp corex make:model Career --force  # Overwritten
wp corex make:model "9bad name"     # Error: invalid name (no file written)
```
**Expected**: no overwrite without `--force`; an invalid name is rejected with a clear message and
nothing is written.

## Scenario 4 — WP-CLI optional (US4, SC-005, SC-006)

```bash
# Engine works headlessly (no WP-CLI) — proven by the unit suite:
composer test
```
**Expected**: with WP-CLI absent the framework loads with no error and registers no commands; the
`GeneratorEngine` still renders + writes (unit-tested with a temp dir).

## Acceptance → scenario map

| Success criterion | Scenario |
|---|---|
| SC-001 scaffold, no leftover tokens | 1 |
| SC-002 generated code passes guards | 2 |
| SC-003 no overwrite without --force | 3 |
| SC-004 invalid name rejected, no write | 3 |
| SC-005 WP-CLI present/absent registration | 4 |
| SC-006 engine headless-tested | 4 (`composer test`) |
