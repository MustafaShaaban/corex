# Tasks: Kit and public page safety

**Input**: [spec.md](spec.md), [plan.md](plan.md). Tests come first in each story. `[P]` means it touches files no other
open task touches.

## Phase 1: Setup

- [x] T001 Set up a scratch WordPress for this worktree (port 8915, database `corex_w115`) and confirm WordPress loads
  (Environment Gate).

## Phase 2: US-SAFE-07 Reset never destroys a page the owner had (P1)

- [x] T010 [P] Unit tests: `ResetPlanner` turns created, replaced (with and without a record), adopted (with and
  without) and a reading record into the right actions and labels (`tests/Unit/Cli/ResetPlannerPagesTest.php`).
- [x] T011 [P] Unit tests: `ResetExecutor` restores, keeps, empties, removes and restores reading settings
  (`tests/Unit/Cli/ResetDispositionTest.php`).
- [x] T012 Integration test: apply with Replace and Adopt, then reset; the pages, their ID, content, template, status
  and the front page come back; a page without a record is kept (`tests/Integration/Kit/KitResetRestoresTest.php`).
- [x] T013 `PageRecord` in corex-core.
- [x] T014 `BlueprintActivator` writes the page and reading records before changing anything.
- [x] T015 `ResetPage`, `ResetInventory`, `ResetAction` kinds, `ResetPlanner`, `ResetExecutor`, `ResetCommand`.

## Phase 3: US-SAFE-09 The wizard promises only what it does (P1)

- [x] T020 [P] Jest: the welcome and Backup step text; the recorded pages listed; Apply disabled until confirmed
  (`addons/corex-kit-company/assets/__tests__/setup-wizard.test.js`).
- [x] T021 Unit test: `planPreview` lists adopted pages; the refusal message (`tests/Unit/Kit/SetupWizardControllerTest.php`).
- [x] T022 `SetupWizardController` (adopted list, refusal) and `setup-wizard.js`.

## Phase 4: US-SAFE-14 Shipped patterns make no claims (P1)

- [x] T030 [P] Pest check over `PatternLibrary` and every `theme/patterns/*.php` (`tests/Unit/Patterns/ShippedPatternsClaimNothingTest.php`).
- [x] T031 [P] Unit tests: `SampleBlocks` hides a marked block from visitors, labels it for editors, leaves other
  blocks alone (`tests/Unit/Blocks/SampleBlocksTest.php`).
- [x] T032 [P] Jest: the editor script labels a marked block and removes the marker on the first change
  (`plugins/corex-blocks/assets/__tests__/sample-editor.test.js`).
- [x] T033 `SampleBlocks`, its stylesheets and editor script, wired in `BlocksServiceProvider`.
- [x] T034 `PatternLibrary`: stats and testimonial marked as samples.
- [x] T035 `theme/inc/pattern-links.php` and the 17 theme pattern files.

## Phase 5: US-SAFE-15 The hero can be read and its buttons used (P1)

- [x] T040 [P] Unit tests: `ActionLinks` fills an empty button from the brand link or the contact page, drops it with
  neither, and leaves an editor's own address (`tests/Unit/Ui/ActionLinksTest.php`).
- [x] T041 Playwright: front-page hero contrast at least 4.5:1, buttons at least 3:1 and reachable by Tab
  (`tests/e2e/front-page-hero.spec.js`).
- [x] T042 `PatternLibrary` hero and CTA colours and action-link buttons; `ActionLinks` wired in `UiServiceProvider`.

## Phase 6: US-FLW-02 Flow edits are never lost without a warning (P1)

- [x] T050 [P] Jest: dirty state, the question on Back, Publish, Move to draft, Close and Run test, Save then act,
  failed save, the leave warning, the Test tab's version (`plugins/corex-config/src/Forms/__tests__/unsavedChanges.test.js`).
- [x] T051 `flowEditor.js`, `useFlows.js`, `index.js`, `FlowEditorPanel.js`, `TestTab.js`, `UnsavedChangesDialog.js`.

## Phase 7: US-AUT-02 and US-VIS-11 Captcha on CoreX forms (P1)

- [x] T060 [P] Unit tests: `CaptchaChallenge` markup per provider, declaration, refusal messages; the asset
  controller loads on a declaration without corex-forms (`tests/Unit/Captcha/CaptchaChallengeTest.php`,
  `tests/Unit/Captcha/CaptchaAssetControllerTest.php`).
- [x] T061 [P] Jest: v3 and widget scripts bind to a declared form, not to an undeclared one, write to its message line,
  and reset on the form's events (`tests/corex-captcha-v3.test.js`, `addons/corex-captcha/assets/__tests__/`).
- [x] T062 [P] Unit/Jest: account registration form prints the check; account.js and newsletter view.js send the
  token and dispatch the events.
- [x] T063 Integration test: the four routes refuse a missing token with the message and store nothing, and accept
  a passed one, with a provider stubbed (`tests/Integration/Captcha/ProtectedRoutesTest.php`).
- [x] T064 `CaptchaChallenge`, `CaptchaAssetController`, `CaptchaServiceProvider`, the two scripts.
- [x] T065 Account renderer, account.js, the profile route's message.
- [x] T066 Newsletter renderer and view.js; the newsletter, bookings and careers routes' message; the two READMEs.

## Phase 8: Polish

- [x] T070 Run unit, integration, Jest, lint and the Playwright specs this touches.
- [x] T071 Guards: clean-code-guard, wp-guard, test-guard, docs-guard; UI measured (hero, wizard step, flow dialog).
- [x] T072 DECISIONS #400 to #407, CHANGELOG `[Unreleased]` with Client impact, PROGRESS "In flight".
- [ ] T073 Push, open the PR, CI green.

## Built as

- **T001.** A worktree WordPress on `http://127.0.0.1:8915`, database `corex_w115`.
- **T013 to T015 (DECISIONS #400).**
  - `PageRecord` (core), `KitPageReader`, `ResetPage`, four page actions and `RESTORE_READING`.
  - The guard review found two problems, both fixed: a kit-created page that was replaced would have
    been restored as the owner's, and a restore run by somebody without unfiltered HTML stripped
    embeds.
- **T022 (#401).** `planPreview` gains `adopted` and `pageTitle`.
- **T033 to T035 (#402, #403).** `Corex\Blocks\SampleBlocks` and `assets/sample-editor.js`;
  `theme/inc/pattern-links.php`.
- **T042 (#404).** `Corex\Ui\Patterns\ActionLinks` fills the button's address when it is drawn.
- **T051 (#405).** `isDirty()`, `UnsavedChangesDialog` and the leave warning. The dialog was measured
  once in dark mode and right to left.
- **T064 to T066 (#406).**
  - `Corex\Security\FormChallenge` with `NoFormChallenge`.
  - `ChallengeFields` and `ChallengeSettings`, shared with corex-forms.
  - `Corex\Captcha\CaptchaChallenge`.
  - The newsletter action is defined once, as `NewsletterSignupRenderer::CAPTCHA_ACTION`.
- **Found while measuring T064 (#407).** The provider's script was queued after WordPress had printed
  the footer. It is queued at `wp_footer` priority 5 now.
- **Not built, by decision (#406).** Visitor blocks for call requests and job applications.
- **Left open.**
  - Two test-guard nits in `unsavedChanges.test.js`: requests are looked up by position, and a failed
    save is covered after Publish but not after Back.
  - `pattern-links.php` keeps a broad `catch (\Throwable)` so a theme never fatals without the
    framework.
