# Implementation Plan: Kit and public page safety

**Branch**: `claude/spec-115-kit-safety-88dd45` | **Date**: 2026-10-21 | **Spec**: [spec.md](spec.md)

## Summary

Seven stories, in four groups whose files do not overlap:

1. **Kit and reset** (US-SAFE-07, US-SAFE-09). The kit records each page it replaces or adopts, and the site's reading
   settings, before it changes them. Reset reads those records: it restores, keeps, empties or removes each page, then
   puts the reading settings back. The wizard's words say exactly that, and its Backup step lists the pages.
2. **Patterns and hero** (US-SAFE-14, US-SAFE-15). A sample marker that visitors never see and editors see labelled,
   removed by the block editor on the first change. Theme patterns lose `href="#"` and invented contact details. The
   hero and CTA get readable colours and buttons that go somewhere. A Pest check keeps it that way.
3. **Flow editor** (US-FLW-02). A dirty state in the reducer, one question before any action that would lose edits,
   and the browser's leave warning.
4. **Captcha on CoreX forms** (US-AUT-02, US-VIS-11). corex-captcha gives any form one helper for the token field and
   the check's place. Its scripts bind to declared forms, and its loader no longer needs corex-forms. The account
   registration form and the newsletter block use it. The four routes answer a missing or refused check with a clear,
   translated message.

## Technical Context

**Language/Version**: PHP 8.3, vanilla JS (buildless admin and front-end scripts), React via `@wordpress/element` for
the flow editor (built by `wp-scripts`).
**Primary Dependencies**: WordPress 7.0 block, REST and pattern APIs; WP-CLI for reset.
**Storage**: post meta (page record), one site option (reading record). No new table.
**Testing**: Pest unit (Brain Monkey) and integration (real WordPress), Jest, Playwright.
**Target Platform**: WordPress sites, shared hosting included.
**Constraints**:
- no new query on every request;
- Muva loads nothing new: its forms are neither `.corex-form` nor declared;
- Perego's corex-form markup and strings are untouched;
- every colour comes from a palette slug.

## Constitution Check

- **I. Theme is a skin.** The theme gains one presentational helper file (`theme/inc/pattern-links.php`). It looks up
  a page's link or a brand setting for its own pattern files, and holds no business logic. When CoreX is absent, it
  falls back to leaving the detail out. ✔
- **II/IV. Injected.**
  - Reset's records are read by `ResetCommand`, the WP-CLI boundary, and handed to the pure planner.
  - The page record is written by `BlueprintActivator` through a new `PageRecord` value in corex-core, shared with the
    CLI.
  - Captcha markup comes from a container-bound `CaptchaChallenge` service. ✔
- **III. Thin controllers.**
  - The routes keep their single check.
  - The message choice is a small pure method on `CaptchaChallenge` (`refusal()`). ✔
- **V. Tokens.**
  - The hero and CTA use palette slugs.
  - The sample label's stylesheet uses `var(--wp--preset--color--*)` with system-colour fallbacks for themes without
    CoreX's tokens. ✔
- **VI. Assets load conditionally.**
  - The provider script loads only when a form declared itself.
  - The sample label stylesheet loads only when a sample renders for an editor.
  - The editor script loads only in the block editor. ✔
- **VII. Security.**
  - Output escaped, the reset's records read with `get_post_meta`/`get_option`.
  - The wizard route keeps its nonce and capability check. ✔
- **VIII. RTL.** Logical properties in the new CSS; the flow editor's question uses `CorexDialog`. ✔
- **IX.** No optional plugin is required. ✔

## Design

### 1. Kit and reset

- `Corex\Provisioning\PageRecord` (corex-core). It holds:
  - `META = '_corex_kit_before'` and `READING_OPTION = 'corex_kit_reading_before'`;
  - `capture(WP_Post): array{title,content,template,status}`;
  - `restoreArgs(array): array` for `wp_update_post`.
  Pure apart from reading the post's template meta, which it gets as input.
- `BlueprintActivator`:
  - before `wp_update_post` in ADOPT (user page) and REPLACE, it writes the page record if the meta is absent;
  - before setting the front page, it writes the reading record if the option is absent;
  - the false backup comment goes.
- `ResetInventory` gains `pages: list<ResetPage>` (`id`, `title`, `persistedAs`, `hasRecord`) and
  `hasReadingRecord`.
