<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Language;

defined('ABSPATH') || exit;

/**
 * An email is drawn in the site's language (DECISIONS #356).
 *
 * The site's language is the one its visitors read and the one its emails are written for.
 * The switch is WordPress's own, the one it makes for the emails it sends itself: the
 * direction, the language and the translations all follow it, and all come back.
 */
final class SiteLanguage implements EmailLanguage
{
    public function during(callable $draw): mixed
    {
        // WordPress answers false where there is nothing to switch: the request is in the
        // site's language already, or somebody has switched it on purpose, or the site's
        // language has no files. Then there is nothing to put back either.
        $switched = switch_to_locale(get_locale());

        try {
            return $draw();
        } finally {
            if ($switched) {
                restore_previous_locale();
            }
        }
    }
}
