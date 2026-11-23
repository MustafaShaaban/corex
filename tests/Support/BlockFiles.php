<?php

/**
 * Finds a file by name under some folders (spec 136, FR-012): every `parity.json`, so a new block
 * is covered without a new test. Dependencies and build output are not sources and are skipped:
 * `node_modules`, `build`, `vendor`, and folders whose name starts with a dot. The JavaScript twin
 * is `packages/build-tools/parity/find.js`.
 *
 * @package Corex\Tests\Support
 */

declare(strict_types=1);

namespace Corex\Tests\Support;

use FilesystemIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class BlockFiles
{
    private const SKIPPED = ['node_modules', 'build', 'vendor'];

    /**
     * @param list<string> $folders
     * @return list<string> every match, sorted
     */
    public static function find(array $folders, string $fileName): array
    {
        $found = [];
        foreach ($folders as $folder) {
            if (! is_dir($folder)) {
                continue;
            }

            $directories = new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS),
                static fn (SplFileInfo $entry): bool => ! $entry->isDir()
                    || (! in_array($entry->getFilename(), self::SKIPPED, true) && ! str_starts_with($entry->getFilename(), '.')),
            );

            foreach (new RecursiveIteratorIterator($directories) as $file) {
                if ($file instanceof SplFileInfo && $file->getFilename() === $fileName) {
                    $found[] = $file->getPathname();
                }
            }
        }
        sort($found, SORT_STRING);

        return $found;
    }
}
