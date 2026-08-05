<?php

/**
 * @package Corex\Guides
 */

declare(strict_types=1);

namespace Corex\Guides;

defined('ABSPATH') || exit;

use Corex\Container\ContainerInterface;
use Corex\Foundation\ServiceProvider;
use Corex\Guides\Corex\CorexGuides;
use Corex\Email\Template\TemplateRegistry;
use Corex\Guides\Support\SupportMailer;
use Corex\Guides\Support\SupportRequestController;
use Corex\Guides\Support\SupportRequestTemplate;
use Corex\Mail\Mailer;

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

        // The Corex Mail seam if this install has one, and null if it does not. Resolved here rather
        // than looked up inside SupportMailer, because a service that reaches into a container to
        // find its own collaborators is not injected (Principle IV) — and because corex-email is
        // optional, so `make(Mailer::class)` would be a hard dependency on an add-on (Principle IX).
        $this->container->singleton(
            SupportMailer::class,
            static fn (ContainerInterface $c): SupportMailer => new SupportMailer(
                $c->has(Mailer::class) ? $c->make(Mailer::class) : null,
                // Resolved when SupportMailer is first built — inside the `admin_post` closure,
                // long after boot — so this reflects whether the template was actually registered
                // below rather than the order two providers happened to register in.
                self::supportsTemplate($c) ? SupportRequestTemplate::NAME : null,
            ),
        );
    }

    /**
     * Whether this install can render the branded support template.
     *
     * `SupportRequestTemplate` **extends a Corex Mail class**, so merely naming it where that add-on
     * is absent fatals on the missing parent — hence `class_exists()` on the registry, which is
     * cheap and settles it before the autoloader is asked for the subclass.
     */
    private static function supportsTemplate(ContainerInterface $container): bool
    {
        return class_exists(TemplateRegistry::class) && $container->has(TemplateRegistry::class);
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

        // In `boot()`, because every provider has registered by now — including Corex Mail's, which
        // binds the registry. Doing it in `register()` would depend on which of two providers ran
        // first, and doing it inside the SupportMailer factory (as this first did) hides a side
        // effect in a lazy binding: the template was never registered at all unless something
        // happened to resolve the mailer.
        if (self::supportsTemplate($this->container)) {
            $this->container->make(TemplateRegistry::class)->register(new SupportRequestTemplate());
        }

        if (! is_admin()) {
            return;
        }

        $this->container->make(GuidesScreen::class)->register();

        // Resolved inside the closure, not at boot: `admin-post.php` is the only request that ever
        // fires this, and building the controller on every admin page load to answer a POST that
        // does not come is work done for nothing.
        add_action('admin_post_' . SupportRequestController::ACTION, function (): void {
            $this->container->make(SupportRequestController::class)->handle();
        });
    }
}
