# Implementation Plan: Modes and login protection that do what they say

**Branch**: `spec/124-modes-and-login` | **Date**: 2026-10-26 | **Spec**: [spec.md](spec.md)

## Summary

Eleven stories in two areas of corex-config, with one change each in corex-core (a shared reader of the submission
pause and the lockout event) and corex-forms (the flow block's notices). Every rule stays in one place: the mode model
says what each mode does, one gate closes public submissions for Maintenance and Forms paused, one readiness snapshot
decides what blocks going live, one settings store reads both shapes of the login settings.

## Technical Context

**Language/Version**: PHP 8.3, JavaScript (ES2022, `@wordpress/scripts` for the Security Center bundle, a buildless
script for the mode form). **Primary Dependencies**: WordPress 7.0+, CoreX container. **Storage**: WordPress options
(mode, mode history, visitor messages, login settings, the login hash key), the `login_attempts` and `notifications`
tables. **Testing**: Pest (unit and integration), Jest, Playwright. **Target Platform**: WordPress single site and
multisite, shared hosts with no shell. **Constraints**: nothing a visitor gets changes until a mode or setting is chosen;
the stored login settings of Muva and Perego answer exactly as before.

## Constitution Check

| Principle | How this plan keeps it |
|---|---|
| I Theme is a skin | No theme change. |
| II Plugins boot themselves | Guards register in corex-config's provider; the pause reader is corex-core. |
| III Thin controllers | New REST routes (lockout release) and admin-post handlers (visitor messages, preview) call one service each. |
| IV Injected | New services are container-resolved; no `new` of a dependency inside a method. |
| V Tokens | New CSS uses existing `--corex-` admin tokens only; the token inventory is regenerated. |
| VI Conditional assets | The flow notices ship in the flow block's own stylesheet; the mode script only on its screen. |
| VII Security | REST routes declare capability + nonce in `permission_callback`; admin-post handlers go through `AdminGuard`; output escaped. |
| VIII RTL | Logical properties; cards and panels checked right to left. |
| IX Optional deps | Polylang is detected by function, never required. |
| X Spec first | This spec, then the code. |

Gate: PASS.

## Research (decisions, DECISIONS #520 to #529)

1. **#520 Three switches, read in both shapes.** Keys `hide_login`, `hide_admin`, `limit_attempts`. When none of them is
   stored, the old keys decide: `enabled && block_default_endpoints` → all three on; `enabled` alone → limits on;
   otherwise all off (the old "hide" default of true is ignored when protection was off). Nothing is written on update.
   A save writes the new keys and `enabled` (any switch) and `block_default_endpoints` (`hide_login`), so a rollback
   reads the same. `LoginProtectionSettings::$enabled` stays, meaning "any switch on": the own address and the URL
   rewriting follow it, as before. Alternatives: a one-time option migration (rejected: a write on the two live sign-in
   pages during an update, with no gain).
2. **#521 The settings screen sends only what changed.** The client remembers the loaded values and posts the changed
   keys; the server merges them over the stored settings, validates each submitted key (window 1 to 1440 minutes,
   lockout 1 to 10080 minutes, attempts 1 to 50, retention 1 to 365 days, proxies as IPv4/IPv6 addresses or CIDR
   ranges) and answers 400 naming the field and the range or the line. A value it was not given is never touched, so a
   30-second window set from the command line survives a save of the attempts. The old full-payload clients still work.
3. **#522 Lockouts name their account and can be released.** The enforcer resolves the typed name (login, then email)
   to a user and records its id on failures and lockouts. `POST corex/v1/security/lockouts/release` with `{id}` or
   `{all: true}` ends the lockout by setting its end to now (the same update the recovery command makes), answers
   `already_ended` (409) for one that has ended, and writes `security.lockout.released` to the activity log. Gate:
   `manage_options` + REST nonce, like the settings route (spec 117 moves both to its ability).
4. **#523 Sign-in values keyed per site.** Identity and network are HMAC-SHA256 with a random key stored once in
   `corex_login_hash_key`. Reads match the keyed value and the previous unkeyed one, so attempts and lockouts recorded
   before the update keep counting until they age out. The lockout event carries the account login (or nothing), and
   fingerprints (8 hex characters of the keyed hashes), never the typed name or the address; the notification and the
   activity record are built from it. Old lockout notices are rewritten once on update (option
   `corex_login_privacy_version`), keyed by the same keyed identity, and their stored address removed.
5. **#524 Override phrase and history.** "GO LIVE ANYWAY" when the snapshot at submit has blockers, PRODUCTION when not;
   the other phrase is refused with `blocked` (the wizard already words that status). The history row carries
   `overridden` (blocker labels); the activity log gets `operations.production.override` (security). The minted
   "five-minute confirmation" is removed: it was created and checked in the same request, so the phrase is the gate.
6. **#525 Forms paused and one submission gate.** `forms-paused` joins the mode model, mail treats it as production, a
   lock never takes it away. `Corex\Http\PublicSubmissionPause` (corex-core) answers "paused, by which mode, with which
   words" from the stored mode and messages, for everyone but a signed-in administrator; corex-config's
   `PublicSubmissionGuard` (was `MaintenanceSubmissionGuard`) refuses with it; corex-forms' flow block asks it too.
7. **#526 Messages for visitors.** Option `corex_visitor_messages` = `{maintenance|forms-paused: {messages: {locale:
   text}, back_by: unix}}`. Languages: `SiteLanguages` (the site locale; Polylang's when present; filter
   `corex_site_languages`). The maintenance page renders one block per language with `lang`/`dir`, switching locale for
   the default words and the date. A `wp-content/maintenance.php` is the site's own unless it carries CoreX's marker
   (CoreX writes none today). Preview: `admin-post.php?action=corex_preview_maintenance` with a nonce.
8. **#527 Guardrails and noindex.** `ModeGuardrails` (pure) gives the four rows; `ModeRobots` adds `noindex` through
   `wp_robots` and `X-Robots-Tag` on `send_headers` in Development and Staging.
9. **#528 Hardening by mode.** Checks gain `live_only` (ssl, debug_display, search_indexing). The snapshot factory marks a
   live-only check `deferred` in Development and Staging; `LaunchChecklist` puts deferred rows in a "Before going live"
   group and leaves them out of every count but the blocking one (the gate still blocks on them); readiness
   notifications are not raised for deferred checks. Texts no longer say "production site".
10. **#529 Mode cards and the visitor notices.** The select becomes a `fieldset` of radio cards; the buildless script
    reveals the selected card's block and keeps Apply disabled with a reason until its confirmation is given. The flow
    block prints the test-site notice above the form in Development/Staging and the paused panel instead of the form.

## Data model

| Entity | Where | Shape |
|---|---|---|
| Login settings | option `corex_login_protection_settings` (loaded) | `hide_login`, `hide_admin`, `limit_attempts`, `custom_slug`, `threshold`, `window_seconds`, `lockout_seconds`, `trusted_proxy_mode`, `trusted_proxy_ranges`, `retain_days`, `successful_login_logging`, plus `enabled`, `block_default_endpoints` written for older readers |
| Hash key | option `corex_login_hash_key` | 64 hex characters, created on first need |
| Lockout | table `login_attempts` row, outcome `locked` | `user_id` now set when the name is an account; `reason_code` `released_by_owner` after a release |
| Visitor messages | option `corex_visitor_messages` | per mode: `messages` (locale → text, max 300), `back_by` (unix, 0 = none) |
| Mode history row | option `corex_operations_mode_log` | `{time, user, from, to, overridden?: list<string>}` |

## Contracts

- `POST corex/v1/security/login-protection` (changed): accepts `hide_login`, `hide_admin`, `limit_attempts`,
  `custom_slug`, `max_attempts`, `window_minutes`, `lockout_minutes`, `trusted_proxies`, `retention_days`,
  `successful_login_logging`; any subset; still reads `enabled`/`block_default_endpoints`/`window_seconds`/
  `lockout_seconds` from an older client. 400 `corex_invalid_login_setting` with `field` and a message.
- `POST corex/v1/security/lockouts/release` (new): `{id:int}` or `{all:true}` → `{released:int, lockouts:[…]}`; 409
  `already_ended`; 403 without the capability.
- Public submission routes: 503 `site_in_maintenance` or `forms_paused`, `Retry-After`, body `{code, message, data}`.
- `wp corex mode set forms-paused --acknowledge`; `wp corex mode set production --phrase="GO LIVE ANYWAY"`.
- `admin-post.php?action=corex_visitor_messages` (save) and `action=corex_preview_maintenance` (preview).

## Project structure (files touched)

```text
plugins/corex-core/src/Http/PublicSubmissionPause.php        new
plugins/corex-core/src/Security/LoginLockoutEvent.php        fields change
plugins/corex-core/src/Environment/SiteEnvironment.php       forms-paused
plugins/corex-config/src/Operations/                        OperationsMode, ModeDisclosure, ModeChangeService,
    ProductionLaunchService, OperationsModeStore, MaintenanceGuard, PublicSubmissionGuard, ModeGuardrails, ModeRobots,
    VisitorMessages(+Store, Controller), SiteLanguages, MaintenancePage, MaintenancePreviewController
plugins/corex-config/src/Security/                          OperationsSecurityScreen, SecurityCenter.js, state,
    SecuritySettingsController, LockoutReleaseController, HardeningChecks, LoginProtection/*
plugins/corex-config/src/Readiness/                         LaunchChecklist, ReadinessWords, ReadinessController
plugins/corex-config/src/Notifications/Producers/            LoginLockoutNotificationProducer, Readiness producer
plugins/corex-config/assets/                                operations-mode.js, operations-security.css
plugins/corex-forms/src/Block/                              FlowBlockRenderer, VisitorFormNotice, _flow.scss
packages/cli/src/Commands/                                  ModeCommand, SecurityResetLoginCommand
```

## Complexity tracking

None: no principle is bent.
