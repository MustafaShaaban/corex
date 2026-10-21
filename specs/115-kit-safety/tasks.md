# Tasks: Kit and public page safety

**Input**: [spec.md](spec.md), [plan.md](plan.md). Tests come first in each story. `[P]` means it touches files no other
open task touches.

## Phase 1: Setup

- [ ] T001 Set up a scratch WordPress for this worktree (port 8915, database `corex_w115`) and confirm WordPress loads
  (Environment Gate).

## Phase 2: US-SAFE-07 Reset never destroys a page the owner had (P1)

- [ ] T010 [P] Unit tests: `ResetPlanner` turns created, replaced (with and without a record), adopted (with and
  without) and a reading record into the right actions and labels (`tests/Unit/Cli/ResetPlannerPagesTest.php`).
- [ ] T011 [P] Unit tests: `ResetExecutor` restores, keeps, empties, removes and restores reading settings
  (`tests/Unit/Cli/ResetDispositionTest.php`).
- [ ] T012 Integration test: apply with Replace and Adopt, then reset; the pages, their ID, content, template, status
  and the front page come back; a page without a record is kept (`tests/Integration/Kit/KitResetRestoresTest.php`).
- [ ] T013 `PageRecord` in corex-core.
- [ ] T014 `BlueprintActivator` writes the page and reading records before changing anything.
- [ ] T015 `ResetPage`, `ResetInventory`, `ResetAction` kinds, `ResetPlanner`, `ResetExecutor`, `ResetCommand`.

## Phase 3: US-SAFE-09 The wizard promises only what it does (P1)

- [ ] T020 [P] Jest: the welcome and Backup step text; the recorded pages listed; Apply disabled until confirmed
  (`addons/corex-kit-company/assets/__tests__/setup-wizard.test.js`).
- [ ] T021 Unit test: `planPreview` lists adopted pages; the refusal message (`tests/Unit/Kit/SetupWizardControllerTest.php`).
- [ ] T022 `SetupWizardController` (adopted list, refusal) and `setup-wizard.js`.

## Phase 4: US-SAFE-14 Shipped patterns make no claims (P1)

- [ ] T030 [P] Pest check over `PatternLibrary` and every `theme/patterns/*.php` (`tests/Unit/Patterns/ShippedPatternsClaimNothingTest.php`).
- [ ] T031 [P] Unit tests: `SampleBlocks` hides a marked block from visitors, labels it for editors, leaves other
  blocks alone (`tests/Unit/Blocks/SampleBlocksTest.php`).
- [ ] T032 [P] Jest: the editor script labels a marked block and removes the marker on the first change
  (`plugins/corex-blocks/assets/__tests__/sample-editor.test.js`).
- [ ] T033 `SampleBlocks`, its stylesheets and editor script, wired in `BlocksServiceProvider`.
- [ ] T034 `PatternLibrary`: stats and testimonial marked as samples.
- [ ] T035 `theme/inc/pattern-links.php` and the 17 theme pattern files.

## Phase 5: US-SAFE-15 The hero can be read and its buttons used (P1)

- [ ] T040 [P] Unit tests: `ActionLinks` fills an empty button from the brand link or the contact page, drops it with
  neither, and leaves an editor's own address (`tests/Unit/Ui/ActionLinksTest.php`).
- [ ] T041 Playwright: front-page hero contrast at least 4.5:1, buttons at least 3:1 and reachable by Tab
  (`tests/e2e/front-page-hero.spec.js`).
- [ ] T042 `PatternLibrary` hero and CTA colours and action-link buttons; `ActionLinks` wired in `UiServiceProvider`.

## Phase 6: US-FLW-02 Flow edits are never lost without a warning (P1)

- [ ] T050 [P] Jest: dirty state, the question on Back, Publish, Move to draft, Close and Run test, Save then act,
  failed save, the leave warning, the Test tab's version (`plugins/corex-config/src/Forms/__tests__/unsavedChanges.test.js`).
- [ ] T051 `flowEditor.js`, `useFlows.js`, `index.js`, `FlowEditorPanel.js`, `TestTab.js`, `UnsavedChangesDialog.js`.

## Phase 7: US-AUT-02 and US-VIS-11 Captcha on CoreX forms (P1)

- [ ] T060 [P] Unit tests: `CaptchaChallenge` markup per provider, declaration, refusal messages; the asset
  controller loads on a declaration without corex-forms (`tests/Unit/Captcha/CaptchaChallengeTest.php`,
  `tests/Unit/Captcha/CaptchaAssetControllerTest.php`).
- [ ] T061 [P] Jest: v3 and widget scripts bind to a declared form, not to an undeclared one, write to its message line,
  and reset on the form's events (`tests/corex-captcha-v3.test.js`, `addons/corex-captcha/assets/__tests__/`).
- [ ] T062 [P] Unit/Jest: account registration form prints the check; account.js and newsletter view.js send the
  token and dispatch the events.
- [ ] T063 Integration test: the four routes refuse a missing token with the message and store nothing, and accept
  a passed one, with a provider stubbed (`tests/Integration/Captcha/ProtectedRoutesTest.php`).
- [ ] T064 `CaptchaChallenge`, `CaptchaAssetController`, `CaptchaServiceProvider`, the two scripts.
- [ ] T065 Account renderer, account.js, the profile route's message.
- [ ] T066 Newsletter renderer and view.js; the newsletter, bookings and careers routes' message; the two READMEs.

## Phase 8: Polish

- [ ] T070 Run unit, integration, Jest, lint and the Playwright specs this touches.
- [ ] T071 Guards: clean-code-guard, wp-guard, test-guard, docs-guard; UI measured (hero, wizard step, flow dialog).
- [ ] T072 DECISIONS #400 to #407, CHANGELOG `[Unreleased]` with Client impact, PROGRESS "In flight".
- [ ] T073 Push, open the PR, CI green.
