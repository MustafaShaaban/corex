<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Multisite;

defined('ABSPATH') || exit;

use Closure;
use Corex\Events\EventDispatcher;
use Corex\Events\ListenerProvider;
use Corex\Multisite\Events\SiteScopeSwitched;

/**
 * Treats switches and restores identically because WordPress emits switch_blog for
 * both directions with the same signature. Acting only on a changed current id is
 * symmetric, idempotent, and immune to an unbalanced WordPress switch stack.
 */
final class SiteScopeManager implements SiteScope
{
    private int $currentSiteId;

    /**
     * @var list<SiteScoped>
     */
    private array $services = [];

    public function __construct(
        private readonly MultisiteContext $multisite,
        SiteContext $site,
        private readonly EventDispatcher $events,
        private readonly ListenerProvider $listeners,
    ) {
        $this->currentSiteId = $site->id();
    }

    /**
     * Self-gated on the install shape rather than trusting the caller to check, so a
     * single-site request can never acquire a switch_blog callback however this is wired
     * (spec FR-004, SC-008). Priority zero is deliberate: every future CoreX switch_blog
     * listener must use priority 10 or later so it observes post-invalidation state.
     */
    public function listen(): void
    {
        if (! $this->multisite->enabled()) {
            return;
        }

        add_action('switch_blog', [$this, 'onSwitchBlog'], 0, 3);
    }

    public function currentSiteId(): int
    {
        return $this->currentSiteId;
    }

    public function onSwitchBlog(int $newSiteId, int $previousSiteId, string $context = ''): void
    {
        if ($newSiteId === $previousSiteId) {
            return;
        }

        $this->currentSiteId = $newSiteId;

        foreach ($this->services as $service) {
            $service->forgetSiteState($newSiteId);
        }

        if ($this->listeners->hasListenersFor(SiteScopeSwitched::class)) {
            $this->events->dispatch(new SiteScopeSwitched($newSiteId, $previousSiteId));
        }
    }

    public function register(SiteScoped $service): void
    {
        if (! in_array($service, $this->services, true)) {
            $this->services[] = $service;
        }
    }

    public function run(int $siteId, Closure $callback): mixed
    {
        if ($siteId === $this->currentSiteId) {
            return $callback();
        }

        switch_to_blog($siteId);

        try {
            return $callback();
        } finally {
            restore_current_blog();
        }
    }
}
