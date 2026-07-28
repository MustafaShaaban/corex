<?php

/**
 * @package Corex\Guides
 */

declare(strict_types=1);

namespace Corex\Guides;

defined('ABSPATH') || exit;

/**
 * Every registered guide, from Corex and from whatever site is built on it (spec 084, FR-001–FR-005).
 *
 * ## Why `registerDeferred()` is not optional
 *
 * This registry's whole purpose is to accept contributions from plugins Corex has never heard of,
 * and the ordering makes that hostile. Corex boots on `plugins_loaded` at priority 10; a site plugin
 * following the generated starter (`packages/cli/stubs/starter/plugin.stub`) boots on
 * `plugins_loaded` at priority 10 too. Whichever WordPress happens to load first wins, and it varies
 * with the plugin's directory name.
 *
 * So nothing is resolved until something reads the registry, which is an admin request — long after
 * every plugin has loaded. This is the shape of `Corex\Config\Data\DataRegistry`, and it exists
 * there because this exact bug was already shipped once: a registry built during boot froze before
 * add-ons could declare their tables, and the newsletter table was invisible in wp-admin while being
 * perfectly visible to WP-CLI, which resolves things later. Same trap, worse audience.
 *
 * ## Why the filter results are sieved
 *
 * `corex_guides` is the seam for callers with no container access. Anything that comes back and is
 * not a `Guide` is dropped rather than rendered — the `Cache\CacheRegistry` precedent, and the
 * reason there is the reason here: this screen is what somebody opens when they are already stuck,
 * and a fatal on the help page is the worst possible moment for one.
 */
final class GuideRegistry
{
    /** @var array<string,Guide> keyed by id, so a site can replace a Corex guide by re-registering it */
    private array $guides = [];

    /** @var list<callable():list<Guide>> */
    private array $deferred = [];

    private bool $resolved = false;

    public function register(Guide $guide): void
    {
        $this->guides[$guide->id] = $guide;
    }

    /**
     * Register guides that cannot be built yet, to be built the first time the registry is read.
     *
     * This is the method a site plugin should use. `register()` works too, but only if the caller
     * can be certain it runs after Corex has bound the registry — and a plugin cannot be certain of
     * that from `plugins_loaded`.
     *
     * **Keep the factory cheap: it runs on every admin page load.** {@see ContextualHelp} reads the
     * registry on `current_screen` to decide whether the screen has a help tab, so resolution
     * happens once per admin request, not once per visit to the Guides screen. Guides are value
     * objects and building them costs nothing; a factory that queries the database or reads a file
     * would put that cost on every page in wp-admin. If a guide's content genuinely has to come
     * from storage, cache it — the registry deliberately does not, because it cannot know how long
     * somebody else's data stays fresh.
     *
     * @param callable():list<Guide> $factory
     */
    public function registerDeferred(callable $factory): void
    {
        $this->deferred[] = $factory;
        $this->resolved   = false;
    }

    /**
     * Every registered guide, in section then order then title sequence.
     *
     * **Sections therefore come out alphabetically by key**, which is the only lever a site has
     * over where its own section appears and is not guessable from the API. Deliberately not a
     * separate section-order registry: that would be a second thing to register, a second thing to
     * get wrong, and a second thing for two plugins to disagree about. A site that wants its
     * section first names the key accordingly.
     *
     * @return list<Guide>
     */
    public function all(): array
    {
        $this->resolveDeferred();

        $guides = array_values($this->guides);

        usort($guides, static function (Guide $a, Guide $b): int {
            return [$a->section, $a->order, $a->title] <=> [$b->section, $b->order, $b->title];
        });

        return $guides;
    }

    /**
     * Every guide the current user should actually be offered (FR-009).
     *
     * @return list<Guide>
     */
    public function available(): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (Guide $guide): bool => $guide->isAvailableTo(),
        ));
    }

    public function find(string $id): ?Guide
    {
        $this->resolveDeferred();

        return $this->guides[$id] ?? null;
    }

    /**
     * Available guides grouped by section, sections in first-seen order.
     *
     * @return array<string,list<Guide>>
     */
    public function bySection(): array
    {
        $grouped = [];

        foreach ($this->available() as $guide) {
            $grouped[$guide->section][] = $guide;
        }

        return $grouped;
    }

    /**
     * The available guides that describe a given admin screen (FR-013).
     *
     * @return list<Guide>
     */
    public function forScreen(string $screen): array
    {
        return array_values(array_filter(
            $this->available(),
            static fn (Guide $guide): bool => $guide->screen !== '' && $guide->screen === $screen,
        ));
    }

    private function resolveDeferred(): void
    {
        if ($this->resolved) {
            return;
        }

        // Set before the factories run: a factory that reads the registry back — a site guide that
        // links to a Corex one, say — would otherwise recurse until the request died.
        $this->resolved = true;

        foreach ($this->deferred as $factory) {
            foreach ($factory() as $guide) {
                $this->register($guide);
            }
        }

        $this->applyFilter();
    }

    /**
     * The escape hatch for callers with no container: `add_filter('corex_guides', …)`.
     */
    private function applyFilter(): void
    {
        /**
         * Filters the registered guides after deferred factories have resolved.
         *
         * @param list<Guide> $guides The guides registered so far.
         */
        $filtered = apply_filters('corex_guides', array_values($this->guides));

        if (! is_array($filtered)) {
            return;
        }

        $this->guides = [];

        foreach ($filtered as $guide) {
            if ($guide instanceof Guide) {
                $this->guides[$guide->id] = $guide;
            }
        }
    }
}
