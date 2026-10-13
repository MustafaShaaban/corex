<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Mail;

defined('ABSPATH') || exit;

/**
 * The language an email is drawn in: which way its text runs, what its `lang` says, and what
 * the strings CoreX wrote into it are translated to.
 *
 * It is not the language of the request that draws it. WordPress answers `is_rtl()`,
 * `get_bloginfo('language')` and every translated string for the request, and in the admin a
 * request is in the signed-in person's own profile language. An email is read by somebody
 * else.
 *
 * Whatever writes a word of an email asks here, and not only what draws its design: a subject
 * or a line of a body translated before a mailer has it is in the request's language for good
 * (DECISIONS #362). It lives in the core for that reason. A form's notification and a Guides
 * support message are written on a site without CoreX Mail as well.
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
