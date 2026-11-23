<?php

/**
 * @package Corex\Blocks
 */

declare(strict_types=1);

namespace Corex\Blocks\Languages;

defined('ABSPATH') || exit;

use Locale;

/**
 * The language source CoreX ships (spec 137, FR-030; DECISIONS #694): WordPress alone, which has one
 * language, so a language switcher over it prints nothing.
 *
 * It reads the request's locale and direction from WordPress and names the language in its own script
 * with PHP's `intl` extension when the server has it (the language code otherwise). It calls no
 * multilingual plugin and looks for none (constitution IX), requests nothing over the network, and
 * finds no page, term or menu by its slug (FR-042).
 */
final class WordPressLanguageSource implements LanguageSource
{
    /**
     * @param bool|null $hasIntl Whether to name a language with PHP's `intl` extension; null asks PHP
     *                           whether it is loaded. The container leaves it null.
     */
    public function __construct(private readonly ?bool $hasIntl = null)
    {
    }

    public function languages(): array
    {
        $locale = determine_locale();

        return [
            new Language(
                str_replace('_', '-', $locale),
                $this->nativeName($locale),
                is_rtl() ? 'rtl' : 'ltr',
                $this->thisPage(),
                true,
            ),
        ];
    }

    /** The language's name in its own language (`English`, `العربية`), or its code without `intl`. */
    private function nativeName(string $locale): string
    {
        $language = strtolower((string) strtok($locale, '_-'));
        $name = ($this->hasIntl ?? extension_loaded('intl')) ? (string) Locale::getDisplayLanguage($language, $language) : '';

        if ($name === '' || $name === $language) {
            return strtoupper($language);
        }

        return extension_loaded('mbstring')
            ? mb_strtoupper(mb_substr($name, 0, 1)) . mb_substr($name, 1)
            : $name;
    }

    /**
     * The address of the page asked for: on the site's own host, from the request's path, as
     * WordPress's `get_self_link()` builds it. With no page asked for (the command line, a cron run),
     * the home page.
     */
    private function thisPage(): string
    {
        return isset($_SERVER['REQUEST_URI']) ? get_self_link() : home_url('/');
    }
}
