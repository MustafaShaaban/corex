<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Operations;

defined('ABSPATH') || exit;

/**
 * What the coming-soon page leaves out of its `<head>`.
 *
 * The page tells a visitor the site is not ready. WordPress's defaults would have it also say
 * which WordPress it runs, where its remote-editing endpoint is, and offer feeds whose addresses
 * the mode redirects straight back to the page. The emoji loader goes with them: an inline
 * script on a page that prints no emoji of its own.
 *
 * Only for a request that is being served the page. A visitor let through to the real site, and
 * the real site after launch, get WordPress's head as the site has it.
 */
final class ComingSoonHead
{
    /** WordPress's own `wp_head` callbacks, with the priority each is registered at. */
    private const LEFT_OUT = [
        'wp_generator'                 => 10,
        'rsd_link'                     => 10,
        'feed_links'                   => 2,
        'feed_links_extra'             => 3,
        'print_emoji_detection_script' => 7,
    ];

    public static function quiet(): void
    {
        foreach (self::LEFT_OUT as $callback => $priority) {
            remove_action('wp_head', $callback, $priority);
        }

        // The emoji stylesheet is queued beside the loader it styles.
        remove_action('wp_enqueue_scripts', 'wp_enqueue_emoji_styles');
        remove_action('wp_print_styles', 'print_emoji_styles');
    }
}
