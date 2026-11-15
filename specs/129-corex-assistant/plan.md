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
| `AssistantSettings` | `AssistantSettings` | the switch, keys and models through `ConfigInterface` (`.env` > option); order, switches, limits, branding and booking from options (slice 2) |
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
| `Admin\ServiceCheck`, `Admin\ServiceCheckController` | `AssistantSettingsPage` status + check | the check is `POST corex/v1/assistant/check`; slice 1's `Admin\AssistantPanel` (drawn under a Settings section) is removed in slice 2 |

Framework edits: `AddonProviderRegistry`, `AddonRegistry` (catalogue), `composer.json`, `package.json` workspaces,
`.github/CODEOWNERS`. Slice 1 also edited `SettingsPages`, `SettingsRegistry`, `SettingsStore::save()` and
`SettingsSaver`; slice 2 returns all four to `main` (the screen of FR-019 replaces the page, and a secret saved
unloaded was invisible to the Config engine).

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

## Slice 2 (FR-016 to FR-020)

| Unit | Job |
|---|---|
| `Chain\Provider` | catalogue gains `openai` and `anthropic` (paid, `api`, answer-length field, sampling); `fromCatalogue()` |
| `Chain\ModelClient` | headers and body per API; the Messages API's answer (text blocks, `refusal`, `max_tokens`); 529 a server error |
| `Chain\ChatRouter` | skips a paid service once today's paid requests are spent; counts each request sent to one |
| `Chain\Cooldowns` | keeps each service's last failure a week, for the screen |
| `Limits\VisitorLimits` | caps from the owner (hour, day, site, paid); paid counts |
| `Booking\BookingHours` | the hours in the business zone; the slots from now; the preview in another zone |
| `Booking\SlotsController` | `GET corex/v1/assistant/slots`, public, `no-store` |
| `Forms\RequiredForChannel` | the `required_for:<channels>` rule, asked of absent values too |
| `Admin\AssistantScreen`, `AssistantScreenView`, `AssistantScreenSave` | CoreX → Assistant: menu, cards, save through `AdminGuard` |
| `Admin\ServiceCheck` | a real question with the full instructions, timed, each failure recorded |
| `blocks/assistant/slots.js`, `handoff.js` | the times by the visitor's day, both zones for the team; fields that follow the channel |

Framework edits: `AdminPage` (rail entry and breadcrumb for `assistant`), the rail icon, and slice 1's Settings
section, page and guide topic removed again.
