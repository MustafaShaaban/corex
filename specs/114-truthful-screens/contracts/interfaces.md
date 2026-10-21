# Interface changes

## REST `GET corex/v1/insights`, `POST corex/v1/insights/run`

A result may now be a failed run: `score` and `grade` are `null`, `status` is `failed`, `failure` names the kind and
`summary` is the reason. A reader that shows a score must check `status` first.

## `ActivityRepository::query(array $filters, int $page, int $perPage)`

Unchanged signature. `$filters['area']` may be a string (as before) or a list of strings (any of them).

## Actions (new)

- `corex_operations_mode_changed( string $from, string $to, int $userId )`: after a real mode change is stored.
- `corex_access_refusal_counted( int $userId, string $kind, string $section, int $at )`: when the access log opens a
  new counted entry (not for a repeat inside its hour).

## Script data `corexCaptcha`

Gains `ready` (bool: a keyed provider with both keys) beside `restUrl` and `nonce`.

## Removed

- `Corex\Config\Branding\BrandingService::loginSsoEnabled()` and the Settings field `brand.login_sso_enabled` (the
  stored option is left in place).
- `Corex\Config\ControlPanel\ControlPanelStatus` and `DomainStatus`.
- Blog Pro's `shareControls` script data.
