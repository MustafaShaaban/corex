<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\I18n;

defined('ABSPATH') || exit;

/**
 * Finds the script translations CoreX ships (spec 121, US-NFR-02, DECISIONS #466).
 *
 * WordPress looks for a script's translations in its own languages folder, under a name made from
 * the md5 of the script's path inside its plug-in. CoreX's are written into corex-core's
 * `languages/` folder under that same name when the bundles are built. When WordPress's folder has
 * no file for a CoreX script, this answers with CoreX's; a language pack a site installs itself
 * still wins, because its file exists first.
 */
final class ScriptTranslations
{
    /** `corex-<locale>-<md5>.json`, the name WordPress gives a script's translations. */
    private const FILE_NAME = '/^corex-[A-Za-z_]+-[0-9a-f]{32}\.json$/';

    public function __construct(private readonly string $directory)
    {
    }

    /**
     * @param string|false $file   The file WordPress is about to read.
     * @param string       $handle The script.
     * @param string       $domain Its text domain.
     * @return string|false
     */
    public function locate(string|false $file, string $handle, string $domain): string|false
    {
        if ($domain !== 'corex' || ! is_string($file) || is_readable($file)) {
            return $file;
        }

        $name = basename($file);
        if (preg_match(self::FILE_NAME, $name) !== 1) {
            return $file;
        }

        $shipped = $this->directory . '/' . $name;

        return is_readable($shipped) ? $shipped : $file;
    }
}
