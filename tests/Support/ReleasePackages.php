<?php

/**
 * Release packages for tests: a description as the builder writes it, and a real zip in the temp
 * folder that holds it (spec 107).
 *
 * @package Corex\Tests\Support
 */

declare(strict_types=1);

namespace Corex\Tests\Support;

use ZipArchive;

final class ReleasePackages
{
    /** The folders the described release owns. */
    public const PATHS = [
        'wp-content/plugins/acme-site',
        'wp-content/plugins/corex-core',
        'wp-content/themes/corex',
        'wp-content/vendor',
    ];

    /** @var list<string> */
    private static array $made = [];

    /**
     * A description as the builder writes it, with some of it changed.
     *
     * @param array<string,mixed> $changes Keys to replace; a null value removes the key.
     *
     * @return array<string,mixed>
     */
    public static function description(array $changes = []): array
    {
        $described = array_merge([
            'name'              => 'corex-shared-host-dist',
            'schema'            => 2,
            'built_at'          => '2026-10-08T18:00:00.000Z',
            'corex_version'     => '0.44.0',
            'client'            => 'acme',
            'plugins'           => ['acme-site', 'corex-core'],
            'themes'            => ['corex'],
            'requires'          => ['php' => '8.3', 'wordpress' => '7.0'],
            'wordpress_version' => '7.1.3',
            'release_paths'     => self::PATHS,
            'contents'          => [
                'wp-content/plugins/acme-site'  => ['files' => 2, 'bytes' => 40, 'hash' => str_repeat('a', 64)],
                'wp-content/plugins/corex-core' => ['files' => 9, 'bytes' => 900, 'hash' => str_repeat('b', 64)],
                'wp-content/themes/corex'       => ['files' => 4, 'bytes' => 400, 'hash' => str_repeat('c', 64)],
                'wp-content/vendor'             => ['files' => 30, 'bytes' => 3000, 'hash' => str_repeat('d', 64)],
            ],
            'autoload' => ['file' => 'wp-content/vendor/autoload.php', 'psr4' => ['Corex\\' => 'wp-content/plugins/corex-core/src/']],
        ], $changes);

        return array_filter($described, static fn (mixed $value): bool => $value !== null);
    }

    /**
     * A zip in the temp folder holding the given entries, by name.
     *
     * @param array<string,string> $entries Each entry's name and what it holds.
     */
    public static function zip(array $entries): string
    {
        $file = tempnam(sys_get_temp_dir(), 'corex-release-') . '.zip';
        $zip  = new ZipArchive();
        $zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($entries as $name => $holds) {
            $zip->addFromString($name, $holds);
        }

        $zip->close();
        self::$made[] = $file;

        return $file;
    }

    /**
     * A package as the builder makes one: its description at the top, and a file in every folder
     * it says it holds.
     *
     * @param array<string,mixed>  $changes Changes to the description.
     * @param array<string,string> $more    Entries to add, or to replace (an empty string removes nothing: use `without`).
     * @param list<string>         $without Release paths to leave out of the zip.
     */
    public static function package(array $changes = [], array $more = [], array $without = []): string
    {
        $described = self::description($changes);
        $entries   = ['corex-release.json' => (string) json_encode($described)];

        foreach ((array) ($described['release_paths'] ?? []) as $path) {
            if (! in_array($path, $without, true)) {
                $entries[$path . '/index.php'] = '<?php // ' . $path;
            }
        }

        return self::zip(array_merge($entries, $more));
    }

    /** Remove every zip this made, and the empty file `tempnam()` left beside each. */
    public static function clean(): void
    {
        foreach (self::$made as $file) {
            @unlink($file);
            @unlink(substr($file, 0, -4));
        }

        self::$made = [];
    }
}
