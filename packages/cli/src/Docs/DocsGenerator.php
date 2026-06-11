<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Docs;

defined('ABSPATH') || exit;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * Walks the configured source trees, reads each class to a ClassDoc, renders it to
 * Markdown, and writes one page per class under `<output>/<layer-slug>/<class-slug>.md`
 * — so the Internals Reference is generated from the code and can't drift. A file that
 * fails to parse is skipped, never fatal. Pure orchestration over the (pure) reader +
 * renderer; only the final write touches the filesystem.
 */
final class DocsGenerator
{
    public function __construct(
        private readonly ClassDocReader $reader,
        private readonly MarkdownDocRenderer $renderer,
    ) {
    }

    /**
     * @param array<string,string> $layers  layer label => source directory
     *
     * @return list<string> the written page paths
     */
    public function generate(array $layers, string $outputDir): array
    {
        $written = [];

        foreach ($layers as $label => $dir) {
            foreach ($this->phpFiles($dir) as $file) {
                $doc = $this->readQuietly($file);

                if ($doc === null) {
                    continue;
                }

                $path = rtrim($outputDir, '/\\') . DIRECTORY_SEPARATOR
                    . $this->slug($label) . DIRECTORY_SEPARATOR
                    . $this->slug($doc->shortName) . '.md';

                $dirName = dirname($path);

                if (! is_dir($dirName)) {
                    mkdir($dirName, 0755, true);
                }

                if (file_put_contents($path, $this->renderer->render($doc, $label)) !== false) {
                    $written[] = $path;
                }
            }
        }

        return $written;
    }

    private function readQuietly(string $file): ?ClassDoc
    {
        try {
            return $this->reader->read($file);
        } catch (Throwable) {
            return null; // unparseable file — skip, never abort the run
        }
    }

    /**
     * @return iterable<string>
     */
    private function phpFiles(string $dir): iterable
    {
        if (! is_dir($dir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                yield $file->getPathname();
            }
        }
    }

    private function slug(string $value): string
    {
        $slug = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $value));

        return trim($slug, '-');
    }
}
