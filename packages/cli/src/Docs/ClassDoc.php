<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Docs;

defined('ABSPATH') || exit;

/**
 * The documentation extracted from one class-like (class/interface/trait/enum): its
 * fully-qualified name, kind, the summary from its docblock, and its public method
 * signatures + summaries. A plain value object — the renderer turns it into Markdown.
 */
final class ClassDoc
{
    /**
     * @param list<array{signature:string,summary:string}> $methods
     */
    public function __construct(
        public readonly string $fqcn,
        public readonly string $shortName,
        public readonly string $namespace,
        public readonly string $kind,
        public readonly string $summary,
        public readonly array $methods,
    ) {
    }
}
