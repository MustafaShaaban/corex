# Interfaces: Settings that save what you changed

## Addresses

- `admin.php?page=corex-settings-config&section=<page>`: one Settings page. Without `section`, General and appearance.
- `admin.php?page=corex-settings-config&corex_tab=<section>`: still works; resolves to the page holding the section.
- `admin.php?page=corex-addons#corex-addon-<slug>`: an add-on's card.

## Settings save (admin POST, `AdminGuard`: `manage_options` + nonce `corex_settings`)

| Field | Meaning |
|---|---|
| `corex_settings_page` | the page key; an unknown page or a page whose add-on is off writes nothing |
| `<field name>` | a field of that page, as today (`brand_company_name`, …) |
| `corex_remove_secret[]` | secret keys of that page to delete |

Answers with a redirect to the page; the result is shown once on arrival.

## REST

All answer the spec-043 envelope. Each needs `manage_options` and a REST nonce (`X-WP-Nonce`). No answer carries a key.

| Route | Plugin | Answer |
|---|---|---|
| `POST corex/v1/settings/providers/(pagespeed\|cloudflare)/test` | corex-config | `{ worked, message, status }` where `status` is the "Last tested …" line; 400 when a key is missing |
| `POST corex/v1/captcha/test` (existing) | corex-captcha | unchanged shape; now also records the result |
| `GET corex/v1/media/conversion` | corex-media | `{ run }` (see below) |
| `POST corex/v1/media/conversion` | corex-media | `{ run }`; 409 `conversion_running`; 409 `conversion_unavailable` with the reason |
| `POST corex/v1/media/conversion/advance` | corex-media | runs one step, `{ run }` |
| `POST corex/v1/media/conversion/cancel` | corex-media | `{ run }`; 409 when nothing is running |

`run`: `{ state, total, converted, skipped, failed, ended_at (site time, words), failures: [{file, reason}], summary }`
or `null` when no run exists; `state` ∈ `queued`, `running`, `completed`, `cancelled`, `failed`.

## Hooks

| Hook | Kind | Arguments | Purpose |
|---|---|---|---|
| `corex_provider_tested` | action | `string $provider, bool $worked, string $reason` | a provider test reports its result (captcha add-on → Settings) |
| `corex_settings_section_extra` | filter | `string $html, string $sectionKey` | an add-on adds a panel under a section (Media's conversion panel); contributors escape their own HTML |

## PHP

- `Corex\Support\Config\SettingLocks::lockOf(string $key): ?SettingLock` (container singleton).
- `Corex\Admin\AdminPage::open(string $section, string $title, string $description = '', string $crumbSuffix = '',
  array $subnav = [])`: `$subnav` is a list of `['label' => string, 'url' => string, 'current' => bool]` drawn under
  the active rail entry.
- `SettingsForm::render()` keeps its signature for option pages; on/off fields there are switches too.
