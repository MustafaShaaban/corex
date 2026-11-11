<?php

/**
 * @package Corex\Tests\Support
 */

declare(strict_types=1);

namespace Corex\Tests\Support;

use Corex\Email\Design\EmailPalette;

/**
 * The accent a site's emails are drawn in when its Mail settings name none: the brand's own
 * primary colour, read from the theme as the frame reads it (spec 120, story 3).
 */
final class BrandAccent
{
    public static function primary(): string
    {
        foreach ((array) (wp_get_global_settings(['color', 'palette'])['theme'] ?? []) as $entry) {
            if (is_array($entry) && ($entry['slug'] ?? '') === 'primary') {
                return strtolower((string) $entry['color']);
            }
        }

        return EmailPalette::light()->accent;
    }
}
