<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Generators;

defined('ABSPATH') || exit;

use Corex\Cli\Support\Naming;

/**
 * Scaffolds a complete, registered, working DYNAMIC block from one name — the same
 * shape the build pipeline expects: a block folder (`block.json` + `index.js` +
 * `style.scss`) under `<base>/Blocks/<slug>/`, plus the PHP `BlockRenderer` class
 * beside it at `<base>/Blocks/<Name>Renderer.php`. WP-CLI-independent and headless-testable: it only renders
 * stubs and writes files (no WordPress, no `register_block_type`).
 *
 * The generated block is discovered by the corex-blocks engine (BlockMap → the build
 * dir, or the source `blocks/` dir headlessly) and previewed in the editor via
 * `<ServerSideRender>`. Run `npm run build` to compile its `index.js`/`style.scss`.
 */
final class BlockScaffolder
{
    public function __construct(
        private readonly StubRenderer $renderer,
        private readonly Naming $naming,
        private readonly string $stubsDir,
    ) {
    }

    public function scaffold(string $rawName, GeneratorContext $context, bool $force = false): BlockScaffoldResult
    {
        $base = $this->naming->classNameFor($rawName);
        $slug = $this->naming->blockSlugFor($base);
        $title = $this->naming->titleFor($base);
        $rendererClass = $base . 'Renderer';

        $rendererNamespace = $context->namespace . '\\Blocks';
        $values = [
            'block_name'    => $context->prefix . '/' . $slug,
            'slug'          => $slug,
            'title'         => $title,
            'class'         => $rendererClass,
            'namespace'     => $rendererNamespace,
            // JSON needs escaped backslashes for the FQCN inside block.json.
            'renderer_fqcn' => str_replace('\\', '\\\\', $rendererNamespace . '\\' . $rendererClass),
            'text_domain'   => $context->prefix,
            'css_class'     => $context->prefix . '-' . $slug,
        ];

        // Renderers and their block folders live together under one `Blocks/` dir
        // (matching the corex-ui convention + the `App\Blocks` namespace). A single
        // dir name also avoids a `blocks/` vs `Blocks/` collision on case-insensitive
        // filesystems (Windows, macOS). BlockMap's GLOB_ONLYDIR skips the renderer
        // PHP files, so they never confuse discovery.
        $blocksRoot = $this->join($context->basePath, 'Blocks');
        $blockDir = $blocksRoot . DIRECTORY_SEPARATOR . $slug;
        $rendererPath = $blocksRoot . DIRECTORY_SEPARATOR . $rendererClass . '.php';

        if (is_dir($blockDir) && ! $force) {
            return BlockScaffoldResult::skipped($blockDir);
        }

        $files = [
            $blockDir . DIRECTORY_SEPARATOR . 'block.json' => 'block/block.json',
            $blockDir . DIRECTORY_SEPARATOR . 'index.js'   => 'block/index.js',
            $blockDir . DIRECTORY_SEPARATOR . 'style.scss' => 'block/style.scss',
            $rendererPath                                  => 'block/renderer',
        ];

        // Render everything before writing anything: an unresolved placeholder must
        // fail loudly without leaving a half-written block on disk.
        $rendered = [];
        foreach ($files as $path => $stub) {
            $rendered[$path] = $this->renderer->render($this->readStub($stub), $values);
        }

        return $this->writeAll($blockDir, $rendered);
    }

    /**
     * Write the rendered files, creating parent dirs. Returns a CREATED result with
     * the written paths, or an ERROR result on the first failure.
     *
     * @param array<string, string> $rendered  path => contents
     */
    private function writeAll(string $blockDir, array $rendered): BlockScaffoldResult
    {
        $written = [];

        foreach ($rendered as $path => $contents) {
            $dir = dirname($path);

            if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
                return BlockScaffoldResult::error($blockDir, sprintf('Could not create directory: %s', $dir));
            }

            if (file_put_contents($path, $contents) === false) {
                return BlockScaffoldResult::error($blockDir, sprintf('Could not write: %s', $path));
            }

            $written[] = $path;
        }

        return BlockScaffoldResult::created($blockDir, $written);
    }

    private function readStub(string $name): string
    {
        return (string) file_get_contents(
            rtrim($this->stubsDir, '/\\') . DIRECTORY_SEPARATOR . $name . '.stub'
        );
    }

    private function join(string ...$parts): string
    {
        return rtrim($parts[0], '/\\') . DIRECTORY_SEPARATOR
            . implode(DIRECTORY_SEPARATOR, array_slice($parts, 1));
    }
}
