<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Releases;

defined('ABSPATH') || exit;

/**
 * Receives a release package in parts (spec 107, plan D12).
 *
 * A package is known by the SHA-256 of the whole file, which the browser computes before it
 * sends anything. Its parts are appended to one file named for that hash, each saying where it
 * starts; a part that does not start where the last one ended is answered with how much the
 * site holds, and the browser goes on from there. So an upload that was cut off resumes, a part
 * sent twice does no harm, and two packages being received do not touch.
 *
 * When all of it has arrived the file is hashed here. If it is what was sent it takes the
 * package's name; if it is not, it is deleted. Nothing is opened as a package until then.
 */
final class ReleaseUpload
{
    /**
     * Larger than any release: the framework's package, zipped, is about 35MB. A site has only
     * so much disk, and an upload says its own size.
     */
    public const LARGEST = 512 * 1024 * 1024;

    public const TOO_LARGE = 'too_large';
    public const DAMAGED   = 'damaged';
    public const BAD_NAME  = 'bad_name';
    public const BAD_HASH  = 'bad_hash';

    /** What the builder names a package, and nothing else: `corex-release-<client>-<version>-<built>.zip`. */
    private const A_PACKAGE_NAME = '/^corex-release-[A-Za-z0-9][A-Za-z0-9._-]*\.zip$/';

    /**
     * @param int $largest The most bytes a package may be.
     */
    public function __construct(
        private readonly ReleaseStore $store,
        private readonly int $largest = self::LARGEST,
    ) {
    }

    /**
     * How much of the package with this hash the site holds: where the next part starts.
     *
     * @throws ReleaseRefused When the hash is not one.
     */
    public function received(string $sha256): int
    {
        $part = $this->partFile($sha256);
        clearstatcache(true, $part);

        return is_file($part) ? (int) filesize($part) : 0;
    }

    /**
     * Append a part, and answer how much is held after it.
     *
     * @param int    $offset Where in the package these bytes start.
     * @param string $bytes  The part.
     *
     * @throws ReleaseUploadOutOfStep When the part does not start where the last one ended.
     * @throws ReleaseRefused         When the hash is not one, the package has grown past what a release can be, or the host will not let it be written.
     */
    public function append(string $sha256, int $offset, string $bytes): int
    {
        $part = $this->partFile($sha256);

        // Opened to append and locked, so two requests carrying the same part cannot both
        // find the file the length they expect.
        $file = fopen($part, 'c+b'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        if ($file === false) {
            throw new ReleaseRefused(
                ReleaseHostFacts::NOT_WRITABLE,
                __('The site could not write the package in the folder it keeps releases in. Ask the host to let the site write in wp-content/corex-releases.', 'corex'),
            );
        }

        try {
            flock($file, LOCK_EX);
            $held = (int) fstat($file)['size'];

            if ($held !== $offset) {
                throw new ReleaseUploadOutOfStep($held);
            }

            $fits = $held + strlen($bytes) <= $this->largest;

            if ($fits) {
                fseek($file, 0, SEEK_END);
                fwrite($file, $bytes); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
            }
        } finally {
            flock($file, LOCK_UN);
            fclose($file); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        }

        if (! $fits) {
            $this->discard($part);
            throw new ReleaseRefused(
                self::TOO_LARGE,
                __('This file is larger than any CoreX release package. It was not kept.', 'corex'),
            );
        }

        return $held + strlen($bytes);
    }

    /**
     * Make what arrived the package, if it is all there and it is what was sent.
     *
     * @param int    $size The package's size, as the browser read it.
     * @param string $name The package's file name.
     *
     * @return string The name it is kept under in `incoming/`.
     *
     * @throws ReleaseUploadOutOfStep When not all of it has arrived.
     * @throws ReleaseRefused         When the name is not a package's, or what arrived is not what was sent.
     */
    public function complete(string $sha256, int $size, string $name): string
    {
        $part = $this->partFile($sha256);
        $held = $this->received($sha256);

        if (preg_match(self::A_PACKAGE_NAME, $name) !== 1) {
            $this->discard($part);
            throw new ReleaseRefused(
                self::BAD_NAME,
                __('This is not named as a CoreX release package is (corex-release-….zip). It was not kept.', 'corex'),
            );
        }

        if ($held < $size) {
            throw new ReleaseUploadOutOfStep($held);
        }

        if ($held !== $size || hash_file('sha256', $part) !== $sha256) {
            $this->discard($part);
            throw new ReleaseRefused(
                self::DAMAGED,
                __('What arrived is not the file that was sent: it was damaged on the way. It was not kept. Send it again.', 'corex'),
            );
        }

        rename($part, $this->store->fileIn('incoming', $name)); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename

        return $name;
    }

    /**
     * The file a package's parts are appended to. The hash names it, so the hash is checked
     * for being one: sixty-four lower-case hexadecimal digits cannot be a path.
     */
    private function partFile(string $sha256): string
    {
        if (preg_match('/^[0-9a-f]{64}$/', $sha256) !== 1) {
            throw new ReleaseRefused(
                self::BAD_HASH,
                __('The upload did not say which file it is. Start it again.', 'corex'),
            );
        }

        return $this->store->fileIn('incoming', $sha256 . '.part');
    }

    private function discard(string $part): void
    {
        if (is_file($part)) {
            unlink($part); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
        }
    }
}
