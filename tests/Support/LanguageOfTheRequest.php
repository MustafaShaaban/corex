<?php

/**
 * @package Corex\Tests\Support
 */

declare(strict_types=1);

namespace Corex\Tests\Support;

use Corex\Email\Language\EmailLanguage;

/**
 * Draws an email in whatever language the request is in: for a unit test of what an email is
 * drawn as, where there is no WordPress to have a language. That the site's language is the
 * one used is `EmailLanguageTest`, against WordPress.
 */
final class LanguageOfTheRequest implements EmailLanguage
{
    public function during(callable $draw): mixed
    {
        return $draw();
    }
}
