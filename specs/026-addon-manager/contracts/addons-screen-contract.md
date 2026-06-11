# Contract: Corex Add-ons screen

## Menu

- Submenu under the Corex menu (`corex-settings`), slug `corex-addons`, capability `manage_options`,
  title "Add-ons".

## Render (`render()`)

- Requires `AdminGuard::authorized('manage_options')` (returns silently otherwise).
- For each registered add-on, a row showing: label, status (Active / Inactive / Not installed), flag state
  (where it has a flag), and — when a toggle is blocked — the reason.
- Each togglable add-on has a `POST` form: a nonce field (`corex_addons` / `corex_addons_nonce`), a hidden
  `corex_addon` (slug), a hidden `corex_addon_action` (`enable`|`disable`), and a submit button.
- All dynamic values escaped (`esc_html`/`esc_attr`); all strings via `__()`/`esc_html__()` with the `corex`
  text domain; layout uses WP admin `.card` + logical CSS (RTL-correct).

## Apply (`maybeToggle()`)

1. Returns unless the POST carries `corex_addon` + `corex_addon_action` and `AdminGuard::verifiedPost(
   'corex_addons_nonce', 'corex_addons')` is true (cap + nonce). No side effects otherwise.
2. Resolves the add-on from the registry; unknown slug → no-op + notice.
3. For `enable`: if `AddonManager::canEnable()` → `AddonActivator::enable()`; else an admin notice naming the
   missing dependency, no change.
4. For `disable`: if `AddonManager::canDisable()` → `AddonActivator::disable()`; else an admin notice naming
   the blocking dependent, no change.
5. Reports the outcome via an admin notice (escaped, translatable).

## Guarantees

- No toggle ever leaves an active add-on without a dependency, or activates an add-on whose dependency is
  inactive (FR-004 / SC-003).
- An uninstalled add-on renders as "Not installed" and offers no toggle (FR-005).
- No state change without a valid nonce + `manage_options` (FR-003 / SC-004).
