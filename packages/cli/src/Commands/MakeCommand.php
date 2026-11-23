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
use Corex\Cli\Site\SiteRepository;
use Corex\Cli\Site\SiteRepositoryResolver;
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
        private readonly ?SiteRepositoryResolver $siteRepositories = null,
    ) {
    }

    /**
     * Whether a flag is on: given (`--records`, `--records=1`), and not turned off (`--no-records`,
     * `--records=false`, `--records=0`). WP-CLI hands `--records=false` over as the text "false",
     * which a cast would read as on.
     *
     * @param array<string, mixed> $assoc
     */
    public static function flag(array $assoc, string $name): bool
    {
        $value = $assoc[$name] ?? false;

        return is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param list<string>          $args
     * @param array<string, mixed>  $assoc
     */
    public function run(string $type, array $args, array $assoc): void
    {
        if ($type === 'block') {
            $this->runBlock(
                $args[0] ?? '',
                self::flag($assoc, 'force'),
                self::flag($assoc, 'records') ? BlockScaffolder::RECORDS : BlockScaffolder::IN_PLACE,
            );

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
     * Scaffold a complete dynamic block (block.json + index.js + style.scss + renderer, and the
     * parity.json of a block edited in place). Edited in place by default; `--records` gives a
     * block that shows records, previewed by the server render (spec 136, FR-014). Reports each
     * created file so the developer can see the full set.
     */
    private function runBlock(string $name, bool $force, string $shape): void
    {
        if ($this->blockScaffolder === null || $this->context === null) {
            WP_CLI::error('Block scaffolding is unavailable (no scaffolder bound).');

            return;
        }

        try {
            $result = $this->blockScaffolder->scaffold($name, $this->context, $force, $shape);
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
     * Scaffold a client site (plugin + theme + governance) under `--dir` (default: the
     * current directory + the site slug). Flags: --plugin-only / --theme-only / --force.
     *
     * The site directory is `--dir`, not `--path`. It was read from `--path` until spec 102, and
     * documented that way everywhere, and it never arrived: `--path` is a WP-CLI global, taken as
     * the WordPress install before any command sees its arguments. So the documented form did
     * not generate a site somewhere unexpected — it failed to find WordPress at all.
     *
     * @param array<string,mixed> $assoc
     */
    private function runSite(string $name, array $assoc): void
    {
        if ($this->siteScaffolder === null) {
            WP_CLI::error('Site scaffolding is unavailable (no scaffolder bound).');

            return;
        }

        $output = isset($assoc['dir']) ? (string) $assoc['dir'] : getcwd() . '/' . sanitize_title($name);

        $options = [
            'force'       => (bool) ($assoc['force'] ?? false),
            'plugin_only' => (bool) ($assoc['plugin-only'] ?? false),
            'theme_only'  => (bool) ($assoc['theme-only'] ?? false),
            // --starter emits the example slice; --minimal forces it off (the default is off too).
            'starter'     => (bool) ($assoc['starter'] ?? false) && ! (bool) ($assoc['minimal'] ?? false),
        ];

        $repository = $this->siteRepositories?->for($output, (string) getcwd());

        try {
            $result = $this->siteScaffolder->scaffold($name, $output, $options, $repository);
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
            $this->reportKeptTheme($result->kept);

            if ($repository !== null && ! $options['plugin_only'] && ! $options['theme_only']) {
                $this->reportUpdateSafety($repository);
            }

            return;
        }

        if ($result->lookedIn !== []) {
            WP_CLI::error(sprintf(
                /* translators: %s: the paths looked in, separated by commas. */
                __('Nothing was generated. A client theme is a child of the CoreX theme and lists its tokens, and the CoreX theme\'s theme.json could not be read at: %s', 'corex'),
                implode(', ', $result->lookedIn),
            ));

            return;
        }

        $result->status === SiteScaffoldResult::SKIPPED
            ? WP_CLI::warning(sprintf('%s (%s)', $result->message, $result->siteDir))
            : WP_CLI::error($result->message ?? 'Site scaffolding failed.');
    }

    /**
     * Say which files of an existing theme were left as they were (spec 135, FR-019). `--force`
     * rewrites the rest; an existing theme is never converted to a child, and its tokens are never
     * replaced.
     *
     * @param list<string> $kept
     */
    private function reportKeptTheme(array $kept): void
    {
        if ($kept === []) {
            return;
        }

        WP_CLI::warning(sprintf(
            /* translators: %s: the files kept, separated by commas. */
            __('Kept as they were: %s. An existing theme is never converted to a child of the CoreX theme, and its tokens are never replaced.', 'corex'),
            implode(', ', $kept),
        ));
    }

    /**
     * Say plainly what the scaffold could not do (spec 102). Both are things the operator has to
     * act on, and neither is visible in the list of files written.
     */
    private function reportUpdateSafety(SiteRepository $repository): void
    {
        if (! $repository->baseline->isResolved()) {
            WP_CLI::warning(
                'The framework commit could not be read from git, so corex-baseline.json records none. '
                . 'Record one with: npm run verify:framework -- --record <release tag>'
            );
        }

        if ($repository->root === null) {
            WP_CLI::warning(
                'No CI workflow was generated: the site is not at <repository>/sites/<client>, '
                . 'so there is nowhere to place .github/workflows/site-<client>.yml.'
            );
        }
    }
}
