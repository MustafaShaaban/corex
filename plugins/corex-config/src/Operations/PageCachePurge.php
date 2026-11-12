<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Operations;

defined('ABSPATH') || exit;

use Throwable;

/**
 * Clears the page caches a mode change would leave stale (spec 124, FR-004b, DECISIONS #524).
 *
 * The mode is in the page: `noindex` in Development and Staging ({@see ModeRobots}), the paused
 * panel in Forms paused. A page cache would keep serving the page as it was before the change: a
 * launched site still saying `noindex`, a paused one still showing its forms. So every change of
 * mode, by any route (the screen, the command line, a launch), asks each page-cache plugin that is
 * active on the site to clear its pages, through the purge that plugin offers.
 *
 * CoreX itself keeps no page or rendered output in a cache (its declared cache entries,
 * {@see \Corex\Cache\CacheRegistry}, are a build manifest, a submission count and pending previews),
 * so there is nothing of its own to clear here.
 *
 * Each name below was read in the plugin's own source (2026-11-12: WP Rocket 3.24, W3 Total Cache
 * 2.11.0, LiteSpeed Cache 7.9.3, WP Super Cache 3.1.5, WP Fastest Cache 1.5.5, SiteGround Optimizer
 * 7.8.6), and each clears the pages only, never an object cache, where CoreX's rate limits and
 * spent captcha tokens can live (spec 078). A plugin is active when its purge exists: its function
 * is defined, or its action has a listener. Cloudflare's plugin (4.14.5, read the same day) is not
 * here: it offers no purge function, and its "purge everything" is bound to a list of other
 * plugins' actions it reads before CoreX loads.
 *
 * A purge that throws is reported as not cleared and never stops the other purges. It cannot undo
 * the change either: the new mode is stored before the store announces it.
 */
final class PageCachePurge
{
    /** Before the activity record of the change (WordPress's default, 10), so the record can say what was cleared. */
    public const PRIORITY = 5;

    /** Stands for this site's id on a network (each site has its own mode), and 0, the whole cache, on a single site. */
    public const THIS_SITE = '{this-site}';

    /**
     * Each plugin's key (its directory on WordPress.org), product name, and purge: a function to
     * call or an action to fire, with its arguments.
     *
     * @var array<string,array{label:string,function?:string,action?:string,args?:list<mixed>}>
     */
    public const PLUGINS = [
        'wp-rocket'        => ['label' => 'WP Rocket', 'function' => 'rocket_clean_domain'],
        'w3-total-cache'   => ['label' => 'W3 Total Cache', 'function' => 'w3tc_flush_posts'],
        'litespeed-cache'  => ['label' => 'LiteSpeed Cache', 'action' => 'litespeed_purge', 'args' => ['*']],
        'wp-super-cache'   => ['label' => 'WP Super Cache', 'function' => 'wp_cache_clear_cache', 'args' => [self::THIS_SITE]],
        'wp-fastest-cache' => ['label' => 'WP Fastest Cache', 'function' => 'wpfc_clear_all_cache'],
        'sg-cachepress'    => ['label' => 'SiteGround Optimizer', 'function' => 'sg_cachepress_purge_cache'],
    ];

    /** @var array{cleared:list<string>,failed:list<string>}|null */
    private ?array $last = null;

    /**
     * @param array<string,array{label:string,function?:string,action?:string,args?:list<mixed>}> $plugins
     */
    public function __construct(private readonly array $plugins = self::PLUGINS)
    {
    }

    /** A plugin's product name, for the sentence that says what was cleared. */
    public static function label(string $key): string
    {
        return self::PLUGINS[$key]['label'] ?? $key;
    }

    public function register(): void
    {
        add_action('corex_operations_mode_changed', [$this, 'onModeChanged'], self::PRIORITY, 0);
    }

    public function onModeChanged(): void
    {
        $this->last = $this->clear();
    }

    /**
     * What the last change of mode in this request cleared; null when there was none.
     *
     * @return array{cleared:list<string>,failed:list<string>}|null
     */
    public function lastClearing(): ?array
    {
        return $this->last;
    }

    /**
     * Ask every active page-cache plugin to clear its pages.
     *
     * @return array{cleared:list<string>,failed:list<string>}
     */
    public function clear(): array
    {
        $cleared = [];
        $failed  = [];

        foreach ($this->plugins as $key => $plugin) {
            if (! $this->isActive($plugin)) {
                continue;
            }

            try {
                $this->purge($plugin);
                $cleared[] = (string) $key;
            } catch (Throwable) {
                $failed[] = (string) $key;
            }
        }

        return ['cleared' => $cleared, 'failed' => $failed];
    }

    /**
     * @param array{label:string,function?:string,action?:string,args?:list<mixed>} $plugin
     */
    private function isActive(array $plugin): bool
    {
        if (isset($plugin['function'])) {
            return function_exists($plugin['function']);
        }

        return isset($plugin['action']) && has_action($plugin['action']) !== false;
    }

    /**
     * @param array{label:string,function?:string,action?:string,args?:list<mixed>} $plugin
     */
    private function purge(array $plugin): void
    {
        $args = array_map(
            static fn (mixed $arg): mixed => $arg === self::THIS_SITE
                ? (is_multisite() ? get_current_blog_id() : 0)
                : $arg,
            $plugin['args'] ?? [],
        );

        if (isset($plugin['function'])) {
            ($plugin['function'])(...$args);

            return;
        }

        do_action((string) $plugin['action'], ...$args);
    }
}
