# Implementation Plan: Stable Client Readiness

**Branch**: `feature/055-stable-client-readiness` | **Date**: 2026-06-18 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/055-stable-client-readiness/spec.md`

## Summary

Prepare Corex for the first two real company-identity websites by closing framework-level readiness risks before
client branding begins. The plan is stability-first and native-first: prove add-on runtime gating, WooCommerce
dependency gating, release metadata consistency, CI/security posture, make:site isolation, deployment profile
coverage, component coverage, Free/Core vs Pro boundaries, and multi-agent workflow safety.

The technical approach is to add small, testable readiness surfaces rather than product features: a lower-level
runtime provider-resolution contract used by `Boot` before add-on providers register behavior, metadata and
scaffold validation checks under the existing CLI/release/testing patterns, docs-backed governance hardening, and a
component/boundary matrix that classifies the minimum company-site needs without starting the final Corex visual
redesign.

## Technical Context

**Language/Version**: PHP 8.3+ for runtime gates, CLI validators, and Pest tests; JavaScript/Node 20+ for existing
workspace builds and Jest; YAML for GitHub Actions; Markdown for governance, deployment, and readiness artifacts.

**Primary Dependencies**: WordPress 7.0+ plugin/theme runtime; WP-CLI for `wp corex`; Composer/Pest/Brain Monkey;
`@wordpress/scripts`, npm workspaces, Jest, Playwright, and `@wordpress/env`; GitHub Actions; existing Corex PSR-11
container, config repository, feature flags, add-on registry UI, WooKitGate, VersionPlan, ComplianceCheck, and
SiteScaffolder.

**Storage**: No new persistent storage planned. Runtime state reads existing Corex config/feature flags and
WordPress/plugin availability. Readiness reports are generated artifacts or command output unless tasks later justify
a stored option.

**Testing**: Pest unit tests for provider-resolution/gating, Woo gating, metadata validation, make:site validation,
Free/Core boundaries, and component matrix rules; existing integration tests where WordPress is available; Jest for
changed JS surfaces if any; Playwright E2E remains environment-gated and is reported honestly when unavailable.

**Target Platform**: WordPress 7.0+ with PHP 8.3+, FSE block themes, WAMP for local solo checks, wp-env/Docker for
team/CI parity where available, and GitHub Actions for CI/docs/e2e workflows.

**Project Type**: WordPress framework monorepo: core plugins, optional add-ons, CLI package, theme, docs handbook,
docs-app, and Spec Kit governance files.

**Performance Goals**: Disabled add-ons register no unsafe hooks/routes/REST endpoints/blocks/admin menus/assets/
migrations/tables/cron jobs. Active add-ons preserve current behavior. Readiness checks run fast enough for local
pre-PR use, with browser/wp-env checks split into the existing heavier E2E path.

**Constraints**: Spec-first; no framework implementation before `tasks.md`; no full visual redesign; no client
branding in framework folders; no hard optional dependencies; token-only/logical CSS for any UI; security-critical
basics stay Free/Core; environment-gated checks are reported as gated, not failed.

**Scale/Scope**: First-party Corex runtime surfaces across 3 core plugins, the theme, 10 first-party add-ons/kits,
root/package metadata, 3 GitHub workflows, make:site scaffolds, deployment docs, and the minimum component needs for
two company-identity websites.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.* Corex Constitution **v1.2.1**.

- [x] **I. Theme is a skin** - PASS. The theme remains presentation only; make:site token validation verifies
  client branding stays in generated client theme/plugin files, not Corex framework logic.
- [x] **II. Plugins boot themselves** - PASS. Core plugins continue to self-init on `plugins_loaded`; runtime
  provider resolution gates optional add-ons before unsafe behavior is registered.
- [x] **III. Thin controllers, fat services** - PASS. Any new routes/commands must delegate to pure services/checks;
  repositories remain the only data access boundary.
- [x] **IV. Everything injected** - PASS. Runtime gates and validators are pure services resolved through the
  container or called as pure collaborators; no dependency construction inside behavior methods.
- [x] **V. Runtime tokens** - PASS/N/A. No visual redesign. Any small UI/readiness surface consumes existing
  `theme.json` CSS variables and `brand.json` runtime strategy.
- [x] **VI. Conditional assets** - PASS. Disabled add-on checks explicitly cover assets and block assets; no new
  global CSS/JS library is planned.
- [x] **VII. Declarative security** - PASS. New routes, if any, must declare middleware; admin screens use
  `AdminGuard`; CI/security governance is part of this readiness scope.
- [x] **VIII. RTL-first** - PASS. Component coverage and any UI changes require logical CSS and RTL notes.
- [x] **IX. No optional dep is hard** - PASS. WooCommerce gating is a primary success criterion; other optional
  integrations remain detected behind interfaces or gates.
- [x] **X. Spec is source of truth** - PASS. This plan traces to spec 055 and does not authorize implementation
  until `/speckit-tasks` creates ordered tasks.
- [x] **Guard Gate + Definition of Done** - acknowledged. Per task: tests, docs, i18n/RTL/WCAG where relevant,
  PROGRESS/DECISIONS updates, and relevant guards (`clean-code-guard`, `wp-guard`, `test-guard`, `docs-guard`).

**Result: PASS - no violations.** Complexity Tracking not required.

## Project Structure

### Documentation (this feature)

```text
specs/055-stable-client-readiness/
|-- plan.md
|-- research.md
|-- data-model.md
|-- quickstart.md
|-- contracts/
|   |-- runtime-gating.md
|   |-- metadata-consistency.md
|   |-- make-site-validation.md
|   |-- readiness-report.md
|   `-- component-boundaries.md
|-- checklists/
|   `-- requirements.md
`-- tasks.md                 # Created later by /speckit-tasks, not by this command
```

