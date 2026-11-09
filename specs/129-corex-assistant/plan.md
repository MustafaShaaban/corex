# Implementation Plan: CoreX Assistant (spec 129)

**Branch**: `spec/129-corex-assistant` | **Spec**: [spec.md](spec.md) | **Decisions**: #600 to #607

## Summary

Perego's spec 058 assistant, moved into a CoreX add-on and made generic. The server keeps Perego's units and its
security fixes; what was Perego's (its profile, its strings, its country list, its mail renderer, its own settings
screen, `wp-config` constants) becomes a CoreX seam: a knowledge provider, a strings filter, the Settings registry
with `.env` locks, the forms engine's own team notification.

## Technical context

PHP 8.3, WordPress 7.0+, `@wordpress/scripts` 36. No new PHP or npm dependency. Tests: Pest (unit, Brain Monkey),
Jest (jsdom). Built in a worktree with its own WordPress (`corex_wassistant`, `php -S 127.0.0.1:8929`).

## Constitution check

| Principle | How it is met |
|---|---|
| II Plugins boot themselves | The provider registers everything on `init`, `rest_api_init`, `wp_footer`, `admin_enqueue_scripts` |
| III Thin controllers | `AssistantController` adapts the request, runs the pipeline, calls `AssistantService::answer()` |
| IV Injected | Every collaborator is a container singleton; WordPress calls sit in small adapters passed in as closures (tested without WordPress) |
| V Tokens | Both stylesheets read `--wp--preset--*` and `--wp--custom--*` tokens with `var()` fallbacks; no raw colours |
| VI Assets conditional | Button assets only when the button prints; chat assets by dynamic import on first use |
| VII Declarative security | The chat route runs the `nonce` middleware and the add-on's `AssistantLimitsMiddleware` through the `Pipeline`; the Check route runs `auth:manage_options` and `nonce` |
| VIII RTL | Logical properties only; the panel's `dir` follows the page |
| IX No hard optional dependency | corex-forms is checked with `class_exists`/`has()`; the chat works without it, with no form |
| X Spec first | This spec, plan and tasks before code |

## Server (`addons/corex-assistant/src`)

| Unit | From Perego | Change |
|---|---|---|
| `Chain\Provider` | `Provider` | none in shape; the catalogue moves here unchanged |
| `Chain\Failure` | `Failure` | none |
| `Chain\Cooldowns` | `Cooldowns` | option `corex_assistant_cooldowns` |
| `Chain\ModelClient` | `ModelClient` | OpenRouter's `X-Title` is the site name, not "Perego Assistant" |
| `Chain\ChatRouter` | `ChatRouter` | none |
| `AssistantSettings` | `AssistantSettings` | reads `ConfigInterface` (`.env` > option), no constants; name, mark, daily cap |
| `Knowledge\Knowledge` | `Knowledge` | site name and free text lines; no Perego `Brand` |
| `Knowledge\KnowledgeProvider` | new | the contract a client implements |
| `Knowledge\SiteKnowledge` | `KnowledgeSource` | default: site name, tagline, up to 40 published pages with the start of their text, capped at 24 KB; transient per locale, dropped on `save_post_page` |
| `Reply\Instructions` | `Instructions` | the assistant's name and the site's name in place of Perego's; no region |
| `Reply\ReplyShaper` | `ReplyShaper` | none |
| `Limits\VisitorLimits` | `VisitorLimits` | cap from settings; keys `corex_ai_*` |
| `Limits\VisitorKey` | `AssistantController::visitorKey` | own class |
| `Limits\VisitorLock` | the `add_option` lock in `handle()` | own class |
| `Http\AssistantLimitsMiddleware` | lock + limits in `handle()`/`respond()` | a `Middleware`, so the route declares it |
| `Http\AssistantController` | `AssistantController` | body limit, then Pipeline(nonce, assistant-limits), then the service |
| `AssistantService` | `respond()` | the conversation in, `{reply, team, links, busy}` out |
| `Front\AssistantStrings` | `AssistantContent` | English `__()` defaults, `corex_assistant_strings` filter |
| `Front\AssistantButton` | `AssistantBlock` | block `corex/assistant`, printed in `wp_footer`; config adds `dir`, `country`, `countriesFirst` |
| `Forms\AssistantCallbackForm` | `AssistantCallbackForm` | `phone` rule only; default listeners (store + team email) |
| `Admin\AssistantPanel`, `Admin\ServiceCheck`, `Admin\ServiceCheckController` | `AssistantSettingsPage` status + check | drawn under the Settings section by `corex_settings_section_extra`; the check is `POST corex/v1/assistant/check` |

Framework edits: `AddonProviderRegistry`, `AddonRegistry` (catalogue), `SettingsPages` (page `assistant`),
`SettingsRegistry` (section `assistant`), `SettingsStore::save()` + `SettingsSaver` (secrets not autoloaded),
`composer.json`, `package.json` workspaces, `.github/CODEOWNERS`.

## Front end (`addons/corex-assistant/blocks/assistant`)

