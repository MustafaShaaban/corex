# Data model: Abilities that mean something

No table or option changes shape. New values:

## Ability `corex_view_screens`

| Field | Value |
|---|---|
| Name | See CoreX screens without changing them |
| Area | CoreX admin (`corex-admin`) |
| Risk | sensitive |
| Locked | no |
| Implied by | `corex_manage_admin` |
| Opens read-only | Overview, Settings, Operations & Security, Insights |

## Screen map (`ScreenAbilities`)

| Slug | Section | Ability | Read-only |
|---|---|---|---|
| corex-settings | overview | corex_manage_admin | yes |
| corex-addons | addons | corex_manage_admin | no |
| corex-forms | forms | corex_manage_forms | no |
| corex-submissions | submissions | corex_manage_submissions | no |
| corex-email-studio | email | corex_manage_email | no |
| corex-data-models | data-models | corex_manage_data (or data models) | no |
| corex-operations-security | operations-security | corex_manage_operations | yes |
| corex-notifications | notifications | corex_manage_notifications | no |
| corex-releases | releases | corex_manage_releases | no |
| corex-access | access | corex_manage_access | no |
| corex-blog-pro | blog-pro | corex_manage_blog | no |
| corex-insights | insights | corex_manage_admin | yes |
| corex-setup | setup | corex_manage_setup | no |
| corex-settings-config | settings | corex_manage_settings | yes |

Guides (`corex-guides`) is not in the map: it opens for anyone signed in.

## Matrix cell

`{effect: allow|deny|inherit, editable: bool, reason: null|locked_definition|site_administrator, state:
allowed_set|denied_set|allowed_admin|not_granted|locked, ignoredDeny: bool}`; each role carries `administers`.

## Activity kinds added

| Kind | Actor | Context |
|---|---|---|
| `access.role.refused` | the person who tried | `role`, `changes`, `blockers` (code and ability) |
| `access.request.expired` | the person who tried to decide it | `request_id`, `requester_id`, `abilities` |

## Audit refusal kind added

`action`: a route call or a save refused for want of the screen's ability; section is the screen's page slug.
