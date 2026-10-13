<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Language;

defined('ABSPATH') || exit;

/**
 * The language an email is drawn in: which way its text runs, what its `lang` says, and what
 * the strings CoreX wrote into it are translated to.
 *
 * It is not the language of the request that draws it. WordPress answers `is_rtl()`,
 * `get_bloginfo('language')` and every translated string for the request, and in the admin a
 * request is in the signed-in person's own profile language. An email is read by somebody
 * else.
 */
interface EmailLanguage
{
    /**
     * Draw in the email's language, and leave the request in its own.
     *
     * @template T
     *
     * @param callable():T $draw
     *
     * @return T
     */
    public function during(callable $draw): mixed;
}
