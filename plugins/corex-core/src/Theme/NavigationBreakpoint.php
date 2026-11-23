<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Theme;

defined('ABSPATH') || exit;

/**
 * The width a navigation breakpoint token stands for (spec 137, FR-004; DECISIONS #692).
 *
 * The token is `settings.custom.nav.breakpoint` in the active theme's merged `theme.json`: the one
 * width at which a CoreX header changes between its desktop and its phone form. It becomes part of
 * a media condition, so only a whole or decimal number of `px` is taken, written back from the
 * number itself, and nothing a theme wrote reaches the page as CSS. Anything else, a number too
 * large to be finite included, is the CoreX theme's own width. A width below WordPress's own
 * collapse point is read as that point: WordPress folds its Navigation block below it, and a
 * narrower switch is not supported.
 */
final class NavigationBreakpoint
{
    /** The CoreX theme's token, and the width `corex-navigation-collapse.css` is written with. */
    public const DEFAULT = '782px';

    /** Where WordPress folds the Navigation block (`wp-includes/blocks/navigation/style.css`). */
    public const FLOOR = '600px';

    public function width(mixed $token): string
    {
        if (! is_string($token) || preg_match('/^(\d+(?:\.\d+)?)px$/D', $token, $match) !== 1) {
            return self::DEFAULT;
        }

        $number = (float) $match[1];

        // Four hundred digits are still digits, and a float makes them INF, which is no width.
        if (! is_finite($number)) {
            return self::DEFAULT;
        }

        $number = max($number, (float) self::FLOOR);

        return rtrim(rtrim(sprintf('%.4F', $number), '0'), '.') . 'px';
    }

    public function isDefault(string $width): bool
    {
        return $width === self::DEFAULT;
    }
}
