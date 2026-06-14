<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Commands;

defined('ABSPATH') || exit;

use Corex\Cli\Generators\ApiResourceScaffolder;
use Corex\Cli\Generators\ApiResourceScaffoldResult;
use Corex\Cli\Generators\BlockScaffolder;
use Corex\Cli\Generators\BlockScaffoldResult;
use Corex\Cli\Generators\Generator;
use Corex\Cli\Generators\GeneratorContext;
use Corex\Cli\Generators\GeneratorEngine;
use Corex\Cli\Generators\GeneratorResult;
use Corex\Cli\Site\SiteScaffolder;
use Corex\Cli\Site\SiteScaffoldResult;
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
        private readonly ?ApiResourceScaffolder $apiScaffolder = null,
        private readonly ?SiteScaffolder $siteScaffolder = null,
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

        if ($type === 'api-resource') {
            $this->runApiResource($args[0] ?? '', (bool) ($assoc['force'] ?? false));

            return;
        }

        if ($type === 'site') {
            $this->runSite($args[0] ?? '', $assoc);

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

    /**
     * Scaffold a complete REST resource (controller + routes + request + resource + test),
     * reporting each created file (spec 046).
     */
    private function runApiResource(string $name, bool $force): void
    {
        if ($this->apiScaffolder === null || $this->context === null) {
            WP_CLI::error('API resource scaffolding is unavailable (no scaffolder bound).');

            return;
        }

        try {
            $result = $this->apiScaffolder->scaffold($name, $this->context, $force);
        } catch (Throwable $e) {
            WP_CLI::error($e->getMessage());

            return;
        }

        match ($result->status) {
            ApiResourceScaffoldResult::CREATED => $this->reportCreatedApiResource($result),
            ApiResourceScaffoldResult::SKIPPED => WP_CLI::warning(sprintf('%s (%s)', $result->message, $result->apiDir)),
            default                            => WP_CLI::error($result->message ?? 'API resource scaffolding failed.'),
        };
    }

    private function reportCreatedApiResource(ApiResourceScaffoldResult $result): void
    {
        foreach ($result->paths as $path) {
            WP_CLI::log(sprintf('  + %s', $path));
        }

        WP_CLI::success(sprintf('API resource scaffolded: %s', $result->apiDir));
        WP_CLI::log('Register its Routes class (->register() on rest_api_init) and fill in the service.');
    }

    /**
     * Scaffold a client site (plugin + theme + governance) under `--path` (default: the
     * current directory + the site slug). Flags: --plugin-only / --theme-only / --force.
     *
     * @param array<string,mixed> $assoc
     */
    private function runSite(string $name, array $assoc): void
    {
        if ($this->siteScaffolder === null) {
            WP_CLI::error('Site scaffolding is unavailable (no scaffolder bound).');

            return;
        }

        $output = isset($assoc['path']) ? (string) $assoc['path'] : getcwd() . '/' . sanitize_title($name);

        $options = [
            'force'       => (bool) ($assoc['force'] ?? false),
            'plugin_only' => (bool) ($assoc['plugin-only'] ?? false),
            'theme_only'  => (bool) ($assoc['theme-only'] ?? false),
            // --starter emits the example slice; --minimal forces it off (the default is off too).
            'starter'     => (bool) ($assoc['starter'] ?? false) && ! (bool) ($assoc['minimal'] ?? false),
        ];

        try {
            $result = $this->siteScaffolder->scaffold($name, $output, $options);
        } catch (Throwable $e) {
            WP_CLI::error($e->getMessage());

            return;
        }

        if ($result->status === SiteScaffoldResult::CREATED) {
            foreach ($result->paths as $path) {
                WP_CLI::log(sprintf('  + %s', $path));
            }
            WP_CLI::success(sprintf('Client site scaffolded: %s', $result->siteDir));
            WP_CLI::log('Edit only the client plugin/theme — never the Corex framework. See AGENTS.md.');

            return;
        }

        $result->status === SiteScaffoldResult::SKIPPED
            ? WP_CLI::warning(sprintf('%s (%s)', $result->message, $result->siteDir))
            : WP_CLI::error($result->message ?? 'Site scaffolding failed.');
    }
}
