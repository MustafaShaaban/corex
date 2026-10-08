# Implementation Plan: A code-defined form's wording, markup and protection

**Branch**: `spec/104-form-contract-and-protection` | **Date**: 2026-10-08 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/104-form-contract-and-protection/spec.md`

**How this was written**: by hand to the Spec Kit plan template, from reading the source at
`80d74cf6`. `/speckit-plan` was not run.

## Summary

Give a form defined in code a contract beyond its fields: it states its wording, it states that it
is protected, and it may supply its own markup built from parts CoreX publishes. Then place a
widget for the two providers that have none, on both kinds of form.

Delivered in four slices, each its own pull request, in the order under "Slices".

## Technical Context

**Language/Version**: PHP 8.3; JavaScript (ES2022), the front-end scripts unbundled

**Primary Dependencies**: WordPress 7.0; `plugins/corex-core` (the runtime script, the challenge
contracts); `plugins/corex-forms`; `addons/corex-captcha` (optional, detected)

**Storage**: none new. A submission is stored by the form's listeners, as today

**Testing**: Pest unit (`tests/Unit/Forms`, `tests/Unit/Captcha`), Pest integration on real
WordPress (`tests/Integration/Forms`), Jest (`tests/`, `addons/corex-captcha/assets/__tests__`),
Playwright (`tests/e2e`)

**Target Platform**: the public site (the Form block's output and its submit route), single site
and multisite

**Project Type**: WordPress plugin (`plugins/corex-forms`) and add-on (`addons/corex-captcha`)

**Performance Goals**: SC-003: a page with no protected form makes no request to a provider

**Constraints**: the captcha add-on is optional and is never a hard dependency of corex-forms
(constitution IX); assets load only where a form is (VI); output escaped, input sanitised (VII);
logical properties (VIII); every string translatable

**Scale/Scope**: one new public class in corex-forms, four methods on `Form`, one extracted
service, one new front-end script in the add-on

## What the source does today

| Fact | Where |
|---|---|
| `Form` has `slug`, `fields()`, `label()` (its name in the editor's selector) and `listeners()` | `plugins/corex-forms/src/Form.php` |
| The button, the confirmation and the general error are three fixed strings | `Block/FormBlockRenderer.php` l.91-96 |
| One `p.corex-form__status` carries both the confirmation and the general error; the confirmation travels as `data-corex-success` | same; `corex-runtime.js` `renderSuccess` |
| `FormBlockRenderer` and `FieldRenderer` are `final`, print fixed markup, and fire no hook; corex-forms and the captcha add-on fire no WordPress hook at all | `Block/` |
| The renderer is resolved from the container by the class named in `block.json` | `plugins/corex-blocks/src/DynamicBlockRegistrar.php` |
| The runtime reads `data-corex-endpoint`, `-nonce`, `-schema`, `-messages`, `-success`, `-error`, `[data-corex-field]`, `.corex-form__error`, `.corex-form__status`; it collects every named control, so a hidden `captcha_token` is sent | `plugins/corex-core/assets/js/corex-runtime.js` |
| The submit route's sanitiser keeps the trap field and the schema's fields and drops everything else, a token included | `Submission/SubmitController.php` `sanitizeShape()` |
| The pipeline is nonce, sanitise, throttle, then `FormSubmissionService::handle()`: unknown form, trap field, validation, uploads, event | `SubmitController.php`, `FormSubmissionService.php` |
| A refusal's `code` comes from its HTTP status: 401/403 `forbidden`, 429 `rate_limited`, anything else `error` | `SubmitController.php` `codeForStatus()` |
| Only `FlowBlockRenderer` emits the token input and calls `ProtectedFormRegistry::declare()`; the registry is keyed by slug, flows and code forms alike | `Block/FlowBlockRenderer.php` l.135-152 |
| The check lives in `ProtectionStage::verifyCaptcha()`, a stage of the flow pipeline; its context is built from a flow and a version | `Submission/Stages/ProtectionStage.php`, `FormChallengeContextFactory.php` |
| The add-on enqueues only for `recaptcha`; its script finds forms by `data-corex-form` and looks the action up by slug; the `data-corex-captcha-action` the input carries is read by nothing | `addons/corex-captcha/src/CaptchaAssetController.php`, `assets/corex-captcha-v3.js` |
| "Turnstile and hCaptcha place no widget" is written in six places | `FormChallengeContextFactory::DRIVERS_WITHOUT_WIDGET`, `providerConfigured()`, `CaptchaDiagnostic::WITHOUT_WIDGET`, `CapabilityFacts.php` (`captcha.widget`), the `captcha.driver` help in `SettingsRegistry.php`, `CaptchaAssetController.php` |

## Decisions

### D1. Wording is three methods on `Form`

`submitLabel()`, `successMessage()` and `errorMessage()`. Each returns an empty string by default,
and empty means CoreX's own wording, which stays where it is today: in the renderer. A form
overrides what it wants. The renderer escapes what it is given, and a label of nothing but spaces
is CoreX's label too (FR-004). The stock strings are written once that way; had the defaults
returned them, the renderer would have needed its own copy for the blank label. No block
attribute: the reading under Assumptions.

### D2. Protection is a method on `Form`, in the shape a flow already has

`protection(): array` returns `['captcha' => 'off']` by default. A form opts in with
`['captcha' => 'on']` and may add `action` and `threshold`; `FlowProtection::normalize()` is the
one normaliser for both kinds of form.

### D3. One check, extracted, used by both pipelines (FR-018)

`ProtectionStage::verifyCaptcha()` becomes `SubmissionChallenge::verify(token, protection, slug)`
in `Submission/`, returning the same status and detail it stores today.
`FormChallengeContextFactory` gains `forForm(slug, protection)`; `forContext()` delegates to it.
`ProtectionStage` and `FormSubmissionService` both call the service. The stage's behaviour and its
stored metadata do not change, and its tests are the regression net for the extraction.

### D4. Where the check sits on the code-form route

In `FormSubmissionService::handle()`, after the trap field and before validation (FR-012). The
controller only adds `captcha_token` to what the sanitiser keeps. The service removes the token
before validating, so it is never a value (FR-014). A refusal is
`Response::reject(message, 422, ['code' => 'challenge_failed'])`, and `SubmitController::toRest()`
answers with that code in place of the one derived from the status (FR-013). The runtime already
shows a refusal's message in the status region.

### D5. The action travels with the form, not with its name (FR-017)

The token input already carries `data-corex-captcha-action`. The add-on's script reads the action
from the input of the form being submitted, and no longer from a map keyed by slug.
`ProtectedFormRegistry` stays as the answer to "does this page hold a protected form", which is
all the asset controller needs from it.

### D6. A site's markup is `Form::markup(FormParts $parts): ?string`

`null`, the default, means the stock form. `FormParts` is a new final class in `Block/`, built by
the renderer for one form on one render, and is the published contract (FR-021):

| Method | Gives |
|---|---|
| `attributes(array $extra = [])` | the `<form>` element's attributes, escaped: the class the runtime binds, the endpoint, the token, the schema, the messages, the wording. `$extra` adds classes and attributes of the site's own |
| `hidden()` | the trap field, and the token input when the form is protected |
| `field(string $name)` | one field's stock markup |
| `control(string $name, array $extra = [])` | the attributes a hand-written control needs: id, name, required, `aria-describedby` |
| `fieldAttributes(string $name)` | what a field's wrapper must carry for an error to find it |
| `label(string $name)`, `error(string $name)` | a field's label; the place its error is written |
| `challenge()` | where a visible challenge is placed; empty when the form shows none |
| `status()` | the region the confirmation and the general error are announced in |
| `submit(array $extra = [])` | the button, with the form's label |

`FormBlockRenderer` builds the stock form from the same parts, so the two cannot drift, and its
output for a form with no markup of its own is byte-identical to today's (SC-007, pinned by a
test written before the refactor).

Not a filter and not a theme template: the reading under Assumptions. The renderer stays `final`.

### D7. A hand-drawn form with a part missing (FR-024)

After `markup()` returns, the renderer checks for the parts the runtime cannot work without: the
bound class, the endpoint, the trap field, the status region, and the token input when protected.
If one is missing, somebody who can edit posts sees a notice naming it; a visitor gets nothing,
and the failure is logged. Named parts only; the check does not parse the site's design.

### D8. Turnstile and hCaptcha: one script, explicit rendering

`addons/corex-captcha/assets/corex-captcha-widget.js` serves both providers, which share a shape:
load the provider's script once with explicit rendering, render a widget into each
`.corex-form__challenge`, write the token it calls back with into that form's `captcha_token`,
reset the widget after a submission because a token is spent once, and tell the visitor through
`Corex.notices.status` when the provider cannot be reached. The server side exists
(`RemoteCaptcha`). The six "no widget" statements and the empty-token allowance go in this slice
(FR-032, FR-033).

## Constitution Check

| Principle | How this plan meets it |
|---|---|
| Spec before code (X) | This directory, before the first slice. |
| Thin controllers, logic in services (III) | The controller adds one key to its sanitiser and maps one code; the check is a service; the renderer composes parts. |
| Everything injected (IV) | `SubmissionChallenge` is bound in `FormsServiceProvider` and injected into the stage and the service. `FormParts` is a per-render value built by the renderer from its injected collaborators. |
| Assets load conditionally (VI) | A provider's script loads only on a page where a protected form was rendered (FR-011, FR-031). |
| Security declarative (VII) | The route's middleware is unchanged; the token is sanitised with the rest; every part escapes what it prints. |
| RTL first (VIII) | The challenge container uses logical properties; rendered checks in RTL. |
| No optional dependency is hard (IX) | corex-forms asks the container whether a `ChallengeVerifier` is bound, as it does today; without the add-on a protected form is accepted under the protection it has (FR-016). |
| i18n | Every new string through the `corex` text domain. |
| Tests | Pest unit for wording, parts, the extracted check and the refusal; integration for the route on real WordPress; Jest for both captcha scripts; Playwright for a stated label, a hand-drawn form, and a protected form with the provider stood in for. |
| Guard Gate, UI/UX gate | Each slice's diff through its guards; slices 3 and 4 rendered in light and dark, LTR and RTL, before they are presented. |

No violation needs justifying.

## Project Structure

```text
plugins/corex-forms/src/
├── Form.php                              # submitLabel, successMessage, errorMessage, protection, markup
├── Block/
│   ├── FormParts.php                     # new: the published parts
│   ├── FormBlockRenderer.php             # composes FormParts; calls Form::markup()
│   └── FieldRenderer.php                 # its pieces reachable through FormParts
├── Submission/
│   ├── SubmissionChallenge.php           # new: the one check
│   ├── FormChallengeContextFactory.php   # forForm(); the widget list goes in slice 4
│   ├── FormSubmissionService.php         # checks after the trap field
│   ├── SubmitController.php              # keeps captcha_token; answers a refusal's own code
│   └── Stages/ProtectionStage.php        # calls SubmissionChallenge
addons/corex-captcha/
├── src/CaptchaAssetController.php        # all three providers
├── src/CaptchaDiagnostic.php             # no_widget goes in slice 4
└── assets/corex-captcha-v3.js, corex-captcha-widget.js (new)
tests/Unit/Forms/, tests/Unit/Captcha/, tests/Integration/Forms/, tests/e2e/
docs-app/src/content/docs/guides/forms.md, frontend-runtime.md, forms-flows.mdx, configuration.md
plugins/corex-forms/README.md, addons/corex-captcha/README.md
```

## Slices

Each is one pull request and leaves `main` releasable.

| # | Story | What lands |
|---|---|---|
| 1 | US1 | The three wording methods, used by the renderer. This plan and the spec ship with it. |
| 2 | US2 | `Form::protection()`, the extracted check, the route's refusal, the script reading the action from the form. reCAPTCHA covers a code-defined form. |
| 3 | US3 | `FormParts`, `Form::markup()`, the missing-part notice, the documented example. Closes #248. |
| 4 | US4 | The Turnstile and hCaptcha widget on both kinds of form; the six statements corrected. Closes #264. |

## Risks

- **The extraction in slice 2 touches the flow pipeline.** A flow's protection must not change. The
  stage's existing tests run unchanged against the extracted service before anything else is built
  on it.
- **Byte-identical stock output in slice 3.** The test that pins it is written against `main`'s
  renderer first, for the contact form and for a form using every field type.
- **No provider is called by a test.** A widget's behaviour against the real provider is checked by
  hand with each provider's published test keys, once, and recorded as that. What the suites prove
  is CoreX's side: the script's handling of a token and of a failure, and the server's handling of
  the provider's answer.
- **A cached page across a provider change** carries the old provider's script. The refusal message
  tells the visitor to reload; nothing can be done for a page already served.
- **`captcha.action`, the global setting, has no reader today.** It is left as it is; a per-form
  action is what both kinds of form use. Recorded, not fixed here.
