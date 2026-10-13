<?php

/**
 * @package Corex\Profile
 */

declare(strict_types=1);

namespace Corex\Profile\Support;

defined('ABSPATH') || exit;

/**
 * An instant as the site writes one for a visitor: the date and the time in the formats set
 * under Settings → General, in the site's timezone and language.
 *
 * The account block is a page of the site, and the theme prints a post's date beside it by
 * the same settings. CoreX's admin screens have a fixed format of their own
 * (`Corex\Support\DateTime\AdminDateTime`, spec 076), which is the operator's and not the
 * visitor's (DECISIONS #348).
 */
final class SiteDate
{
    /**
     * The two halves are joined by a pattern and not by one format string, so a translator
     * can reorder them without escaping format characters.
     */
    public function dateAndTime(int $timestamp): string
    {
        return sprintf(
            /* translators: 1: a date, 2: a time, each in the site's own format. Renders "October 13, 2026 at 4:23 pm". */
            __('%1$s at %2$s', 'corex'),
            (string) wp_date((string) get_option('date_format'), $timestamp),
            (string) wp_date((string) get_option('time_format'), $timestamp),
        );
    }
}
