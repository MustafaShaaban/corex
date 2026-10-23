# Tasks: Abilities that mean something

**Input**: `specs/117-abilities/spec.md`, `plan.md`, `research.md`, `data-model.md`

Tests are written with each story (constitution: mandatory). `[P]` = can run in parallel with its neighbours.

## Phase 1: Setup

- [x] T001 Branch `spec/117-abilities` from `origin/main`; a WordPress of its own on port 8917, database
  `corex_w117`.
- [x] T002 Spec, plan, research, data model, quickstart and checklist in `specs/117-abilities/`.

## Phase 2: Foundational (blocks every story)

- [x] T003 `Corex\Access\AbilityNames` (corex-core): translated name and description of every shipped ability,
  area names, risk words; fallback to the catalogue text. Unit test `AbilityNamesTest`.
- [x] T004 `CorexAbility::VIEW_SCREENS` and its catalogue entry, implied by `corex_manage_admin`; update
  `CorexAbilityCatalogTest`, `RequestableAbilitiesTest`.
- [x] T005 `Corex\Access\ScreenAbilities` (corex-core): the screen map of `data-model.md`; `AdminPage::requestAbilityFor()`
  reads it. Unit test `ScreenAbilitiesTest`.
- [x] T006 `Corex\Access\AbilityCheck` (corex-core): `allows()` fires `corex_access_refused` on a refusal;
  `AccessAuditLog` records kind `action`. Unit tests.
- [x] T007 `Corex\Access\SiteAdministration` interface and `WpSiteAdministration`; bound in `ConfigServiceProvider`.
- [x] T008 `Corex\Config\Access\ScreenAccess`: `mode()` (manage, view, none) and `menuCapability()`. Unit test.

## Phase 3: User Story 1 — every screen opens for its ability (P1)

- [x] T009 [US1] `AbilityCompatibility`: a site administrator gets every ability and no role effect. Unit test.
- [x] T010 [US1] `AccessPolicy::preview()` refuses a Deny on a role that administers the site; `AccessService`
  passes it. Unit tests.
- [x] T011 [US1] Menus and render checks on their ability: Overview, Settings, Add-ons, Forms & Flows, Email
  Studio, Operations & Security, Access, Blog Pro (`BlogProPermissions::BASE`), Insights, Setup Wizard.
- [x] T012 [US1] Routes and handlers: access, flows (gateway and middleware), email studio, insights, security
  login protection, setup wizard, captcha test, activity, settings save, add-ons toggle, kit notice, operations
  mode (Production/Maintenance also dangerous), preview link, data export and import dry run handlers.
- [x] T013 [US1] Retention forms and handlers: save needs settings, prune needs dangerous actions.
- [x] T014 [US1] Coming soon toolbar link, the four Guides, the rail under its real parent, denied page copy.
- [x] T015 [US1] Integration test `ScreenAbilitiesAccessTest`: an editor allowed one ability opens that screen and
  its routes, and is refused the others; an administrator with a stored Deny keeps every screen; the mode switch.
- [x] T016 [US1] Update the tests that asserted `manage_options` on these screens and routes.

## Phase 4: User Story 5 — names (P2; before 2 and 3, which show them)

- [x] T017 [US5] Matrix, overview, presenter, pending requests, denied page and refusal messages read
  `AbilityNames`; keys only as tooltips. Unit tests.

## Phase 5: User Story 2 — the matrix grid (P2)

- [x] T018 [US2] `AccessMatrix::editableCorexMatrix()` sends `state`, `administers`, `ignoredDeny`, the area name
  and description. Unit test.
- [x] T019 [US2] `AccessMatrixGrid.js` and the reworked `AccessWorkspace.js` (grid first, Edit per role opens the
  editor, back to the grid); `access.css` sticky first column and frame. Jest tests.
- [x] T020 [US2] Remove the WordPress capability grid, the legend for it and the matrix tab's audit list; write the
  five-state legend.

## Phase 6: User Story 3 — the overview (P2)

- [x] T021 [US3] Role cards from resolved states, groups card of CoreX abilities, "Your permissions" with how,
  "Added by a plugin or the site", requirements card from the screen map, the ignored-Deny list. Unit and
  integration tests.

## Phase 7: User Story 4 — the audit (P2)

- [x] T022 [US4] `AccessService` records `access.role.refused` and `access.request.expired`. Unit tests.
- [x] T023 [US4] `AccessHistory` and the Audit log tab (day groups, 25 a page, kind and person filters, empty
  state). Unit and integration tests.

## Phase 8: User Story 6 — read-only screens (P2)

- [x] T024 [US6] `AdminPage::open()` read-only chip, sentence and request; `assets/read-only.js`; shell CSS.
- [x] T025 [US6] Overview, Settings, Operations & Security and Insights open read-only for the view ability; their
  read routes accept it, writes refuse and record. Integration test and Playwright `abilities.spec.js`.

## Phase 9: User Story 7 — Email Studio (P2)

- [x] T026 [US7] Email Studio on `corex_manage_email`; "Configure delivery" only for site administrators; Settings
  delivery fields `site_admin`. Integration tests (screen, route, notification link, settings save).

## Phase 10: Polish

- [x] T027 Docs: the Access & Abilities guide and the Operations & Security guide's mail section say what each
  screen needs.
- [x] T028 CHANGELOG `[Unreleased]` with Client impact; PROGRESS "In flight"; DECISIONS #420–#427.
- [ ] T029 Token inventory regenerated; Jest, Pest unit and integration, the touched Playwright specs green.
- [ ] T030 Guards: clean-code, wp, test, docs on the diff.

## Dependencies

Phase 2 before everything; US1 before US6 and US7; US5 before US2 and US3.