### Source Code (repository root)

```text
plugins/corex-core/
|-- src/Boot.php                         # Provider list must become gated before optional providers boot
|-- src/Foundation/                      # Runtime provider resolver/gate belongs below admin UI concerns
|-- src/Support/Config/FeatureFlags.php  # Existing feature flag source for runtime state
`-- src/Health/                         # Candidate home for readiness/report probes if tasks choose health integration

plugins/corex-config/
|-- src/Addons/                          # Existing Add-ons admin state/registry, adapted to runtime authority
`-- src/Insights/                        # Existing readiness-style diagnostics patterns

addons/
|-- corex-*/corex-*.php                  # First-party add-on headers and activation surfaces
`-- corex-kit-woo/src/WooKitGate.php     # Existing pure Woo dependency gate, retained and strengthened

packages/cli/
|-- src/Commands/                        # Candidate command home for readiness/check commands
|-- src/Release/VersionPlan.php          # Existing version-stamping model; metadata checks extend this area
|-- src/Release/ComplianceCheck.php      # Existing compliance pattern for generated/client-site safety
`-- src/Site/SiteScaffolder.php          # make:site validation target

.github/
|-- workflows/ci.yml
|-- workflows/docs.yml
|-- workflows/e2e.yml
`-- CODEOWNERS                         # Candidate missing governance control

docs/
|-- en/04-team-workflow/
|-- en/05-deployment/
`-- en/06-cookbooks/

docs-app/src/content/docs/
|-- design-system/
`-- guides/

tests/
|-- Unit/Foundation/                     # Provider gating tests
|-- Unit/Woo/                            # Woo dependency gating tests
|-- Unit/Release/                        # Metadata/readiness validators
|-- Unit/Cli/                            # make:site validation and command tests
|-- Integration/                         # WordPress-aware checks when environment is available
`-- e2e/                                # Playwright smoke/console sweep, environment-gated
```

**Structure Decision**: Runtime gating belongs below the admin add-on screen. `Boot` currently passes every
first-party provider directly into `Application`; spec 055 should introduce a lower-level provider-resolution
contract that can decide active/inactive/dependency-missing state before providers register hooks. The existing
`corex-config` Add-ons UI can display and mutate state, but it must not be the only runtime authority.

## Complexity Tracking

> No constitution violations - section intentionally empty.
