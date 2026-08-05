# Implementation Plan: An Extendable User-Guide Add-on

**Spec**: `specs/084-guides-addon/spec.md` · **Branch**: `spec/084-guides-addon`

## Shape

A new add-on, `addons/corex-guides`, namespace `Corex\Guides\`.

```
addons/corex-guides/
  corex-guides.php                 header + COREX_GUIDES_VERSION + autoloader resolution
  src/GuidesServiceProvider.php    the single extension seam
  src/GuideRegistry.php            register · registerDeferred · all · find · forSection
  src/Guide.php Topic.php Step.php Screenshot.php
  src/GuidesScreen.php             the admin surface
  src/ContextualHelp.php           the per-screen help-tab bridge (removed by spec 097)
  src/Corex/CorexGuides.php        CoreX's own guides, as registered objects
  assets/guides.css  guides.js  screenshots/
```

## Why the registry looks the way it does

`GuideRegistry` copies `plugins/corex-config/src/Data/DataRegistry.php` in shape, deliberately.
That class's own docblock records the bug this pattern exists to prevent: a registry built eagerly
during corex-config's boot froze its contents *before* add-ons had registered, so the newsletter
table was invisible in the admin while being perfectly visible to WP-CLI, which resolves it later.

The same trap is worse here, because the audience is site plugins. CoreX boots on `plugins_loaded`
at priority 10 and a site plugin registering on `plugins_loaded` at priority 10 is a coin flip. So:

- `registerDeferred(callable)` takes a factory, and nothing resolves until first read — which is an
  admin request, long after every plugin has loaded.
- `$this->resolved = true` is set **before** running the factories, so a factory that reads the
  registry back terminates instead of recursing.
- `apply_filters('corex_guides', …)` runs after resolution, and the result is filtered on
  `instanceof Guide` — the belt-and-braces `CacheRegistry` uses, for the same reason: a malformed
  contribution must not become an unrenderable entry on a screen somebody opened for help.

## Availability without conditionals

A guide declares a capability. `GuideRegistry::available()` filters on `current_user_can()`.

Add-on guides are registered **from the add-on's own provider**, not from a table in corex-guides.
An inactive add-on never boots, so it never registers, so its guides are absent — no `is_active()`
check anywhere, and no list of add-on slugs to keep in sync. This is the container-probe idiom the
add-ons already use (`NewsletterServiceProvider:105`).

## Wiring — five places, none auto-discovered

The exploration confirmed there is no manifest file and no provider discovery:

1. `addons/corex-guides/corex-guides.php` — copy `addons/corex-kit-company/corex-kit-company.php`.
2. root `composer.json` — `"Corex\\Guides\\": "addons/corex-guides/src/"`, then `dump-autoload`.
3. `plugins/corex-core/src/Foundation/AddonProviderRegistry.php` — an `AddonProvider` entry.
4. `plugins/corex-config/src/Addons/AddonRegistry.php` — the admin manifest card.
5. `plugins/corex-core/src/Admin/AdminPage.php::sectionMeta()` — or the screen renders with a dead
   rail, plus an icon mask in the admin shell CSS.

Plus a `wp/wp-content/plugins/corex-guides` symlink for local dev, and `tests/Unit/Guides/`.

## How a site extends it

From its own provider's `boot()`, using the idiom `packages/cli/stubs/option-page.stub` teaches:

```php
Corex::make(GuideRegistry::class)->registerDeferred(static fn (): array => [
    Guide::for('perego-projects', __('Managing projects', 'perego'))
        ->inSection('content')
        ->onScreen('edit.php?post_type=perego_project')
        ->requiring('edit_posts')
        ->withTopic(…),
]);
```

Documented at `docs-app/src/content/docs/guides/user-guides.md`, scaffolded by
`wp corex make:guide` (mirroring `packages/cli/src/Generators/OptionPageGenerator.php`).

## Screenshots and dist

Captured by `tests/e2e/capture-guide-screenshots.mjs`, built on the harness spec 076 established and
spec 083 reused. One documented command; fails loudly on a missing screen or control.

**Worth stating before it is discovered later:** `scripts/build-shared-host-dist.mjs:116` copies
every `addons/*` folder into `dist/`, so screenshots under the add-on ship to every client site.
That is the right trade — the guide has to work in-admin on a client's server with no internet — but
the size is real, so captures stay viewport-sized PNGs and the set stays small.

## Verification

- Unit: registration, replacement by id, deferred resolution, the recursion guard, the `instanceof`
  filter, capability gating in both directions.
- Integration: **a second plugin registering on `plugins_loaded` at default priority appears** —
  SC-001, the race this design exists for, tested rather than argued.
- Playwright: the screen renders, search filters, a help tab appears on a declaring screen (spec 097
  replaced this with its absence, and with the Guides-screen link), and a guide requiring an absent
  capability is not listed for a subscriber.
- Guards: `wp-guard`, `clean-code-guard`, `test-guard`, `docs-guard`.
- `specs/082-dashboard-user-manual/spec.md` marked superseded; a `DECISIONS.md` entry on why a
  registry beat a docs page.
