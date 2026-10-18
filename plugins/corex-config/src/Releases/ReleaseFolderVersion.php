<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Releases;

defined('ABSPATH') || exit;

use Closure;

/**
 * The name and version a plugin or theme folder gives in its header, read as WordPress reads
 * them (spec 107, FR-029): a plugin's from the first PHP file at the folder's top whose header
 * names the plugin, a theme's from its `style.css`.
 *
 * It is told how to read a file, so the same rule reads a folder on the site and the same
 * folder inside a package that has not been unpacked.
 */
final readonly class ReleaseFolderVersion
{
    /** As much of a file as WordPress reads for its header. */
    public const HEAD_BYTES = 8192;

    private function __construct(
        public string $name,
        public string $version,
    ) {
    }

    /**
     * The header of the folder at a release path, or none where the folder is not a plugin or a
     * theme, or its header gives no version.
     *
     * @param list<string>            $topFiles The names of the files directly in the folder.
     * @param Closure(string):?string $head     The first HEAD_BYTES of a file in the folder, by its name; null where it cannot be read.
     */
    public static function of(string $releasePath, array $topFiles, Closure $head): ?self
    {
        [$candidates, $nameField] = match (true) {
            str_starts_with($releasePath, 'wp-content/plugins/') => [self::phpFiles($topFiles), 'Plugin Name'],
            str_starts_with($releasePath, 'wp-content/themes/')  => [in_array('style.css', $topFiles, true) ? ['style.css'] : [], 'Theme Name'],
            default                                              => [[], ''],
        };

        foreach ($candidates as $file) {
            $text = $head($file);
            $name = $text === null ? '' : self::field($text, $nameField);

            if ($name !== '') {
                $version = self::field((string) $text, 'Version');

                return $version === '' ? null : new self($name, $version);
            }
        }

        return null;
    }

    /**
     * @param list<string> $files
     *
     * @return list<string> The PHP files, in name order: WordPress takes the first with a plugin's header.
     */
    private static function phpFiles(array $files): array
    {
        $php = array_values(array_filter($files, static fn (string $file): bool => str_ends_with(strtolower($file), '.php')));
        sort($php);

        return $php;
    }

    /** A header field, by the rule of WordPress's `get_file_data()`. */
    private static function field(string $text, string $field): string
    {
        $pattern = '/^(?:[ \t]*<\?php)?[ \t\/*#@]*' . preg_quote($field, '/') . ':(.*)$/mi';

        if (preg_match($pattern, str_replace("\r", "\n", $text), $match) !== 1) {
            return '';
        }

        return trim((string) preg_replace('/\s*(?:\*\/|\?>).*/', '', $match[1]));
    }
}
