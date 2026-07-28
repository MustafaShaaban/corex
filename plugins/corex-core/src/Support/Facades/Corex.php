<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Support\Facades;

defined('ABSPATH') || exit;

use Corex\Boot;

/**
 * Bounded global container accessor (spec FR-008a).
 *
 * For framework-boundary use only — hook callbacks and WP-CLI/cron bootstrap where
 * constructor injection cannot reach. Application services and controllers MUST
 * receive their dependencies via constructor injection, never through this facade.
 */
final class Corex
{
    /**
     * @param array<string, mixed> $parameters
     */
    public static function make(string $id, array $parameters = []): mixed
    {
        return Boot::app()->container()->make($id, $parameters);
    }

    /**
     * Run something once Corex is up — the safe way for a site plugin to reach the container.
     *
     * **This exists because the obvious way is a coin flip that ends in a white screen.** Corex
     * boots on `plugins_loaded` at priority 10, and the site starter this framework generates boots
     * there too. Which one WordPress runs first depends on the plugin's *directory name*. The
     * plugin that loses calls {@see make()}, reaches `Boot::app()`, and gets a `RuntimeException`
     * on every request — the whole site, not just the feature.
     *
     * Nor could a site guard against it: `app()` throws rather than returning null, so
     * `Boot::app() === null` — the check a developer naturally writes — *is* the crash. Found by
     * standing up a plugin that followed the documented pattern exactly.
     *
     * Both orderings are covered here: already booted runs immediately, not yet booted waits for
     * `corex_booted`. There is no ordering left for a caller to get wrong.
     *
     *     add_action( 'plugins_loaded', static function (): void {
     *         Corex::onReady( static function (): void {
     *             Corex::make( GuideRegistry::class )->registerDeferred( … );
     *         } );
     *     } );
     *
     * @param callable(\Corex\Foundation\Application):void $callback
     */
    public static function onReady(callable $callback): void
    {
        if (Boot::booted()) {
            $callback(Boot::app());

            return;
        }

        add_action('corex_booted', $callback, 10, 1);
    }
}
