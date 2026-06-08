<?php

/**
 * @package Corex\Blocks
 */

declare(strict_types=1);

namespace Corex\Blocks;

defined('ABSPATH') || exit;

use Corex\Support\BootLogger;

/**
 * Discovers blocks by convention: one folder per block under a blocks directory,
 * each with a `block.json`. Skips non-blocks silently, logs + skips a malformed
 * `block.json`, and de-dupes by block name (first wins) — never aborting the rest
 * (spec FR-001–FR-004). Pure of WordPress, so it is unit-testable headlessly.
 */
final class BlockMap
{
    public function __construct(private readonly BootLogger $logger)
    {
    }

    /**
     * @return list<array{dir: string, name: string, metadata: array<string, mixed>}>
     */
    public function discover(string $blocksDir): array
    {
        if (! is_dir($blocksDir)) {
            return [];
        }

        $blocks = [];
        $seen = [];

        foreach (glob(rtrim($blocksDir, '/\\') . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $metadata = $this->readMetadata($dir);

            if ($metadata === null) {
                continue;
            }

            $name = $metadata['name'];

            if (isset($seen[$name])) {
                $this->logger->warning(sprintf('Duplicate block name "%s" in %s; keeping the first.', $name, $dir));

                continue;
            }

            $seen[$name] = true;
            $blocks[] = ['dir' => $dir, 'name' => $name, 'metadata' => $metadata];
        }

        return $blocks;
    }

    /**
     * @return array{name: string}&array<string, mixed>|null
     */
    private function readMetadata(string $dir): ?array
    {
        $file = $dir . '/block.json';

        if (! is_file($file)) {
            return null; // not a block — skip silently
        }

        $metadata = json_decode((string) file_get_contents($file), true);

        if (! is_array($metadata) || ! isset($metadata['name']) || ! is_string($metadata['name'])) {
            $this->logger->warning(sprintf('Malformed or nameless block.json in %s; skipped.', $dir));

            return null;
        }

        return $metadata;
    }
}
