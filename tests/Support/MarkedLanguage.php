<?php

/**
 * @package Corex\Tests\Support
 */

declare(strict_types=1);

namespace Corex\Tests\Support;

use Corex\Mail\EmailLanguage;

/**
 * An email's language that can be told from the request's: a unit test gives `said()` to its
 * stub of `__()`, and a word then says `[email] ` first when it was made inside `during()`.
 *
 *     $language = new MarkedLanguage();
 *     Functions\when('__')->alias($language->said(...));
 *
 * For holding that a word of an email is made where the email's language is asked for, and not
 * before. That the language asked for is the site's is `EmailWordsLanguageTest`, on WordPress.
 */
final class MarkedLanguage implements EmailLanguage
{
    private bool $drawing = false;

    public function during(callable $draw): mixed
    {
        $this->drawing = true;

        try {
            return $draw();
        } finally {
            $this->drawing = false;
        }
    }

    /** A string as the request would translate it now. */
    public function said(string $text): string
    {
        return $this->drawing ? '[email] ' . $text : $text;
    }
}
