<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Multisite;

defined('ABSPATH') || exit;

use Corex\Container\Container;
use Corex\Events\EventDispatcher;
use Corex\Events\ListenerProvider;
use Corex\Support\BootLogger;

/**
 * Carries the primitives built before the application container exists (spec FR-003).
 *
 * The multisite scope constructed here is the exact instance seeded into the
 * container. Its temporary event dispatcher and listener provider are distinct
 * from any later container-resolved event bus, so listeners registered through the
 * container cannot observe SiteScopeSwitched from this scope. The later runtime
 * wiring re-points that relationship; this note prevents the two buses being
 * mistaken for one in the composition root.
 */
final class RuntimeContexts
{
    public function __construct(
        public readonly MultisiteContext $multisite,
        public readonly SiteContext $site,
        public readonly NetworkContext $network,
        public readonly NetworkCapabilities $capabilities,
        public readonly SiteScope $scope,
    ) {
    }

    public static function detect(): self
    {
        $multisiteEnabled = function_exists('is_multisite') && is_multisite();

        if (! $multisiteEnabled) {
            return new self(
                new SingleSiteMultisiteContext(),
                new SingleSiteContext(),
                new SingleSiteNetworkContext(),
                new SingleSiteCapabilities(),
                new SingleSiteScope(),
            );
        }

        $multisite = new WpMultisiteContext();
        $site = new WpSiteContext();
        $listeners = new ListenerProvider();
        $events = new EventDispatcher($listeners, new BootLogger(false));

        return new self(
            $multisite,
            $site,
            new WpNetworkContext(),
            new WpNetworkCapabilities(),
            new SiteScopeManager($multisite, $site, $events, $listeners),
        );
    }

    public function seedInto(Container $container): void
    {
        $container->instance(MultisiteContext::class, $this->multisite);
        $container->instance(SiteContext::class, $this->site);
        $container->instance(NetworkContext::class, $this->network);
        $container->instance(NetworkCapabilities::class, $this->capabilities);
        $container->instance(SiteScope::class, $this->scope);
    }
}