- `ResetCommand::gatherInventory` reads them.
- `ResetPlanner` emits, per page:
  - `REMOVE_DEMO`: created, suffixed, legacy (no disposition), placeholder;
  - `RESTORE_PAGE`: replaced or adopted with a record;
  - `EMPTY_PAGE`: adopted without a record (today's behaviour);
  - `KEEP_PAGE`: replaced without a record.
  After the page actions comes `RESTORE_READING` when a reading record exists, and the option deletions last as
  today. The labels carry the story's words.
- `ResetExecutor`:
  - `restorePage` updates title, content, status and template, and removes the kit meta;
  - `keepPage` removes only the kit meta;
  - `restoreReading` writes the three options, and falls back to `posts` when the recorded page no longer exists.
- The legacy `demoPageId` path is unchanged.

### 2. Wizard wording

- `SetupWizardController::planPreview` adds `adopted: list<{slug,title}>`: the plan's ADOPT dispositions for a user's
  page.
- `setup-wizard.js`:
  - the welcome and the Backup step use the exact sentence;
  - the Backup step lists the recorded pages (adopted, plus conflicts set to Replace), recommends a host or plugin
    backup, and asks to confirm with "I have a full backup of this site.";
  - Apply stays disabled until it is ticked.
- The server refusal message: "Confirm you have a full backup of this site before applying. CoreX records only the
  pages this plan replaces or adopts and your front-page settings."

### 3. Samples, theme patterns and hero

- **Marker.** The class `is-corex-sample` on a group.
- **Server** (corex-blocks, which every site loads; `Corex\Blocks\SampleBlocks`): a `render_block` filter.
  - Blocks without the class return at once; a single `str_contains` on `className` is checked first.
  - With the class: a visitor gets `''`. A user who can `edit_posts` gets the block with a label paragraph in front of
    it, and the label stylesheet is enqueued.
- **Editor** (`plugins/corex-blocks/assets/sample-editor.js`, buildless, on `enqueue_block_editor_assets`):
  - a `editor.BlockListBlock` filter adds `data-corex-sample-label` (translated) to marked blocks; a small editor
    stylesheet draws it;
  - a `core/block-editor` subscription records each marked block's content signature when first seen (its serialized
    inner blocks and its attributes, without `className`); on the first difference it removes the class.
- **corex-ui `PatternLibrary`**:
  - stats and testimonial groups carry the marker;
  - the hero heading and CTA heading get `textColor: surface`; the CTA group `textColor: surface`;
  - hero button: `backgroundColor: surface`, `textColor: primary`; CTA button: `backgroundColor: surface`,
    `textColor: accent-dark`;
  - both buttons carry the class `corex-action-link` and no href.
- **corex-ui `ActionLinks`**: a `render_block_core/button` filter for buttons with `corex-action-link` whose link has no
  href.
  - It fills the href with `brand.primary_action_link`, or else the published page with slug `contact`.
  - With neither, the button is not rendered.
  - A button an editor gave its own address is left alone.
  - It is lazy: it runs only when such a button renders.
- **Theme** (`theme/inc/pattern-links.php`):
  - `corex_pattern_page_link($slug, $label)` returns a link to a published page, or the escaped label;
  - `corex_pattern_brand($key)` returns a brand setting or `''`;
  - `corex_pattern_posts_url()` returns the blog page.
  - Every `href="#"` in the 15 files goes through these. Contact rows render only when their setting is set.
  - `footer-locations` shows the brand address once, in place of three invented offices.
  - `section-logo-cloud` becomes a sample.
  - `maintenance` links to the brand email only when set.
- **Check**: `tests/Unit/Patterns/ShippedPatternsClaimNothingTest.php`.
  - It renders every theme pattern file with stubbed helpers and reads `PatternLibrary` content.
  - Outside sample groups, it fails on `href="#"`, `example.(com|org|net)`, `555`, `\d+%`, `\d+\+`, and a few known
    invented phrases ("Trusted by", "happy client").

### 4. Flow editor

- **Reducer**:
  - `selected` stores `saved` (the configuration as loaded);
  - `isDirty(state)` compares a stable JSON of `draft.configuration` with `saved`.
- **`FlowEditorPanel`**:
  - a status line in the header ("Unsaved changes" / "All changes saved");
  - every guarded action goes through `guard(action, label)`, which runs the action at once when clean, and otherwise
    opens `UnsavedChangesDialog`, built on `CorexDialog`, with Save, Discard (Back only) and Cancel;
  - Save calls `saveDraft()`; on success it runs the action, on failure it stays and the failure message shows.
