# Research: Settings that save what you changed

Each entry: the decision, why, and what else was weighed. The code was read on `main` at `da968342` (spec 114
merged). Spec 113 (open PR #437) binds the config engine's `.env` and network-lock layers once each and adds
`Corex\Environment\SiteEnvironment`; this release reads the same layers.

## R1. Seven pages, one form each, redirected after a save (US-SET-01)

**Decision.** `SettingsPages` declares the pages: General and appearance (`brand`, `dashboard`), Email (`mail`,
`forms`), Captcha, Media, Insights, Guides, Advanced. A page's address is
`admin.php?page=corex-settings-config&section=<page>`; `corex_tab=<section>` (used by the Overview, Email Studio, the
Submissions inbox and the mail delivery policy) still resolves, to the page holding that section. The page posts its
key; the saver writes only that page's sections. After a save the screen keeps the result in a per-user transient and
redirects to the page (post, redirect, get), so a reload never resends; the page reads the result once.

**Why.** The design draws a sub-navigation of groups in the rail; an add-on's section must leave the navigation when
the add-on is not installed and keep an address of its own, so each add-on's section is its own page. Brand and the
Dashboard widgets are both about how the admin looks; Forms holds one field, the notification recipient, an email.

**Alternatives.** One form with tabs and a bar per tab (an edit on another tab would be lost or saved unseen). Nine
pages, one per section (a long sub-navigation for two one-field sections). Rendering the result in the post's own
response (a reload resends).

## R2. The save bar is a reset button and a submit button; scripts add the state (US-SET-01)

**Decision.** The bar holds a status line, `<button type="reset">Discard</button>` and `<button type="submit">Save
changes</button>`. Every control carries the value to restore (`data-corex-saved`). The script compares each control
with it on every input: "No changes" (both buttons disabled), "Unsaved changes", or, right after a save, "All changes
saved". Discard puts each control back to its saved value and dispatches `change`, so the enhanced selects, the
conditional rows and the media preview follow. `beforeunload` asks only while there are unsaved changes and never
during the page's own submit. A refused value is drawn in its field after the redirect while `data-corex-saved` holds
the stored one, so the page opens at "Unsaved changes". Without scripts both buttons work natively and the status
line shows only what is true at load (the saved result).

**Alternatives.** A custom confirm dialog on link clicks in the rail (the browser's own prompt also covers reload,
closing the tab and the back button, which a click handler cannot).

## R3. Locks are read from the config engine's own layers (US-SET-02)

**Decision.** `Corex\Support\Config\SettingLocks` asks the `.env` source, then the network-lock source, whether they
hold a key, and answers a `SettingLock` (where, and the value) or nothing. Both sources are bound once in the
container (as spec 113 binds them) and given to the repository and to `SettingLocks`, so `.env` is read once per
request. A locked field is drawn with the value in force (`readonly`, described by "Set in the site's .env file" or
"Locked by the network administrator"); a locked secret is a line "Set in .env" with no input. The saver skips a
locked key whatever is posted. Section states (captcha keys missing, Insights keys) read `ConfigInterface`, the value
in force.

**Alternatives.** A "which source answered" method on `Repository` (the repository is an interface clients implement
around; a separate reader adds nothing to it). Reading `getenv()` (the config engine does not use the process
environment).

## R4. Provider states come from one service (US-SET-03, US-SET-15)

**Decision.** `ProviderSetup` answers, from the value in force: for captcha, nothing to say (no key-based provider), or
the keys missing and the effect; for Insights, the PageSpeed line and the Cloudflare line. The captcha add-on's card on
the Add-ons screen asks the same service. Effects follow the code: no secret resolves to `NullCaptcha` (the honeypot
still guards); a secret with no site key builds the provider but the widget script never loads (no token, so
protected forms are refused); PageSpeed without a key runs on Google's shared quota (`PerformanceProvider`).

## R5. A test result is remembered with a fingerprint, never the key (US-SET-03)

**Decision.** `ProviderTestLog` stores per provider: when, whether it worked, the reason in words, and an HMAC-SHA256
(keyed with `wp_salt('auth')`) of the values the test used, read from `ConfigInterface`. A record whose fingerprint no
longer matches reads "Not tested yet". The captcha add-on's existing route calls `do_action('corex_provider_tested',
'captcha', $worked, $reason)` and corex-config records it; nothing in the response or the record carries a key.

**PageSpeed probe.** `GET runPagespeed?key=<key>` with no address: Google rejects a bad key with "API key not valid"
(`API_KEY_INVALID`) before it looks at the request, so any other 400 means the key was accepted, with no measurement
run. A 429 or a quota message is "the quota is used up"; no answer is "Google could not be reached".

**Cloudflare probe.** `GET /client/v4/user/tokens/verify` with the token; when that does not succeed and an account ID
is set, `/accounts/<id>/tokens/verify` (account-owned tokens). `success` with status `active` is working; anything
else is "Cloudflare refused the token".

**Alternatives.** Running a real PageSpeed measurement (up to a minute and it spends quota); storing a plain hash of
the key (guessable for short keys; an HMAC with the site's salt is not).

## R6. Remove is a box to tick, enhanced into a confirmed button (US-SET-15)

**Decision.** A saved, unlocked secret draws a checkbox `corex_remove_secret[]` = its key, labelled "Remove the saved
key when I save", with the effect beside it. The script hides the box and shows "Remove"; pressing it asks
(`window.confirm`, which every assistive technology reads) with the effect, then ticks the box, marks the page unsaved
and offers "Keep it". The saver deletes the option only for a secret field of the posted page that is not locked, and
records `settings.secret_removed` in the activity log (area settings, the field's label, no value).

## R7. Add-on pages follow `PluginActivationInspector` (US-SET-07)

**Decision.** `SettingsSectionStates` gives Media, Captcha and Guides their add-on's state: not installed (the plugin
file is absent) leaves the page out of the navigation and its address draws `AdminPage::state()` "This page is not
available"; installed and off draws "<Name> is off. These settings apply when it is on." with "Open Add-ons" to
`admin.php?page=corex-addons#corex-addon-<slug>` (each card gains that id) and read-only fields, and no save bar.

