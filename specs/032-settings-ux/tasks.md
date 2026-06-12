# Tasks: Modern settings UX (032)

**Forward, TDD-ordered.** The per-field-type form rendering is the headless-tested core; the media-frame wiring
+ branding header are the WP boundary (visual env-gated). FR→component map in `plan.md`.

## Phase 1: Setup
- [x] T001 Confirm spec-016 BrandingService + spec-017 SettingsForm/Registry/Store/AdminDashboard are the integration points.

## Phase 2: US1+US2 — field types (P1/P2)
- [x] T002 Write `tests/Unit/Config/SettingsFormTest.php` (RED): media field → preview + Select/Remove + value input; select → options; checkbox → toggle; input types unchanged; all escaped.
- [x] T003 Refactor `SettingsForm` with a `field()` type switch (input/media/select/checkbox) to pass T002.
- [x] T004 Update `SettingsRegistry`: `brand.logo_url` → `media`; `captcha.driver` → `select` (none/honeypot/recaptcha/turnstile/hcaptcha); any boolean → `checkbox`.
- [x] T005 Add `plugins/corex-config/src/admin/settings.js` (wire the media frame to media buttons; set value + preview) + enqueue it on the settings screen in `AdminDashboard` (with `wp_enqueue_media()`).

## Phase 3: US3 — branding header (P2)
- [x] T006 [US3] In `AdminDashboard::render()`, show the configured logo (`BrandingService::logoUrl()`) in the screen header (escaped; only when set).

## Phase 4: Polish
- [x] T007 Guard Gate: wp-guard (escaping per type, enqueue), clean-code, test-guard; fix.
- [x] T008 [P] `composer test` green; verify live: the settings screen renders the media/select/checkbox controls + the header logo; a saved media URL is read back by Config.
- [x] T009 Docs: `plugins/corex-config/README.md` + **docs-app** configuration guide (media uploader + field types + branding); PROGRESS + DECISIONS; NEXT STEP.
