<?php

/**
 * @package Corex\Guides
 */

declare(strict_types=1);

namespace Corex\Guides;

defined('ABSPATH') || exit;

use Corex\Foundation\ServiceProvider;
use Corex\Guides\Corex\CorexGuides;

/**
 * Boots the guide registry, Corex's own guides, and the surfaces that render them (spec 084).
 *
 * The registry is bound as a singleton in `register()` and populated in `boot()`, which is the
 * ordering the two-pass provider lifecycle exists for: every provider has been registered before any
 * provider boots, so an add-on contributing guides can resolve the registry without caring whether
 * this one loaded first.
 *
 * **A site plugin is the intended second caller, not an afterthought.** It reaches the registry
 * through `Corex::make(GuideRegistry::class)` from its own `plugins_loaded` boot and calls
 * `registerDeferred()` — see `docs-app/src/content/docs/guides/user-guides.md`. Nothing in Corex
 * knows or needs to know that it did.
 */
final class GuidesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(GuideRegistry::class);
    }

    public function boot(): void
    {
        // Deferred rather than immediate, for the same reason a site plugin's guides are: the
        // registry must not be read during boot, or it freezes before the plugins that extend it
        // have run. Corex's own guides go through the public seam so the seam is exercised by the
        // framework itself and cannot rot unnoticed.
        $this->container->make(GuideRegistry::class)->registerDeferred(
            static fn (): array => CorexGuides::all(),
        );

        if (! is_admin()) {
            return;
        }

        $this->container->make(GuidesScreen::class)->register();
        $this->container->make(ContextualHelp::class)->register();
    }
}