## R8. Translation is proven twice (US-SET-08)

**Decision.** Every string in `SettingsRegistry`, `SettingsPages` and the new classes goes through `__()` in the
`corex` domain; brand names (reCAPTCHA, Cloudflare Turnstile, hCaptcha) stay literal. A unit test stubs `__` to mark
its argument and fails on any title, label, help, help link, choice or page name that is not marked, apart from a
named list of brand names. A header-gated e2e fixture marks every `corex` string through `gettext` and switches the
admin to right-to-left for one request; a browser test reads every Settings page and finds no unmarked words except
brand names and values.

## R9. Switches share the Add-ons control (US-SET-09)

**Decision.** The `.corex-toggle` track and knob move from `addons.css` to the admin shell stylesheet. A Settings
switch is a real checkbox with `role="switch"` laid over the track, so Space, the label and the saved "1"/"0" are the
browser's; On and Off are both in the markup and CSS shows the one that matches `:checked`, so it reads right without
scripts. The knob moves with `inset-inline-start`, so it mirrors in right-to-left.

## R10. The library conversion is the existing bounded job, started from the page (US-SET-06)

**Decision.** corex-media gains `MediaConversionService` and routes `GET/POST corex/v1/media/conversion`, `POST
.../cancel` and `POST .../advance`. Start refuses when conversion is off or the server cannot write WebP (the job
handler is registered only then), and when a run is active (a fixed input hash makes `JobService::enqueue()` find it).
The open page polls `advance`, which fires the runner's action (`corex_run_bounded_job`) for one step and returns the
status; WP-Cron continues it when the page is closed. The batch source reports failures by file name with a reason
(missing file, unreadable file, conversion refused); they are kept with the run in `corex_media_conversion` (at most
100). Skipped is processed minus converted minus failed. Start, cancel and the end are recorded in the activity log
(area settings). The panel is HTML given to Settings through the filter `corex_settings_section_extra`, so corex-config
never depends on corex-media.
