<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Commands;

defined('ABSPATH') || exit;

use Corex\Cli\Generators\BlockScaffolder;
use Corex\Cli\Generators\BlockScaffoldResult;
use Corex\Cli\Generators\Generator;
use Corex\Cli\Generators\GeneratorContext;
use Corex\Cli\Generators\GeneratorEngine;
use Corex\Cli\Generators\GeneratorResult;
use Throwable;
use WP_CLI;

/**
 * The WP-CLI-facing wrapper for the generators. Thin: it parses the name + `--force`,
 * runs the (tested) engine, and reports the structured result. The only class that
 * references WP_CLI; instantiated only when WP-CLI is present.
 */
final class MakeCommand
{
    /**
     * @param array<string, Generator> $generators  subcommand => generator
     */
    public function __construct(
        private readonly GeneratorEngine $engine,
        private readonly array $generators,
        private readonly ?BlockScaffolder $blockScaffolder = null,
        private readonly ?GeneratorContext $context = null,
    ) {
    }

    /**
     * @param list<string>          $args
     * @param array<string, mixed>  $assoc
     */
    public function run(string $type, array $args, array $assoc): void
    {
        if ($type === 'block') {
            $this->runBlock($args[0] ?? '', (bool) ($assoc['force'] ?? false));

            return;
        }

        $generator = $this->generators[$type] ?? null;

        if ($generator === null) {
            WP_CLI::error(sprintf('Unknown generator: %s', $type));

            return;
        }

        try {
            $result = $this->engine->generate($generator, $args[0] ?? '', (bool) ($assoc['force'] ?? false));
        } catch (Throwable $e) {
            WP_CLI::error($e->getMessage());

            return;
        }

        match ($result->status) {
            GeneratorResult::CREATED => WP_CLI::success(sprintf('Created: %s', $result->path)),
            GeneratorResult::SKIPPED => WP_CLI::warning(sprintf('%s (%s)', $result->message, $result->path)),
            default                  => WP_CLI::error($result->message ?? 'Generation failed.'),
        };
    }

    /**
     * Scaffold a complete dynamic block (block.json + index.js + style.scss +
     * renderer). Reports each created file so the developer can see the full set.
     */
    private function runBlock(string $name, bool $force): void
    {
        if ($this->blockScaffolder === null || $this->context === null) {
            WP_CLI::error('Block scaffolding is unavailable (no scaffolder bound).');

            return;
        }

        try {
            $result = $this->blockScaffolder->scaffold($name, $this->context, $force);
        } catch (Throwable $e) {
            WP_CLI::error($e->getMessage());

            return;
        }

        match ($result->status) {
            BlockScaffoldResult::CREATED => $this->reportCreatedBlock($result),
            BlockScaffoldResult::SKIPPED => WP_CLI::warning(sprintf('%s (%s)', $result->message, $result->blockDir)),
            default                      => WP_CLI::error($result->message ?? 'Block scaffolding failed.'),
        };
    }

    private function reportCreatedBlock(BlockScaffoldResult $result): void
    {
        foreach ($result->paths as $path) {
            WP_CLI::log(sprintf('  + %s', $path));
        }

        WP_CLI::success(sprintf('Block scaffolded: %s', $result->blockDir));
        WP_CLI::log('Run `npm run build` to compile index.js + style.scss.');
    }
}
