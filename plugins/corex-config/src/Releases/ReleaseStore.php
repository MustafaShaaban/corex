<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Releases;

defined('ABSPATH') || exit;

use Corex\Security\Upload\ProtectedUploads;
use FilesystemIterator;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The installer's own place on a site: `wp-content/corex-releases/` (spec 107, plan D5).
 *
 *     incoming/   packages being received, and packages put there by hand
 *     staging/    the release paths of the package being installed, unpacked
 *     previous/   the folders the last installation replaced
 *     state.json  the installation in progress
 *     log.json    what happened: installations, refusals, returns (the last fifty)
 *
 * Beside the folders a release replaces, and none of them: the code that starts an installation
 * is replaced by it, and what it was doing has to be somewhere that is not. Not under
 * `uploads/` either, where a backup plugin would copy two releases of code into every backup.
 * And not in the database: a release migrates the tables, and the installer must be able to
 * read its own state before and after.
 */
final class ReleaseStore
{
    public const DIRECTORY = 'corex-releases';

    /** Another request of an installation is at work. */
    public const BUSY = 'installation_busy';

    private const LOCK = 'lock';

    private const AREAS = ['incoming', 'staging', 'previous'];

    private const STATE = 'state.json';

    private const LOG = 'log.json';

    private const LOG_KEEPS = 50;

    /** What the builder names a package. */
    private const PACKAGES = 'corex-release-*.zip';

    /**
     * @param string $contentDir The site's `wp-content` folder.
     */
    public function __construct(private readonly string $contentDir)
    {
    }

    /**
     * The place itself, made if it was not there and closed to a web server.
     *
     * @throws ReleaseRefused When the host will not let it be made: said in words, since a
     *                        person at the screen is who has to ask the host about it.
     */
    public function root(): string
    {
        $root = $this->place();

        if (! ProtectedUploads::guard($root)) {
            throw new ReleaseRefused(ReleaseHostFacts::NOT_WRITABLE, sprintf(
                /* translators: %s: a folder's path on the server. */
                __('The site could not make the folder it keeps releases in: %s. Ask the host to let the site write in its wp-content folder.', 'corex'),
                $root,
            ));
        }

        foreach (self::AREAS as $area) {
            wp_mkdir_p($root . '/' . $area);
        }

        return $root;
    }

    /** Whether the place has been made. Asking makes nothing. */
    public function exists(): bool
    {
        return is_dir($this->place());
    }

    /**
     * Remove what was received and never installed: every package, and every part of one, in
     * `incoming/` that was last written before the time given (spec 107, FR-003).
     *
     * @param int    $before A Unix time.
     * @param string $except The name of a package to leave whatever its age: the one an installation in progress is of.
     *
     * @return int How many files were removed.
     */
    public function discardUnused(int $before, string $except): int
    {
        $removed = 0;

        foreach (glob($this->place() . '/incoming/*.{zip,part}', GLOB_BRACE) ?: [] as $file) {
            if (basename($file) !== $except && (int) filemtime($file) < $before) {
                unlink($file);
                $removed++;
            }
        }

        return $removed;
    }

    /** Where the place is, whether or not it has been made. */
    private function place(): string
    {
        return rtrim($this->contentDir, '/\\') . '/' . self::DIRECTORY;
    }

    /**
     * One of the three folders, made if it was not there.
     *
     * @throws InvalidArgumentException For a folder this place does not have.
     * @throws ReleaseRefused           When the host will not let the place be made.
     */
    public function folder(string $area): string
    {
        if (! in_array($area, self::AREAS, true)) {
            throw new InvalidArgumentException('The release store has no folder named ' . $area);
        }

        return $this->root() . '/' . $area;
    }

