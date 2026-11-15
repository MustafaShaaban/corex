# Implementation Plan: Abilities that mean something

**Branch**: `spec/117-abilities` | **Date**: 2026-10-23 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/117-abilities/spec.md`

## Summary

Every CoreX screen, route and save action asks for the CoreX ability that names it, read from one map
(`Corex\Access\ScreenAbilities`, corex-core). Site administrators keep every ability because
`AbilityCompatibility` stops applying role effects to anyone who can manage options, and `AccessPolicy` refuses a
Deny on a role that can. A new ability, `corex_view_screens`, opens four screens read-only (`ScreenAccess` decides
the mode; `AdminPage::open()` prints the chip and the sentence; a small script marks the controls). The Access
screen gets a grid of resolved states (`AccessMatrix` resolves; `AccessWorkspace.js` draws), CoreX-ability counts,
and one history (`AccessHistory`) built from the activity stream's access area and the counted refusals. One lookup,
`Corex\Access\AbilityNames`, gives every ability, area and risk its translated name.

## Technical Context

**Language/Version**: PHP 8.3, JavaScript (ES modules built by `@wordpress/scripts`)

**Primary Dependencies**: WordPress 7.0+ (capabilities, admin menu, REST API), CoreX container, React through
`@wordpress/element`

**Storage**: existing tables only (role ability grants, access requests, activity) and the access audit option;
no schema change

**Testing**: Pest unit and integration (`-d memory_limit=512M`), Jest, Playwright (`security-access.spec.js`,
`access-request.spec.js`, a new `abilities.spec.js`)

**Target Platform**: WordPress admin, single site and multisite

**Project Type**: WordPress plugin monorepo (corex-core, corex-config, corex-forms, add-ons)

**Performance Goals**: one extra role-capability lookup per role on the Access screen; the history reads at most
1,000 activity rows a page view

**Constraints**: no administrator loses access; public repository wording says what a change does

**Scale/Scope**: 16 abilities, 15 screens, about 90 routes

## Constitution Check

- [x] **I. Theme is a skin** — no theme change.
- [x] **II. Plugins boot themselves** — all changes are in plugins and add-ons.
- [x] **III. Thin controllers, fat services** — controllers ask `AbilityCheck`; history and resolution live in
  `AccessHistory` and `AccessMatrix`.
- [x] **IV. Everything injected** — new services are container-built; `ScreenAbilities` and `AbilityNames` are
  static pure lookups with no state or I/O, the same shape as `CorexScreens` (justified there).
- [x] **V. Runtime tokens** — new CSS uses the admin tokens only.
- [x] **VI. Conditional assets** — the read-only script loads on CoreX screens only.
- [x] **VII. Declarative security** — admin screens keep `AdminGuard`; routes keep their permission callbacks with
  nonces; every check names a CoreX ability.
- [x] **VIII. RTL-first** — logical properties; the grid's sticky column uses `inset-inline-start`.
- [x] **IX. No optional dep is hard** — none touched.
- [x] **X. Spec is source of truth** — this plan traces to spec 117.
- [x] **Guard Gate + Definition of Done** acknowledged.

## Project Structure

### Documentation (this feature)

```text
specs/117-abilities/
├── spec.md
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── checklists/requirements.md
└── tasks.md
```

### Source Code (repository root)

```text
plugins/corex-core/src/Access/
├── CorexAbility.php            # VIEW_SCREENS constant
├── CorexAbilityCatalog.php     # the sixteenth ability
├── AbilityNames.php            # NEW: translated names, descriptions, areas, risks
├── ScreenAbilities.php         # NEW: screen slug → section, ability, read-only flag
├── AbilityCheck.php            # NEW: current_user_can + recorded refusal
├── SiteAdministration.php      # NEW: interface, "does this role administer the site"
└── AccessPolicy.php            # Deny refused on a site-administrator role
plugins/corex-core/src/Admin/AdminPage.php   # read-only chip/sentence, denied copy, rail, request map
plugins/corex-config/src/Access/
├── AbilityCompatibility.php    # administrators skip role effects
├── WpSiteAdministration.php    # NEW
├── ScreenAccess.php            # NEW: manage / view / none, menu capability
├── AccessMatrix.php            # cell states, counts, your permissions
├── AccessHistory.php           # NEW: the audit tab's entries
├── AccessScreen.php            # overview, matrix tab, audit tab, denied tab copy
├── AccessService.php           # refused change recorded, translated reasons, expired request recorded
├── AccessAuditLog.php          # listens to corex_access_refused
├── AccessWorkspace.js, accessState.js, AccessMatrixGrid.js (NEW)
plugins/corex-config/assets/read-only.js (NEW), access.css, corex-admin-shell.css (chip)
every screen and controller named in spec FR-002
```

## Client impact

Muva and Perego: administrators see no change. Earlier grants start opening their screens; owners review the
grants list and any listed Deny on Administrator first. Editors who used Blog Pro since v0.47.0 need "Manage Blog
Pro" allowed. A client repository carrying copies of CoreX's tests takes the updated access tests.

## Order of work

1. Core lookups (names, screen map, check, view ability) and the administrator rule.
2. Every screen and route on its ability; rail; denied page copy.
3. Read-only mode for the view ability.
4. Email Studio and the mail delivery fields.
5. Access screen: matrix grid, overview, audit.
6. Tests, docs, CHANGELOG, PROGRESS, DECISIONS #420–#427.

## Complexity Tracking

None.
