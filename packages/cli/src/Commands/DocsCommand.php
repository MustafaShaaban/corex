<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Commands;

defined('ABSPATH') || exit;

use Corex\Cli\Docs\DocsGenerator;
use Throwable;
use WP_CLI;

/**
 * The WP-CLI wrapper for `wp corex docs:generate`. Thin: it runs the (tested)
 * generator over the configured source layers and reports how many reference pages
 * were written. The only class here that references WP_CLI.
 */
final class DocsCommand
{
    /**
     * @param array<string,string> $layers layer label => source directory
     */
    public function __construct(
        private readonly DocsGenerator $generator,
        private readonly array $layers,
        private readonly string $outputDir,
    ) {
    }

    /**
     * @param list<string>         $args
     * @param array<string, mixed> $assoc
     */
    public function generate(array $args, array $assoc): void
    {
        try {
            $written = $this->generator->generate($this->layers, $this->outputDir);
        } catch (Throwable $e) {
            WP_CLI::error($e->getMessage());

            return;
        }

        WP_CLI::success(sprintf('Generated %d reference pages → %s', count($written), $this->outputDir));
    }
}
