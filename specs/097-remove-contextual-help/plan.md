# Implementation Plan: The Help tab CoreX never asked for

**Spec**: [spec.md](spec.md) · **Branch**: `spec/097-remove-contextual-help`

## Constitution Check

| Principle | How this change satisfies it |
|---|---|
| III thin controllers / fat services | `ScreenHelp` does one thing and holds no state. |
| IV everything injected | `ScreenHelp` and `CorexAdminAssets` both receive `CorexScreens` from the container; nothing is `new`ed inside a method. |
| VI assets load conditionally | No asset is added. The fix is a hook, not a stylesheet — FR-002 forbids the CSS route. |
| VIII RTL first-class | Verified in both directions by the browser matrix (FR-011). |
| IX no optional dependency is hard | The removal lives in `corex-config`, so it does not require the optional `corex-guides` add-on (FR-005). |
| X spec is source of truth | This spec precedes the code; spec 084's FR-013 is marked superseded rather than deleted. |

## Files

```
plugins/corex-config/src/AdminUi/CorexScreens.php        NEW  the one screen predicate
plugins/corex-config/src/AdminUi/ScreenHelp.php          NEW  removes help on CoreX screens
plugins/corex-config/src/AdminUi/CorexAdminAssets.php    delegates supports() to CorexScreens
plugins/corex-config/src/Notifications/NotificationToolbar.php  depends on CorexScreens, not the assets class
plugins/corex-config/src/ConfigServiceProvider.php       binds + registers both
addons/corex-guides/src/ContextualHelp.php               DELETED
addons/corex-guides/src/GuidesServiceProvider.php        stops registering it
addons/corex-guides/src/GuideRegistry.php                forScreen() removed
addons/corex-guides/src/Guide.php                        $screen docblock retargeted at the deep link
```

## Why `admin_head` at `PHP_INT_MAX`

`wp-admin/admin-header.php` fires `admin_head` and *then* calls
`WP_Screen::render_screen_meta()`, which is what emits `#screen-meta`,
`#contextual-help-link-wrap` and the panel. So `admin_head` at the lowest possible priority is the
last point that still beats the render, and it runs after every point at which help can be
registered — `admin_menu`, `load-{hook}`, `current_screen`, and `admin_head` itself (FR-003).
`current_screen`, which is where spec 084 hooked, is too early to catch anybody else's tabs.

`render_screen_meta()` renders the help link only when `get_help_tabs()` or the sidebar is non-empty,
so clearing both removes the wrapper outright — nothing to hide, nothing left holding height
(FR-006). Screen Options is a separate branch of the same method and is untouched.

## Why the predicate is extracted rather than copied

`CorexAdminAssets::supports()` is already used as a screen oracle by a class that wants nothing else
from it — `NotificationToolbar` injects it as `$screens`. Adding `ScreenHelp` as a third consumer of
a method on an *assets* class would entrench that. `CorexScreens` is the regex and the one method;
`CorexAdminAssets::supports()` stays as a delegate so no other caller has to change.

## Why the removal is in corex-config, not corex-guides

`corex-guides` is an optional add-on (Principle IX). If the removal lived there, deactivating Guides
would give every CoreX screen its Help tab back — a plugin that fixes a defect only while installed
has not fixed it (FR-005).

## Testing

- **Unit (Pest)** — `CorexScreens` matches the toplevel, submenu and option-page shapes and rejects
  non-CoreX hooks; `ScreenHelp` calls `remove_help_tabs()` + `set_help_sidebar('')` on a CoreX screen
  and neither on a foreign one; `GuideRegistry` no longer exposes `forScreen()`.
- **Browser (Playwright)** — new `tests/e2e/admin-help-tab.spec.js`, covering FR-001/006/007/008/
  010/011. `tests/e2e/guides.spec.js`'s Help-tab test is rewritten against the Guides-screen link.
- **Fixture** — a client-registered guide is seeded through an mu-plugin in the CI workflow so
  FR-008 is proved against the public seam rather than against a Corex guide.

## The two layout defects (FR-012, FR-013)

Diagnosed in a real browser against `http://corex.local` before any CSS is written: read what
actually overflows / what `elementFromPoint` actually returns at the click point, then fix the
containment. The assertions do not move.
