# Tasks: A code-defined form's wording, markup and protection

**Input**: [spec.md](./spec.md), [plan.md](./plan.md)

**How this was written**: by hand to the Spec Kit tasks template. `/speckit-tasks` was not run.

Tests are written first and seen to fail. `[P]` marks a task that touches no file another open task
touches. Each slice ends with its guards, its rendered check where it has UI, and its notes
(`CHANGELOG.md` with client impact, `PROGRESS.md`, `DECISIONS.md`).

## Slice 1 — A form says what it reads (US1)

- [x] T001 Pest unit: a form that states its submit label, confirmation and general error is rendered with them; a form that states none is rendered with today's three strings; markup in stated wording is printed as text; an empty label falls back (`tests/Unit/Forms/FormBlockRenderTest.php`)
- [x] T002 `Form::submitLabel()`, `successMessage()`, `errorMessage()` with today's strings as defaults (`plugins/corex-forms/src/Form.php`)
- [x] T003 `FormBlockRenderer` prints the form's wording (`Block/FormBlockRenderer.php`)
- [x] T004 Integration: a registered form with stated wording, rendered through the block on real WordPress (`tests/Integration/Forms/FormBlockRenderingTest.php`)
- [ ] T005 Docs: the forms guide and the corex-forms README name the three methods; guards; notes

## Slice 2 — A form defined in code is challenged (US2)

- [ ] T010 Run `ProtectionStageTest` and `PerFormProtectionTest` as they are; they are the net for T012
- [ ] T011 [P] Pest unit: `SubmissionChallenge` — no verifier bound, protection off, a verifying provider passing and failing with its detail, a boolean provider, an empty token (`tests/Unit/Forms/SubmissionChallengeTest.php`)
- [ ] T012 Extract `SubmissionChallenge` from `ProtectionStage::verifyCaptcha()`; `FormChallengeContextFactory::forForm()`; the stage calls the service; T010 still green
- [ ] T013 `Form::protection()`, off by default, normalised by `FlowProtection::normalize()` (`Form.php`)
- [ ] T014 Pest unit: `FormSubmissionService` — a protected form with a rejected or missing token is refused with `challenge_failed`, dispatches nothing; with an accepted token it proceeds and the token is not among the values; an unprotected form is not checked (`tests/Unit/Forms/FormSubmissionServiceTest.php`)
- [ ] T015 The check in `FormSubmissionService::handle()` after the trap field; `SubmitController` keeps `captcha_token` and answers a refusal's own code
- [ ] T016 Pest unit: the renderer emits the token input and declares the form when it is protected and a provider is configured; neither otherwise (`FormBlockRenderTest.php`)
- [ ] T017 `FormBlockRenderer` emits the token input and declares the form
- [ ] T018 Jest: the reCAPTCHA script takes the action from the submitted form's token input; two forms with one name and different actions each get their own (`tests/corex-captcha-v3.test.js`)
- [ ] T019 `corex-captcha-v3.js` reads `data-corex-captcha-action`; the localized `forms` map is no longer what decides the action
- [ ] T020 Integration on real WordPress: `POST corex/v1/forms/{slug}` for a protected form with a stand-in verifier — refused with the code and nothing stored; accepted and stored without the token (`tests/Integration/Forms/SubmitLifecycleTest.php`)
- [ ] T021 Playwright: a page with a protected code-defined form loads the provider script and a page without one does not, with the provider's host stood in for (`tests/e2e/`)
- [ ] T022 Docs: forms guide, forms-flows (stop claiming coverage that did not exist), captcha README, headless cookbook (where a token goes); guards; notes

## Slice 3 — A site draws a form its own way (US3)

- [ ] T030 Pest unit, written against `main`'s renderer first: the stock output for the contact form and for a form with every field type, byte for byte (`tests/Unit/Forms/FormBlockRenderTest.php`)
- [ ] T031 [P] Pest unit: each `FormParts` method — attributes with extra classes merged and escaped, hidden fields with and without protection, a field, a control's attributes, an error place, status, submit (`tests/Unit/Forms/FormPartsTest.php`)
- [ ] T032 `FormParts`; `FieldRenderer`'s pieces reachable through it (`Block/`)
- [ ] T033 `FormBlockRenderer` composes the stock form from `FormParts`; T030 still green
- [ ] T034 `Form::markup(FormParts): ?string`; the renderer uses it when it is not null
- [ ] T035 Pest unit: markup missing a required part — an editor is told which, a visitor gets nothing, the failure is logged
- [ ] T036 The missing-part check and notice (`FormBlockRenderer`)
- [ ] T037 Playwright: a hand-drawn form — an empty submission puts each error in the site's place and marks the control invalid; a valid one is confirmed in the site's status place; light and dark, LTR and RTL
- [ ] T038 Docs: a "Draw a form your own way" section in the forms guide with a complete example; `frontend-runtime.md` corrected to the real contract (messages, wording, trap field, events); guards; notes

## Slice 4 — Turnstile and hCaptcha challenge a visitor (US4)

- [ ] T040 [P] Jest: the widget script — renders once per form, writes the token, resets after a submission, reports an unreachable provider, loads the provider script once for two forms (`addons/corex-captcha/assets/__tests__/`)
- [ ] T041 `corex-captcha-widget.js`; `FormParts::challenge()` and the flow renderer place `.corex-form__challenge` for a widget provider
- [ ] T042 Pest unit: `CaptchaAssetController` enqueues the right script for each provider and nothing without a protected form (`tests/Unit/Captcha/`)
- [ ] T043 `CaptchaAssetController` for all three providers
- [ ] T044 Pest unit: with Turnstile or hCaptcha an empty token is refused; `providerConfigured()` is true for all three with a secret (`ProtectionStageTest`, `SubmissionChallengeTest`)
- [ ] T045 Remove `DRIVERS_WITHOUT_WIDGET` and the empty-token allowance; `CaptchaDiagnostic` answers `ok`; the `captcha.widget` gap, the setting's help and their tests corrected
- [ ] T046 Styles for the challenge container, tokens and logical properties; rendered check in light and dark, LTR and RTL, with each provider's test keys
- [ ] T047 By hand, once, with each provider's published test keys: a pass and a forced failure, recorded in DECISIONS as what was seen
- [ ] T048 Docs: captcha README's driver table, configuration guide (`no_widget` gone), security guides, PROGRESS's open item removed; the four add-ons with their own token check recorded as open; guards; notes
