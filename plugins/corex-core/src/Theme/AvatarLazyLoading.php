<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Theme;

defined('ABSPATH') || exit;

/**
 * Loads an avatar lazily on a page drawn by the CoreX theme (spec 121, T031; spec 116 FR-020).
 *
 * WordPress leaves `loading` off the first images inside the main loop, taking them for the first
 * screen's main image. On a single post the first one can be a comment's avatar, which the heading
 * scale of spec 121 puts below a phone's first screen, so it loaded at once. An avatar is never a
 * page's main image: it is lazy, without a high fetch priority, unless its caller asked for a
 * loading value or the site turned lazy loading off.
 *
 * Only while the CoreX theme is the active one, like {@see ThemeDefaultPresets}: a client's own
 * theme keeps WordPress's answer.
 */
final class AvatarLazyLoading
{
    /** The context `get_avatar()` asks WordPress's loading optimisation with. */
    public const CONTEXT = 'get_avatar';

    /**
     * The `wp_get_loading_optimization_attributes` filter.
     *
     * @param array<string,string> $loading WordPress's answer: `loading`, `fetchpriority`, `decoding`.
     * @param string               $tag     The element, `img` for an avatar.
     * @param array<string,mixed>  $image   The image's own attributes, as its caller gave them.
     * @param string               $context Where the image is drawn.
     * @return array<string,string>
     */
    public function filter(array $loading, string $tag, array $image, string $context): array
    {
        if (
            $context !== self::CONTEXT
            || isset($image['loading'])
            || get_template() !== ThemeDefaultPresets::THEME
            || ! wp_lazy_loading_enabled($tag, $context)
        ) {
            return $loading;
        }

        unset($loading['fetchpriority']);
        $loading['loading'] = 'lazy';

        return $loading;
    }
}