`view.js` (button, invitation, lazy import), `chat.js`, `dom.js`, `handoff.js`, `dial-codes.js` (ISO to dial
code and timezone to ISO, names from `Intl.DisplayNames`, replacing Perego's PHP list and its GET route),
`style.scss`, `chat.scss`. `webpack.config.js` versions the lazily loaded CSS by content hash (Perego's fix).

## Tests

- Pest unit (`tests/Unit/Assistant/`): chain (settings, client, failures, cooldowns, router), reply (instructions,
  shaper, conversation input), limits (counts, IPv6 key, lock), service (busy paths, team), button markup,
  callback form, site knowledge text and links, settings registry/page entries, secret autoload.
- Jest (`addons/corex-assistant/blocks/assistant/__tests__/chat.test.js`): chat (log from storage, reply with
  chips, text only, team opens the form, busy, expired, Escape, new chat, bounded history), hand-off (validation,
  post body with honeypot, token and chat, timezone preset, site default), view (lazy load once, retry).
- Integration (`tests/Integration/Assistant/`): token, body limit, busy, the lock in the real table, a reply from
  the default knowledge, the form, Check services by role.
- Playwright (`tests/e2e/assistant.spec.js`): off by default, lazy chat, chips, hand-off, axe, Escape, a page
  change.

## Risks

Free tiers change limits and model names (models are settings; Check services shows what answers); free tiers
may train on requests (the honesty note says no sensitive data; only the conversation and public copy are sent);
prompt injection (no system role from the visitor, links only to known pages; worst case an off-topic sentence).

## Slice 2: Perego's revision 2 (DECISIONS #609 to #616)

Built in this worktree's own WordPress (`corex_wslice2`, `php -S 127.0.0.1:8931`), on branch
`spec/129-assistant-slice-2`, stacked on slice 1's `spec/129-corex-assistant` (PR #449, open).

### Framework edits

| Where | Change | Why |
|---|---|---|
| `corex-config` `SettingsControls` | types `number` (a whole number) and `order` (a list with Move up/Move down, one hidden input of ids) | US8, US11: an order and a cap are values a screen must hold and check |
| `corex-config` `SettingsSanitizer`, `SettingsSaver`, `SettingsPageRenderer` | `number` refused unless digits; `order` cleaned against its options (unknown dropped, missing appended); a sentence for a refused number | FR-018, FR-024 |
| `corex-config` `SettingsFieldValues` | the Media defaults become one table with the assistant's: free services on, paid off, the order, the limits, the booking hours | US8.3: a fresh screen shows what runs |
| `corex-config` `settings.js` | the order control's buttons; `show_for` reads a switch as `1`/`0` | the booking rows follow their switch |
| `corex-config` `SettingsRegistry` | the Assistant page in four sections: Assistant (switch, name, image, branding per language), Services (order, per service on, key, model; paid cap), Booking, Limits | FR-018, FR-020, FR-024, FR-025 |
| `corex-forms` `Form::prepare()` and `FormSubmissionService` | a valid submission's values pass through the form once before the listeners | FR-022 |
| `corex-forms` `Rules\RequiredIf` | built-in `required_if:<field>,<value>,...`, a `RuleForAbsentValue` | FR-023, Perego's `RequiredForChannel` made generic |

### Add-on edits (`addons/corex-assistant/src`)

| Unit | From Perego (revision 2) | Change |
|---|---|---|
| `Chain\Provider` | `Provider` | catalogue gains `openai`, `anthropic`; each entry says `paid`, `api`, `tokens`, `sampling`; `fromCatalogue()` |
| `Chain\ModelClient` | `ModelClient` | the Messages API's headers, body and answer; `max_tokens` with no text and `refusal` are refused messages |
| `Chain\Cooldowns` | `Cooldowns` | the last failure kept seven days; a bare `until` still read |
| `Chain\ChatRouter` | `ChatRouter` | skips paid services past the paid cap; counts each paid answer (`Limits\PaidAnswers`) |
| `Limits\PaidAnswers` | `VisitorLimits::paidLeft()` | own class: the visitor counts and the money count are separate questions |
| `Limits\VisitorLimits` | `VisitorLimits` | the hour and day caps come from settings |
| `AssistantSettings` | `AssistantSettings` | `order()`, `isOn()`, `chain()` in order, `limits()`, `paidCap()`, `brand()`, `booking()`, `languages()` |
| `Booking\BookingHours` | `BookingHours` | the week from settings presets; a formatter passed in, so the team's email reads in the site's language |
| `Booking\SlotsController` | the GET countries route's slots | `GET corex/v1/assistant/slots`, `no-store` |
| `Forms\AssistantCallbackForm` | `AssistantCallbackForm` | `required_if`; `slot` and `timezone`; `prepare()` writes the slot in both zones |
| `Front\AssistantStrings` | `AssistantSettings::brandCopy()` | defaults, then the filter, then what the owner wrote for that language |
| `Front\AssistantButton` | `AssistantBlock` | `data-slots` when booking is on |
| `Admin\ServiceCheck` | `ServiceCheck` | a visitor's question through `AssistantService::conversation()`; a 26-second budget; failures recorded |
| `Admin\AssistantPanel` | `AssistantSettingsView` (status part) | the owner's order, Free/Paid, On/Off, last failure, paid answers today, the next time on offer |

Front end: `handoff.js` asks the channel first, marks the needed field, fetches the times once and shows the slot
picker (`slots.js`, grouping by day and part of day in the visitor's zone); `chat.scss` styles it with theme
tokens. `assistant-admin.js` prints each check's time.

### Tests (slice 2)

- Pest unit: catalogue and Messages body/answer; OpenAI body; paid skip and count; order and switches; cooldown
  failures; booking slots (overnight, notice, horizon, days, DST, cap) and the both-zones line; the callback's
  `prepare()` and rules; `required_if`; `Form::prepare()` in the service; the `number` and `order` types
  (sanitizer, control, saver); the registry's assistant fields and defaults; strings precedence; the real-size check.
- Integration: the slots route; a hand-off with a slot stored in both zones; a hand-off by email without an email
  refused.
- Jest: channel-led required fields; slot picker grouping and the chosen value posted; the order control.
- Playwright with axe: the Services order saved; the booking rows following their switch; the slot picker in the
  chat and the stored request.
