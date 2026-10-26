<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\I18n;

defined('ABSPATH') || exit;

/**
 * Loads the `corex` text domain on `init` (spec 036; spec 121, DECISIONS #466).
 *
 * `load_plugin_textdomain()` only names the folder; WordPress loads the file the first time a
 * string is asked for. When a `corex` string was asked for before that, WordPress keeps an empty
 * catalogue for the domain for the rest of the request. The CoreX theme shares the domain and is
 * asked first, so on an Arabic site with the CoreX theme every CoreX string stayed English. This
 * names the folder as before and then, when the domain is still not loaded, loads the catalogue
 * itself: a language pack the site installed in `wp-content/languages/plugins` first, the one
 * CoreX ships second.
 */
final class Catalogue
{
    public const DOMAIN = 'corex';

    /**
     * @param string $directory    CoreX's `languages` folder.
     * @param string $relativePath The same folder relative to the plug-ins folder, for WordPress.
     * @param string $packs        WordPress's folder for plug-in language packs.
     */
    public function __construct(
        private readonly string $directory,
        private readonly string $relativePath,
        private readonly string $packs,
    ) {
    }

    public function load(): void
    {
        load_plugin_textdomain(self::DOMAIN, false, $this->relativePath);

        if (is_textdomain_loaded(self::DOMAIN)) {
            return;
        }

        $locale = determine_locale();
        $name = self::DOMAIN . '-' . $locale . '.mo';

        foreach ([$this->packs . '/' . $name, $this->directory . '/' . $name] as $file) {
            if (is_readable($file) && load_textdomain(self::DOMAIN, $file, $locale)) {
                return;
            }
        }
    }
}
