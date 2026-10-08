<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Releases;

defined('ABSPATH') || exit;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * What a folder of a release holds: how many files, how many bytes, and one hash of all of them
 * (spec 107, plan D2).
 *
 * The hash is SHA-256 over one line per file, the lines sorted by path as bytes:
 *
 *     <path, forward slashes, relative to the folder> NUL <size in bytes> NUL <SHA-256 of the file> LF
 *
 * `scripts/release-content-hash.mjs` computes the same thing when a package is built, and writes
 * it into the package's description. A site compares that with this, over what it unpacked.
 */
final class ReleaseContentHash
{
    /**
     * @return array{files:int,bytes:int,hash:string}
     */
    public function describe(string $folder): array
    {
        $folder = rtrim($folder, '/\\');
        $lines  = [];
        $bytes  = 0;

        foreach ($this->filesOf($folder) as $file) {
            $path   = str_replace('\\', '/', substr($file->getPathname(), strlen($folder) + 1));
            $size   = (int) $file->getSize();
            $bytes += $size;
            $lines[] = $path . "\0" . $size . "\0" . hash_file('sha256', $file->getPathname()) . "\n";
        }
        // As bytes, which is the order the builder sorts them in.
        sort($lines, SORT_STRING);

        return ['files' => count($lines), 'bytes' => $bytes, 'hash' => hash('sha256', implode('', $lines))];
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function filesOf(string $folder): iterable
    {
        if (! is_dir($folder)) {
            return;
        }

        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($entries as $entry) {
            if ($entry instanceof SplFileInfo && $entry->isFile()) {
                yield $entry;
            }
        }
    }
}