- **`useFlows`**:
  - `saveDraft` returns the reloaded draft;
  - `transition` and `test` read the version from the latest state, kept in a ref, so an action after a save posts
    the new version.
- **`index.js`**: Back goes through the guard; a `beforeunload` listener is active while dirty.
- **`TestTab`**: shows "Tests the saved draft, version N".

### 5. Captcha on CoreX forms

- **`Corex\Captcha\CaptchaChallenge`** (corex-captcha, singleton, request-scoped):
  - `fields(string $action): string` returns the hidden `captcha_token` input (with `data-corex-captcha-action`) and,
    for Turnstile and hCaptcha, `<div class="corex-form__challenge" data-corex-challenge data-corex-sitekey>`. It
    returns `''` when no provider with keys is set, and records that the page needs scripts;
  - `formAttribute(): string` returns `data-corex-captcha` when protected;
  - `declared(): bool`;
  - `refusal(string $token): string` returns the message for a missing or refused check.
- **`CaptchaAssetController`**:
  - the forms registry becomes optional (null when corex-forms is absent);
  - it loads when the registry or `CaptchaChallenge` has a declaration;
  - it is always registered.
- **Scripts**:
  - v3 and widget select `form.corex-form, form[data-corex-captcha]`;
  - `announce` writes to `[data-corex-captcha-message]` inside the form when present (the account and newsletter
    forms point it at their own message line), else as today;
  - the widget resets on `corex:form:success` / `corex:form:error`, which the account and newsletter scripts now
    dispatch after an answer.
- **Account**:
  - `AccountRenderer`'s registration form prints `CaptchaChallenge::fields('register')` above the button when the
    service is bound, and puts the form attribute on the form;
  - `account.js` serializes it as it does every named field and dispatches the two events.
- **Newsletter**:
  - `NewsletterSignupRenderer` (corex-blocks) asks the container for `CaptchaChallenge` when it is bound;
  - `view.js` sends `captcha_token` and dispatches the events;
  - its captcha message becomes the server's.
- **Routes**: the four routes return `{ok:false,error:'captcha',message}` with the message from `refusal()`. Muva's
  own route (`/forms/{slug}`) is not touched.
- **READMEs**: bookings and careers show the markup.

## Project Structure

Files touched:

```text
plugins/corex-core/src/Provisioning/PageRecord.php                     (new)
addons/corex-kit-company/src/BlueprintActivator.php
addons/corex-kit-company/src/Setup/SetupWizardController.php
addons/corex-kit-company/assets/setup-wizard.js (+ __tests__)
packages/cli/src/Reset/{ResetAction,ResetInventory,ResetPlanner,ResetExecutor,ResetPage(new)}.php
packages/cli/src/Commands/ResetCommand.php
plugins/corex-blocks/src/SampleBlocks.php                              (new) + BlocksServiceProvider.php
plugins/corex-blocks/assets/sample-editor.js, sample.css, sample-editor.css (new)
addons/corex-ui/src/Patterns/PatternLibrary.php, src/Patterns/ActionLinks.php (new), UiServiceProvider.php
theme/inc/pattern-links.php (new), theme/patterns/*.php (17 files)
plugins/corex-config/src/Forms/{flowEditor,useFlows,index,FlowEditorPanel}.js, tabs/TestTab.js,
  components/UnsavedChangesDialog.js (new)
addons/corex-captcha/src/{CaptchaChallenge(new),CaptchaAssetController,CaptchaServiceProvider}.php
addons/corex-captcha/assets/{corex-captcha-v3,corex-captcha-widget}.js
addons/corex-profile/src/Block/AccountRenderer.php, src/ProfileServiceProvider.php, assets/account.js
plugins/corex-blocks/src/NewsletterSignupRenderer.php, src/blocks/newsletter-signup/view.js
addons/corex-{newsletter,bookings,careers}/src/*ServiceProvider.php, addons/corex-{bookings,careers}/README.md
tests: Unit (Cli reset, Kit, Patterns, Captcha, Blocks), Integration (Kit reset round trip, wizard preview,
  samples render), Jest (flow editor, wizard, captcha scripts, account, newsletter), Playwright (front-page hero)
```

## Complexity Tracking

None. Each group adds one small class or helper. No new table and no new route.
