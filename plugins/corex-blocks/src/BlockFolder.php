<?php

/**
 * @package Corex\Blocks
 */

declare(strict_types=1);

namespace Corex\Blocks;

defined('ABSPATH') || exit;

/**
 * Registers a folder of blocks: the one public way a plugin hands CoreX its blocks (spec 135,
 * FR-032; DECISIONS #662).
 *
 * The built folder is used when it is a directory, the source folder otherwise. Each directory in
 * it with a `block.json` is registered through the registrar CoreX's own blocks use, so a renderer
 * named in `corex.renderer` is resolved from the container and the block's path is handled the same
 * way on any mount. A missing folder, or one with no `block.json`, registers nothing.
 *
 * Resolve it from the container and call it on `init`:
 *
 *     $container->make(BlockFolder::class)->register($pluginDir . '/build/blocks', $pluginDir . '/src/Blocks');
 */
final class BlockFolder
{
    public function __construct(
        private readonly BlockMap $map,
        private readonly DynamicBlockRegistrar $registrar,
    ) {
    }

    /**
     * @param string $builtDir  Where `npm run build` writes the blocks (for example `build/blocks`).
     * @param string $sourceDir The blocks' sources, used while there is no build (for example `src/Blocks`).
     */
    public function register(string $builtDir, string $sourceDir): void
    {
        $dir = is_dir($builtDir) ? $builtDir : $sourceDir;

        foreach ($this->map->discover($dir) as $block) {
            $this->registrar->register($block);
        }
    }
}
