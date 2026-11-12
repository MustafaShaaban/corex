# Research: Screens that tell the truth

Each entry: what was decided, why, and what else was considered. Open choices were decided under the owner's
2026-10-21 mandate, taking the planning workspace's recommendation.

## R1. The Overview reads each fact it names (FR-001 to FR-007)

**Decision.** A new `OverviewFacts` gathers, each in its own guard: the brand (Company name and Admin logo stored), the
latest performance result and whether a PageSpeed key is saved, the captcha (driver, keys, whether the add-on is
active), and the login address state (R2). A fact that throws comes back as `null` and its row reads "Could not check".
`OverviewModel` turns the facts into rows. `ControlPanelStatus` and `DomainStatus` are removed: the Overview was their
only reader, and their "optional means configured" rule is the defect.

**Why.** The rows were derived from a status that called optional domains configured by default.

**Considered.** Fixing `ControlPanelStatus` in place: its three states cannot say "Keyless", "Honeypot only" or "Could
not check" without becoming the new model under an old name.

## R2. One rule for "the default login is hidden" (FR-005, FR-008, FR-009)

**Decision.** `LoginAddressState::of(LoginProtectionSettings, bool $recoveryActive)` answers `off`, `reachable`,
`hidden` or `recovery`. `LoginRouteGuard::hidesDefaults()` asks it, and the Security Center and the Overview print it.

**Why.** The screen printed the hiding setting alone while the guard also required protection on and recovery off. One
rule cannot disagree with itself (SC-002).

## R3. A failed PageSpeed run has no score (FR-010 to FR-013)

**Decision.** `InsightResult::failed($provider, $label, $kind, $reason, $checkedAt)` builds a result with no score,
no grade, status `failed`, the failure kind and the reason; its recommendations are empty, so the recommendations list
skips it. `PerformanceProvider` returns it for a local address (before any request), a transport error and every
non-OK classification; `PsiNormalizer` returns it for an unreadable body. The local reason reads "PageSpeed cannot
reach a local site: it measures only an address that is public on the internet."

**Old rows.** `InsightStore::latest()` and `history()` pass each stored row through `InsightFailure::recognise()`: a
row with score 50, no metrics and a summary that is one of the failure messages (the English text or its translation
in the site's language; the HTTP message matched with any number) is returned as a failed row with that message. The
option is never rewritten, so the number of stored rows is unchanged.

**Trends.** Insights draws no trend or average today. Failed rows carry `status: failed` and `score: null`, so anything
that later summarises scores from history skips them by construction; no helper is added for a reader that does not
exist yet. The Insights widget for performance reads "Could not run" for a failed latest run.

**Card.** A failed result draws "Could not run" where the grade was, the reason as the summary, and a "Retry" button.

## R4. Security events reach the activity log (FR-014 to FR-016)

**Decision.** `SecurityActivityRecorder` writes three kinds into the activity log:

- `operations.mode.changed` (area operations), on `corex_operations_mode_changed`, which `OperationsModeStore::set()`
  fires after it records a real change; every route (screen, WP-CLI, production launch) passes through `set()`.
- `access.denied` (area access, outcome denied), on `corex_access_refusal_counted`, which `AccessAuditLog::record()`
  fires only when it opens a new counted entry. A thousand reloads in an hour stay one event, as spec 112 counts them.
- `security.login.locked` (area security, outcome denied), on the `LoginLockoutEvent` that the login protection
  already dispatches. It keeps the lock's end time only: no typed username, no address (spec 112's sign-in log rule).

Access-request decisions were already written (area access). `WpActivityRepository::query()` accepts a list for `area`
and asks `area IN (…)` with one placeholder each. The card asks for the five newest in the three areas.

**Since when.** `SecurityEventsSince` keeps `corex_security_events_since`, set with `add_option` the first time the
recorder writes or the card is read, whichever comes first. The card says "Recorded since <date>". Older events stay in
their own logs and are not copied.

**Considered.** Copying the mode history and access log into the activity table on upgrade: the story says not to,
and the copies would carry times and actors the original logs do not keep alike.

## R5. The captcha test button (FR-017 to FR-019)

**Decision.** The script finds `captcha_secret`, the id `SettingsForm` renders (dots become underscores), named once
as `SECRET_FIELD_ID` and asserted against the PHP form by a unit test. The server passes `ready` (a keyed driver and
both keys present through `Config`, so keys set in the environment count) and the driver; without `ready` the script
adds a line under the secret field: "Save a site key and a secret key for this provider, then test them here." The
four answers become sentences that start with the outcome ("Works: …", "Wrong key: …", "A key is missing: …",
"Cannot reach the provider: …"); the "works" sentence adds that the site key is checked when a visitor's widget loads.

## R6. Add-on toggles report WordPress's answer (FR-020 to FR-022)

**Decision.** `AddonActivator::enable()` returns `activate_plugins()`'s answer as an `AddonToggleResult`; the flag is
written only on success. `disable()` deactivates for this site only (`$network_wide = false`) and reports failure if
the plugin is still active. `AddonToggleService` refuses to disable an add-on whose scope is Network or MustUse,
calls the activator, and records `addon.enabled` / `addon.disabled` in the addons area with outcome success or
failure. The screen draws a locked switch with "Managed by the network administrator" or "Must-use: always on", and
queues the kit prompt only after a successful enable.

## R7. No SSO slot (FR-023, FR-024)

**Decision.** `AdminBranding::loginMessage()` keeps the subtitle only; the SSO glyph, `BrandingService::loginSsoEnabled()`,
the Brand field and the slot's login styles are removed. The stored option is not read and not deleted; with the field
gone, no save touches it.

## R8. Blog Pro without reading data (FR-025 to FR-027)

**Decision.** With `analytics.has_data` false the reading-signals panel and the "Figures cover…" line are not drawn;
in their place one line: "CoreX does not collect reading data yet. Figures appear here once it does." With data, the
panel is as spec 075 left it. The Sharing card and its `shareControls` payload are removed (BLG-D9). The screen's
subtitle no longer names analytics or sharing.

## R9. Settings saves write only what changed (FR-028 to FR-030)

**Decision.** `AdminDashboard::maybeSave()` skips every section whose state is Hidden or Disabled (asked again on the
server, never read from the post). For each remaining field it compares the posted value with the value the form drew
(`settingDisplay()`): a checkbox compares on/off, a text compares the raw string; an equal value is not written. So the
Media defaults drawn as on while nothing is stored stay unstored, and a switch turned off is saved as off.

**Considered.** Saving only the tab whose button was pressed: the form is one form with one button, so an edit on
another tab would be lost silently. Per-section save bars are US-SET-01 (stage 3).
