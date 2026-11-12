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
 * What is recorded is what CoreX knows: which plugins it asked, and which of them said no. A purge
 * that throws, answers `false`, or answers WP Fastest Cache's `['success' => false]` is reported as
 * failed; anything else is reported as asked, not as cleared, because a plugin can return early
 * without saying so (SiteGround with its caches off, WP Rocket during an import or on a second
 * purge in one request). SiteGround's `false` is left out of the failures: with its file cache on,
 * it answers `false` after clearing, because its last step looks for the directory its first step
 * removed. A failed purge never stops the other purges, and cannot undo the change: the new mode is
 * stored before the store announces it (DECISIONS #625).
 */
final class PageCachePurge
{
    /** Before the activity record of the change (WordPress's default, 10), so the record can say what was asked. */
    public const PRIORITY = 5;

    /** Stands for this site's id on a network (each site has its own mode), and 0, the whole cache, on a single site. */
    public const THIS_SITE = '{this-site}';

    /**
     * Stands for this site's home address, `home_url('/')`. SiteGround purges that address and
     * everything below it; with no address it purges `get_home_url('/')`, the main site of a network.
     */
    public const THIS_SITE_URL = '{this-site-url}';

    /**
     * Each plugin's key (its directory on WordPress.org), product name, and purge: a function to
     * call or an action to fire, with its arguments, and whether its `false` means it failed.
     *
     * @var array<string,array{label:string,function?:string,action?:string,args?:list<mixed>,falseIsFailure?:bool}>
     */
    public const PLUGINS = [
        'wp-rocket'        => ['label' => 'WP Rocket', 'function' => 'rocket_clean_domain'],
        'w3-total-cache'   => ['label' => 'W3 Total Cache', 'function' => 'w3tc_flush_posts'],
        'litespeed-cache'  => ['label' => 'LiteSpeed Cache', 'action' => 'litespeed_purge', 'args' => ['*']],
        'wp-super-cache'   => ['label' => 'WP Super Cache', 'function' => 'wp_cache_clear_cache', 'args' => [self::THIS_SITE]],
        'wp-fastest-cache' => ['label' => 'WP Fastest Cache', 'function' => 'wpfc_clear_all_cache'],
        'sg-cachepress'    => ['label' => 'SiteGround Optimizer', 'function' => 'sg_cachepress_purge_cache', 'args' => [self::THIS_SITE_URL], 'falseIsFailure' => false],
    ];

    /** @var array{asked:list<string>,failed:list<string>}|null */
    private ?array $last = null;

    /**
     * @param array<string,array{label:string,function?:string,action?:string,args?:list<mixed>,falseIsFailure?:bool}> $plugins
     */
    public function __construct(private readonly array $plugins = self::PLUGINS)
    {
    }

    /** A plugin's product name, for the sentence that says what was asked. */
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
     * Which plugins the last change of mode in this request asked, and which failed; null when
     * there was none.
     *
     * @return array{asked:list<string>,failed:list<string>}|null
     */
    public function lastClearing(): ?array
    {
        return $this->last;
    }

    /**
     * Ask every active page-cache plugin to clear its pages.
     *
     * @return array{asked:list<string>,failed:list<string>}
     */
    public function clear(): array
    {
        $asked  = [];
        $failed = [];

        foreach ($this->plugins as $key => $plugin) {
            if (! $this->isActive($plugin)) {
                continue;
            }

            try {
                $refused = $this->refused($plugin, $this->purge($plugin));
            } catch (Throwable) {
                $refused = true;
            }

            if ($refused) {
                $failed[] = (string) $key;
            } else {
                $asked[] = (string) $key;
            }
        }

        return ['asked' => $asked, 'failed' => $failed];
    }

    /**
     * Whether a purge's answer says it failed.
     *
     * @param array{label:string,function?:string,action?:string,args?:list<mixed>,falseIsFailure?:bool} $plugin
     */
    private function refused(array $plugin, mixed $answer): bool
    {
        if ($answer === false) {
            return $plugin['falseIsFailure'] ?? true;
        }

        return is_array($answer) && array_key_exists('success', $answer) && $answer['success'] === false;
    }

    /**
     * @param array{label:string,function?:string,action?:string,args?:list<mixed>,falseIsFailure?:bool} $plugin
     */
    private function isActive(array $plugin): bool
    {
        if (isset($plugin['function'])) {
            return function_exists($plugin['function']);
        }

        return isset($plugin['action']) && has_action($plugin['action']) !== false;
    }

    /**
     * The purge's answer; null for an action, which answers nothing.
     *
     * @param array{label:string,function?:string,action?:string,args?:list<mixed>,falseIsFailure?:bool} $plugin
     */
    private function purge(array $plugin): mixed
    {
        $args = array_map(
            static fn (mixed $arg): mixed => match ($arg) {
                self::THIS_SITE     => is_multisite() ? get_current_blog_id() : 0,
                self::THIS_SITE_URL => home_url('/'),
                default             => $arg,
            },
            $plugin['args'] ?? [],
        );

        if (isset($plugin['function'])) {
            return ($plugin['function'])(...$args);
        }

        do_action((string) $plugin['action'], ...$args);

        return null;
    }
}
