# Implementation Plan: Health-check, versioning, i18n & OSS hygiene (036)
**Branch**: `feature/036-health-hygiene` | **Date**: 2026-06-12 | **Spec**: [spec.md](./spec.md)

## Summary
Two pure engines plus hygiene. **Health:** `HealthProbe` (interface) + concrete probes + a pure `HealthReport`
aggregator (overall = worst status); registered into WordPress Site Health (`site_status_tests`) by a
`HealthModule`, and surfaced by a thin `wp corex doctor` command (non-zero exit on critical). **Versioning:** a
pure `VersionPlan` computes per-file header/constant edits for a target semver; a thin `wp corex version <x>
[--dry-run]` applies/previews. **i18n:** a `composer i18n:pot` step + a loaded `languages/` dir + text-domain
consistency. **Hygiene:** LICENSE, CODE_OF_CONDUCT, SECURITY, .editorconfig, GitHub templates.

## Technical Context
PHP 8.3; Pest (+ Brain Monkey only where a probe reads a WP value, injected). CLI commands behind the existing
`class_exists('WP_CLI')` gate (spec 003 precedent; reset spec 025). `.pot` via wp-cli i18n (env-gated).

## Constitution Check (v1.2.1)
- [x] III/IV — `HealthReport`/`VersionPlan` pure; probes own their checks; CLI + Site Health are thin boundaries.
- [x] VII — `wp corex version` writes files via the same safe path as the generators; no secret; dry-run preview.
- [x] IX — health probes treat optional pieces (add-ons, brand) as advisory, never hard failures of the framework.
- [x] X — implements spec 036.
- [x] Guard Gate/DoD — clean-code + (CLI/WP) wp-guard + test-guard; Pest for both engines; docs + docs-app; OSS files.

**Gate**: PASS.

## Design
- `plugins/corex-core/src/Health/HealthProbe.php` (interface: `id(): string`, `run(): ProbeResult`).
- `.../Health/ProbeResult.php` (value: status good|recommended|critical, label, description, actions[]).
- `.../Health/HealthReport.php` (pure: takes `HealthProbe[]`; `results()`, `overall()` = worst).
- `.../Health/Probes/{PhpVersion,WpVersion,ThemeActive,BrandPresent,UploadsWritable}Probe.php` (each small; WP
  reads injected or guarded).
- `.../Health/HealthModule.php` — registers `site_status_tests` (maps each probe to a WP Site Health test).
- `packages/cli/src/Commands/DoctorCommand.php` — renders the report; exit non-zero on critical.
- `packages/cli/src/Release/VersionPlan.php` (pure) + `packages/cli/src/Commands/VersionCommand.php` (apply/dry-run).
- Wire both commands in `CliServiceProvider` (gated); register `HealthModule` in `CoreServiceProvider::boot()`.
- i18n: `composer.json` script `i18n:pot`; `languages/` dir + `load_plugin_textdomain` already on boot (verify).
- Hygiene: `LICENSE`, `CODE_OF_CONDUCT.md`, `SECURITY.md`, `.editorconfig`, `.github/ISSUE_TEMPLATE/*`,
  `.github/PULL_REQUEST_TEMPLATE.md`.

## FR → component map
| FR | Built in |
|---|---|
| FR-001 health engine | `Health/{HealthProbe,ProbeResult,HealthReport,Probes/*}` |
| FR-002 Site Health + doctor | `Health/HealthModule.php` + `Commands/DoctorCommand.php` |
| FR-003 version plan + CLI | `Release/VersionPlan.php` + `Commands/VersionCommand.php` |
| FR-004 i18n | `composer.json` `i18n:pot` + `languages/` + text-domain audit |
| FR-005 hygiene | `LICENSE`, `CODE_OF_CONDUCT.md`, `SECURITY.md`, `.editorconfig`, `.github/*` templates |
| FR-006 tested/thin | `tests/Unit/Health/*` + `tests/Unit/Release/VersionPlanTest.php`; CLI gated |

## Project Structure
```text
plugins/corex-core/src/Health/{HealthProbe,ProbeResult,HealthReport,HealthModule}.php
plugins/corex-core/src/Health/Probes/*.php
packages/cli/src/Release/VersionPlan.php
packages/cli/src/Commands/{DoctorCommand,VersionCommand}.php
tests/Unit/Health/{HealthReportTest,ProbesTest}.php · tests/Unit/Release/VersionPlanTest.php
LICENSE · CODE_OF_CONDUCT.md · SECURITY.md · .editorconfig · .github/ISSUE_TEMPLATE/* · .github/PULL_REQUEST_TEMPLATE.md
docs/en/ + docs-app (health + release + i18n)
```

## Complexity Tracking
Probes stay tiny and individually tested; the aggregator is a fold over statuses. The version planner is pure
string computation (apply is a thin writer reusing the generators' write discipline). `.pot` + Site Health UI are
env-gated.
