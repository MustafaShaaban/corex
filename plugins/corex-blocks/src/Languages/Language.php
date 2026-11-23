<?php

/**
 * @package Corex\Blocks
 */

declare(strict_types=1);

namespace Corex\Blocks\Languages;

defined('ABSPATH') || exit;

/**
 * One language of the site, as the request sees it (spec 137, FR-029): what a language switcher
 * needs to name it and to send a visitor to it.
 */
final class Language
{
    /** `ltr` or `rtl`; any other value is read as `ltr`. */
    public readonly string $direction;

    /**
     * @param string $code      Its BCP 47 tag (`en-US`, `ar`), written as `lang` and `hreflang`.
     * @param string $name      Its name in its own language and script (`English`, `العربية`).
     * @param string $direction `ltr` or `rtl`.
     * @param string $url       This page in that language, or else that language's home page.
     * @param bool   $current   Whether it is the language of the page being shown.
     */
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        string $direction,
        public readonly string $url,
        public readonly bool $current,
    ) {
        $this->direction = strtolower($direction) === 'rtl' ? 'rtl' : 'ltr';
    }
}
