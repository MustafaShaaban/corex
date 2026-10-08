<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Releases;

defined('ABSPATH') || exit;

use DateTimeImmutable;
use Exception;

/**
 * What a release package says it is: `corex-release.json`, as the builder writes it from
 * schema 2 on (spec 107, plan D2).
 *
 * Reading it is the first check a package gets. Everything a site later does with a package
 * (which folders it unpacks, what it compares them with, where they go) comes from here, so a
 * description that names a folder a release does not own is refused, whatever else it says.
 */
final readonly class ReleaseManifest
{
    public const SCHEMA = 2;

    private const NAME = 'corex-shared-host-dist';

    /**
     * The only folders a release owns, relative to the site: one plugin, one theme, the
     * command-line package, the shared code. Never a folder that holds other things.
     */
    private const OWNED = '#^wp-content/(?:(?:plugins|themes)/[A-Za-z0-9][A-Za-z0-9._-]*|packages|vendor)$#';

    /**
     * @param list<string>                                             $releasePaths     Folders the release owns, relative to the site.
     * @param array<string,array{files:int,bytes:int,hash:string}>     $contents         What each holds, by path.
     * @param list<string>                                             $namespaceFolders Folders the autoloader maps a namespace to.
     */
    private function __construct(
        public string $version,
        public DateTimeImmutable $builtAt,
        public ?string $client,
        public string $requiresPhp,
        public string $requiresWordPress,
        public ?string $wordPressVersion,
        public array $releasePaths,
        private array $contents,
        public string $autoloadFile,
        public array $namespaceFolders,
    ) {
    }

    /**
     * @throws ReleaseRefused When the description is not one, or cannot be relied on.
     */
    public static function fromJson(string $json): self
    {
        $described = json_decode($json, true);

        if (! is_array($described) || ($described['name'] ?? null) !== self::NAME || ! isset($described['corex_version'])) {
            throw new ReleaseRefused(
                ReleaseRefused::NOT_A_PACKAGE,
                __('This is not a CoreX release package: it does not describe itself as one.', 'corex'),
            );
        }

        self::refuseOtherSchemas($described['schema'] ?? null);

        $paths = self::releasePaths($described);

        return new self(
            self::version($described),
            self::builtAt($described),
            is_string($described['client'] ?? null) && $described['client'] !== '' ? $described['client'] : null,
            self::requirement($described, 'php'),
            self::requirement($described, 'wordpress'),
            is_string($described['wordpress_version'] ?? null) ? $described['wordpress_version'] : null,
            $paths,
            self::contents($described, $paths),
            (string) ($described['autoload']['file'] ?? ''),
            array_values(array_map('strval', (array) ($described['autoload']['psr4'] ?? []))),
        );
    }

    /**
     * What a folder of the release holds, as the builder measured it.
     *
     * @return array{files:int,bytes:int,hash:string}
     */
    public function contentsOf(string $releasePath): array
    {
        return $this->contents[$releasePath];
    }

    private static function refuseOtherSchemas(mixed $schema): void
    {
        if ($schema === null) {
            throw new ReleaseRefused(
                ReleaseRefused::BUILT_BEFORE,
                __('This package was built before packages could be installed from the admin. Build it again with the current CoreX.', 'corex'),
            );
        }

        if ($schema !== self::SCHEMA) {
            throw new ReleaseRefused(
                ReleaseRefused::BUILT_AFTER,
                __('This package was built by a newer CoreX than this site can read. Update the site with a package built for it first.', 'corex'),
            );
        }
    }

    /**
     * @param array<string,mixed> $described
     */
    private static function version(array $described): string
    {
        $version = (string) $described['corex_version'];

        if (preg_match('/^\d+\.\d+\.\d+/', $version) !== 1) {
            throw self::incomplete(__('This package does not say which version it is, so it cannot be checked. Build it again.', 'corex'));
        }

        return $version;
    }

    /**
     * @param array<string,mixed> $described
     */
    private static function builtAt(array $described): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable((string) ($described['built_at'] ?? ''));
        } catch (Exception) {
            throw self::incomplete(__('This package does not say when it was built, so it cannot be checked. Build it again.', 'corex'));
        }
    }

    /**
     * @param array<string,mixed> $described
     */
    private static function requirement(array $described, string $of): string
    {
        $requirement = $described['requires'][$of] ?? null;

        if (! is_string($requirement) || preg_match('/^\d+(\.\d+)*$/', $requirement) !== 1) {
            throw self::incomplete(__('This package does not say which PHP and WordPress it needs, so it cannot be checked. Build it again.', 'corex'));
        }

        return $requirement;
    }

    /**
     * @param array<string,mixed> $described
     *
     * @return list<string>
     */
    private static function releasePaths(array $described): array
    {
        $paths = array_values(array_map('strval', (array) ($described['release_paths'] ?? [])));

        if ($paths === []) {
            throw self::incomplete(__('This package does not say which folders it holds, so it cannot be checked. Build it again.', 'corex'));
        }

        foreach ($paths as $path) {
            if (preg_match(self::OWNED, $path) !== 1) {
                throw new ReleaseRefused(
                    ReleaseRefused::UNSAFE_PATH,
                    sprintf(
                        /* translators: %s: a folder path from the package. */
                        __('This package names a folder a release does not own (%s), and was not unpacked.', 'corex'),
                        $path,
                    ),
                );
            }
        }

        return $paths;
    }

    /**
     * @param array<string,mixed> $described
     * @param list<string>        $paths
     *
     * @return array<string,array{files:int,bytes:int,hash:string}>
     */
    private static function contents(array $described, array $paths): array
    {
        $contents = [];

        foreach ($paths as $path) {
            $measured = $described['contents'][$path] ?? null;

            if (
                ! is_array($measured)
                || ! is_int($measured['files'] ?? null)
                || ! is_int($measured['bytes'] ?? null)
                || preg_match('/^[0-9a-f]{64}$/', (string) ($measured['hash'] ?? '')) !== 1
            ) {
                throw self::incomplete(__('This package does not say what each of its folders holds, so it cannot be checked. Build it again.', 'corex'));
            }

            $contents[$path] = ['files' => $measured['files'], 'bytes' => $measured['bytes'], 'hash' => $measured['hash']];
        }

        return $contents;
    }

    /**
     * @param string $message The whole sentence, already translated: each is its own string, not a fragment.
     */
    private static function incomplete(string $message): ReleaseRefused
    {
        return new ReleaseRefused(ReleaseRefused::INCOMPLETE, $message);
    }
}