    /**
     * Remove everything one of the three folders holds, and leave the folder.
     *
     * @throws InvalidArgumentException For a folder this place does not have.
     */
    public function empty(string $area): void
    {
        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->folder($area), FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($entries as $entry) {
            // The paths are this class's own, under the installer's folder.
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname()); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
        }
    }

    /**
     * Where a file of the given name is kept in one of the three folders.
     *
     * A name, never a path: what a package is called comes from an upload, and a name that
     * could climb would let an upload choose where on the site it is written.
     *
     * @throws InvalidArgumentException For a folder this place does not have, or a name that is not only a name.
     */
    public function fileIn(string $area, string $name): string
    {
        if ($name === '' || $name === '.' || $name === '..' || preg_match('#[/\\\\\x00]#', $name) === 1) {
            throw new InvalidArgumentException('A file in the release store is named, not placed: ' . $name);
        }

        return $this->folder($area) . '/' . $name;
    }

    /**
     * The packages in `incoming/`, by name: uploaded, or put there by hand.
     *
     * It reads and makes nothing. This is asked when the screen opens, and the screen has to
     * open on a host where the place cannot be made, to say so.
     *
     * @return list<array{name:string,bytes:int}>
     */
    public function packages(): array
    {
        $packages = [];

        foreach (glob($this->place() . '/incoming/' . self::PACKAGES) ?: [] as $file) {
            $packages[] = ['name' => basename($file), 'bytes' => (int) filesize($file)];
        }

        return $packages;
    }

    /**
     * Do a piece of an installation while no other request does one (spec 107, FR-023).
     *
     * The lock is asked for and not waited for: a request that finds it held is a second tab,
     * a double press or a retry that came too soon, and is told so. It is a lock on a file, so
     * it goes when its request ends, however that request ends.
     *
     * @template T
     *
     * @param callable():T $work
     *
     * @return T
     *
     * @throws ReleaseRefused When another request holds it, or the host will not let the place be made.
     */
    public function exclusively(callable $work): mixed
    {
        // The path is this class's own, under the installer's folder.
        $lock = fopen($this->root() . '/' . self::LOCK, 'c'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            if ($lock !== false) {
                fclose($lock); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
            }

            throw new ReleaseRefused(
                self::BUSY,
                __('Another request of this installation is still at work. Wait a moment and try again.', 'corex'),
            );
        }

        try {
            return $work();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        }
    }

    /**
     * The installation in progress, or nothing.
     *
     * @return array<string,mixed>
     */
    public function state(): array
    {
        return $this->read(self::STATE);
    }

    /**
     * @param array<string,mixed> $state
     */
    public function saveState(array $state): void
    {
        $this->write(self::STATE, $state);
    }

    public function clearState(): void
    {
        $file = $this->root() . '/' . self::STATE;

        if (is_file($file)) {
            unlink($file);
        }
    }

    /**
     * What happened here, oldest first.
     *
     * @return list<array<string,mixed>>
     */
    public function log(): array
    {
        return array_values($this->read(self::LOG));
    }

    /**
     * @param array<string,mixed> $event
     */
    public function record(array $event): void
    {
        $this->write(self::LOG, array_slice([...$this->log(), $event], -self::LOG_KEEPS));
    }

    /**
     * A file that is missing, or cut off mid-write by a request that died, holds nothing.
     *
     * Reading makes nothing: the screen asks what is in progress when it opens, and has to
     * open on a host where the place cannot be made, to say so.
     *
     * @return array<int|string,mixed>
     */
    private function read(string $name): array
    {
        $file = $this->place() . '/' . $name;

        if (! is_file($file)) {
            return [];
        }

        $held = json_decode((string) file_get_contents($file), true);

        return is_array($held) ? $held : [];
    }

    /**
     * Written beside the file and moved over it, so a reader never finds half of one.
     *
     * @param array<int|string,mixed> $holds
     */
    private function write(string $name, array $holds): void
    {
        $file    = $this->root() . '/' . $name;
        $written = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';

        // Direct writes, not WP_Filesystem: it can ask for FTP credentials it has no way to
        // ask for in the middle of an installation. The path is this class's own.
        file_put_contents($written, (string) wp_json_encode($holds)); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        rename($written, $file); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
    }
}
