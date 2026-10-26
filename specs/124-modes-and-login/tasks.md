# Tasks: Modes and login protection that do what they say (spec 124)

Tests are written with each task (Pest unit and integration, Jest, Playwright). `[P]` tasks touch different files.

## Phase 1: Shared pieces

- [ ] T001 `SiteEnvironment`: `forms-paused` is a production mode for mail and a visitor mode a lock never takes away
- [ ] T002 `OperationsMode`: add `forms-paused` (order: development, staging, production, coming-soon, forms-paused,
      maintenance), its words, tone word, acknowledgement; `ModeDisclosure` describes it
- [ ] T003 `PublicSubmissionPause` (corex-core): paused by which mode, words, back-by, admin bypass

## Phase 2: US-OPS-02 Forms paused + US-VIS-04 visitor notices

- [ ] T004 `PublicSubmissionGuard` replaces `MaintenanceSubmissionGuard`: one gate, two modes, 503 + `Retry-After`
- [ ] T005 [P] Flow block: test-site notice in Development/Staging; paused panel in Forms paused (corex-forms)
- [ ] T006 Tests: every marked route refused in both modes with the same list; admin passes; flows keep their state

## Phase 3: US-OPS-01 Guardrails and noindex

- [ ] T007 `ModeGuardrails` (four rows per mode, Email from the mail gate, "Could not check")
- [ ] T008 `ModeRobots`: `noindex` and `X-Robots-Tag` in Development and Staging
- [ ] T009 Tests per mode: mail spy, form post, robots header

## Phase 4: US-OPS-05 Override phrase

- [ ] T010 `ModeChangeService`/`ProductionLaunchService`: PRODUCTION or GO LIVE ANYWAY by the snapshot at submit
- [ ] T011 `OperationsModeStore`: history row with `overridden`; `corex_production_override` action; activity event
      and sentence
- [ ] T012 CLI messages; `ReadinessController` gives the wizard the phrase that applies

## Phase 5: US-OPS-06 Messages for visitors

- [ ] T013 `VisitorMessages` store + validation, `SiteLanguages`
- [ ] T014 `MaintenanceGuard` page: messages per language, back-by, contact email, `Retry-After`, the site's own file
- [ ] T015 Card + save handler + preview handler on the Environment tab

## Phase 6: US-OPS-07 Mode cards

- [ ] T016 Radio cards with tone word, sentence, guardrail rows and confirmation per mode
- [ ] T017 `operations-mode.js`: reveal the block, Apply disabled with its reason; styles; Jest

## Phase 7: US-SEC-01, 02 Login switches and settings

- [ ] T018 Settings model and store: three switches, both shapes read, both written
- [ ] T019 Route guard, enforcer, service, `LoginAddressState`, `LoginUrl` follow their switch
- [ ] T020 Settings route: partial saves, per-field validation, proxies validated
- [ ] T021 Security Center: three switches with sentences, every field with units, summary sentence, proxy warning
- [ ] T022 Overview tile, Overview row and Operations summary show each switch; recovery command turns all off
- [ ] T023 Integration test loading the old option shapes (Muva's and Perego's) and proving the same answers

## Phase 8: US-SEC-03, 10 Lockouts

- [ ] T024 Keyed hashes with legacy matching; account recorded; fingerprints
- [ ] T025 `LoginLockoutEvent` carries no typed name or address; producer and recorder use it
- [ ] T026 Release route + Lockouts panel (account or "unknown username", ends at, Release, Release all)
- [ ] T027 One-time rewrite of stored lockout notices
- [ ] T028 Privacy test: lock out, then search every store and the captured mail

## Phase 9: US-SEC-07 Hardening by mode

- [ ] T029 Live-only checks deferred in Development/Staging; "Before going live" group; counts; notifications
- [ ] T030 Texts without "production site"; search engines check in the mode's words

## Phase 10: Close

- [ ] T031 Docs (`docs/en/03-operations/`), CHANGELOG (Unreleased, Client impact), DECISIONS #520 to #529, PROGRESS
- [ ] T032 Build, Pest, Jest, touched Playwright specs, browser measurements (375px, dark, RTL), screenshots
- [ ] T033 Guards: clean-code-guard, wp-guard, test-guard, docs-guard; push, PR, CI green
