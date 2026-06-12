# Feature Specification: Health-check, versioning alignment, i18n & OSS hygiene (036)

**Created**: 2026-06-12 · **Status**: Draft · **Input**: The "Finish Corex" release-readiness bundle — make a
Corex site self-diagnose its health, keep every plugin/theme version stamped to the release, ship translation-
ready with a `.pot`, and carry the open-source hygiene files a real project needs (LICENSE, CONTRIBUTING, CODE OF
CONDUCT, SECURITY, editorconfig, GitHub templates).

## User Scenarios & Testing

### US1 — "Is my Corex site healthy?" (P1) 🎯 MVP
As a site owner, I open **Tools → Site Health** and see Corex's own checks (PHP/WordPress version, an FSE/Corex
theme active, brand tokens present, uploads writable, add-ons coherent) reported as **good / recommended /
critical** with a plain-language description and a concrete next action. From the terminal I can run
`wp corex doctor` and get the same report as readable output (and a non-zero exit when something is critical), so
it works in CI and over SSH.

**Acceptance**: a pure health engine aggregates independent probes into a report with an overall status; each
probe returns status + label + description + (optional) actions; the report registers into WordPress's Site
Health screen; `wp corex doctor` prints it and exits non-zero on any critical finding.

### US2 — One version, everywhere (P1)
As a maintainer cutting a release, I run `wp corex version 0.22.0` and every framework plugin/theme header and
`COREX_*_VERSION` constant is stamped to that version in one step — no more drift between the release tag and the
plugin headers (which today read `0.1.0`).

**Acceptance**: a pure planner computes, for a target semver, the exact per-file header/constant replacements; a
thin CLI applies them; a dry-run lists the changes without writing; an invalid version is rejected.

### US3 — Translation-ready, with a POT (P2)
As an agency shipping a multilingual site, I generate an up-to-date translation template with one command and
drop `.po`/`.mo` files into a known `languages/` directory; every user-facing string already uses the literal
`corex` text domain.

**Acceptance**: a documented `.pot` generation step (wp-cli i18n) exists; a `languages/` location is defined and
loaded; the text domain is consistent across the codebase.

### US4 — Open-source hygiene (P2)
As a contributor (or a security researcher, or an editor configuring their tools), I find the files I expect:
a real LICENSE, a CONTRIBUTING guide, a CODE_OF_CONDUCT, a SECURITY policy with a disclosure path, an
`.editorconfig` enforcing the house style, and issue/PR templates.

**Acceptance**: each file exists, is accurate to the project (GPL-2.0-or-later; the actual workflow), and is
discoverable by GitHub.

## Requirements

- **FR-001**: A **pure** `HealthReport` aggregates a set of `HealthProbe`s; each probe is independently testable
  and returns `{status: good|recommended|critical, label, description, actions[]}`. The report exposes the
  overall (worst) status. No WordPress calls in the aggregator (probes own their own checks behind injected data).
- **FR-002**: Corex registers its probes into WordPress's **Site Health** screen (`site_status_tests`) and ships a
  thin `wp corex doctor` command that renders the report and exits non-zero on a critical finding.
- **FR-003**: A **pure** `VersionPlan` computes, for a valid target semver, the set of file edits (header lines +
  `COREX_*_VERSION` constants) needed to align every framework plugin/theme to that version; an invalid version
  is rejected. A thin `wp corex version <semver> [--dry-run]` applies (or previews) the plan.
- **FR-004**: Every user-facing string uses the literal `corex` text domain; a documented `.pot` generation step
  and a loaded `languages/` directory exist.
- **FR-005**: The repo carries `LICENSE` (GPL-2.0-or-later), `CODE_OF_CONDUCT.md`, `SECURITY.md`, `.editorconfig`,
  and GitHub issue/PR templates; `CONTRIBUTING.md` stays accurate.
- **FR-006**: Both engines (`HealthReport`, `VersionPlan`) are headless **Pest**-tested; the WP-CLI command layer
  stays thin (the existing `class_exists('WP_CLI')` gate), like the other `wp corex` commands.

## Success Criteria

- **SC-001**: `wp corex doctor` reports an actionable health summary and the same checks appear in Site Health.
- **SC-002**: `wp corex version <x>` makes the plugin/theme headers match the release tag (drift eliminated).
- **SC-003**: A maintainer can produce a `.pot` and a contributor finds every expected OSS file.
- **SC-004**: The health + version engines have passing Pest tests; the full suite stays green.

## Assumptions

- "Demo content" from the original roadmap line is already delivered by spec 031 (kits seed real pages), so this
  spec does not re-add it; it focuses on health, versioning, i18n, and hygiene.
- `.pot` generation runs via wp-cli i18n in an environment that has WP-CLI (env-gated); the engine work here is
  the text-domain consistency + the documented step + the `languages/` location.
- Version stamping targets framework plugins (corex-core/blocks/forms/config), the theme, and the add-ons that
  carry headers; the planner is data-driven (a file→pattern map), so the set is easy to extend.

## Dependencies

Spec 001 (Config/providers), spec 003 (CLI command layer + the `class_exists('WP_CLI')` gate), spec 025 (reset —
the CLI thin-command precedent), spec 034 (the `Update URI`/version surface this keeps aligned).
