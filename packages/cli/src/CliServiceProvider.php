<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli;

defined('ABSPATH') || exit;

use Corex\Cache\CacheManager;
use Corex\Cache\CacheScope;
use Corex\Cli\Commands\DocsCommand;
use Corex\Cli\Commands\DoctorCommand;
use Corex\Cli\Commands\MakeCommand;
use Corex\Cli\Commands\MigrateCommand;
use Corex\Cli\Commands\ReadinessCommand;
use Corex\Cli\Commands\ReadinessCommandServices;
use Corex\Cli\Commands\ResetCommand;
use Corex\Cli\Commands\SecurityResetLoginCommand;
use Corex\Cli\Commands\VersionCommand;
use Corex\Cli\Release\CiSecurityReadiness;
use Corex\Cli\Release\ComponentCoverageReadinessCheck;
use Corex\Cli\Release\FreeProBoundaryReadinessCheck;
use Corex\Cli\Release\MetadataConsistencyCheck;
use Corex\Cli\Release\MultiAgentReadinessCheck;
use Corex\Cli\Release\VersionPlan;
use Corex\Health\HealthModule;
use Corex\Cli\Docs\ClassDocReader;
use Corex\Cli\Docs\DocsGenerator;
use Corex\Cli\Docs\MarkdownDocRenderer;
use Corex\Assets\AssetManager;
use Corex\Assets\AssetReport;
use Corex\Cli\Docs\ApiDocsGenerator;
use Corex\Cli\Generators\ApiResourceScaffolder;
use Corex\Cli\Generators\BlockScaffolder;
use Corex\Cli\Release\ClientBrandingComplianceCheck;
use Corex\Cli\Release\DeploymentReadinessCheck;
use Corex\Cli\Release\ReleasePackagePlan;
use Corex\Cli\Routes\RouteList;
use Corex\Cli\Routes\RoutesReader;
use Corex\Cli\Site\SiteScaffolder;
use Corex\Cli\Site\SiteScaffoldValidator;
use Corex\Cli\Generators\ControllerGenerator;
use Corex\Cli\Generators\GeneratorContext;
use Corex\Cli\Generators\GeneratorEngine;
use Corex\Cli\Generators\ModelGenerator;
use Corex\Cli\Generators\GuideGenerator;
use Corex\Cli\Generators\OptionPageGenerator;
use Corex\Cli\Generators\RepositoryGenerator;
use Corex\Cli\Generators\ServiceGenerator;
use Corex\Cli\Generators\StubRenderer;
use Corex\Cli\Reset\ResetExecutor;
use Corex\Cli\Reset\ResetGate;
use Corex\Cli\Reset\ResetPlanner;
use Corex\Cli\Support\Naming;
use Corex\Container\ContainerInterface;
use Corex\Foundation\ServiceProvider;
use Corex\Support\Config\ConfigInterface;
use WP_CLI;

/**
 * Registers the generator subsystem and, only when WP-CLI is present, the
 * `wp corex make:*` commands (spec FR-012, FR-014; Principle IX).
 */
final class CliServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(ResetPlanner::class);
        $this->container->singleton(ResetGate::class);
        $this->container->singleton(ResetExecutor::class);
        $this->container->singleton(ResetCommand::class);
        $this->container->singleton(StubRenderer::class);
        $this->container->singleton(Naming::class);

        $this->container->singleton(
            GeneratorContext::class,
            fn (ContainerInterface $c): GeneratorContext => $this->context($c->make(ConfigInterface::class)),
        );

        $this->container->singleton(
            GeneratorEngine::class,
            fn (ContainerInterface $c): GeneratorEngine => new GeneratorEngine(
                $c->make(StubRenderer::class),
                $c->make(Naming::class),
                $c->make(GeneratorContext::class),
                dirname(__DIR__) . '/stubs',
            ),
        );

        $this->container->singleton(
            BlockScaffolder::class,
            fn (ContainerInterface $c): BlockScaffolder => new BlockScaffolder(
                $c->make(StubRenderer::class),
                $c->make(Naming::class),
                dirname(__DIR__) . '/stubs',
            ),
        );

        $this->container->singleton(
            ApiResourceScaffolder::class,
            fn (ContainerInterface $c): ApiResourceScaffolder => new ApiResourceScaffolder(
                $c->make(StubRenderer::class),
                $c->make(Naming::class),
                dirname(__DIR__) . '/stubs',
            ),
        );

        $this->container->singleton(
            SiteScaffolder::class,
            fn (ContainerInterface $c): SiteScaffolder => new SiteScaffolder(
                $c->make(StubRenderer::class),
                dirname(__DIR__) . '/stubs',
            ),
        );
        $this->container->singleton(SiteScaffoldValidator::class);
        $this->container->singleton(DeploymentReadinessCheck::class);
        $this->container->singleton(ComponentCoverageReadinessCheck::class);
        $this->container->singleton(FreeProBoundaryReadinessCheck::class);
        $this->container->singleton(MultiAgentReadinessCheck::class);
        $this->container->singleton(MigrateCommand::class);

        $this->container->singleton(
            DocsGenerator::class,
            static fn (): DocsGenerator => new DocsGenerator(new ClassDocReader(), new MarkdownDocRenderer()),
        );
    }

    public function boot(): void
    {
        if (! class_exists('WP_CLI')) {
            return;
        }

        foreach ($this->commandRegistrations() as $name => $registration) {
            WP_CLI::add_command($name, $registration['handler'], $registration['definition']);
        }
    }

    /**
     * @return array<string, array{handler: callable, definition: array<string, mixed>}>
     */
    public function commandRegistrations(): array
    {
        $root = dirname(__DIR__, 3);
        $frameworkPaths = [
            'plugins/corex-core', 'plugins/corex-blocks', 'plugins/corex-config', 'plugins/corex-forms',
            'theme', 'packages/cli', 'addons/corex-ui', 'addons/corex-email', 'addons/corex-captcha',
            'addons/corex-newsletter', 'addons/corex-careers', 'addons/corex-bookings', 'addons/corex-media',
            'addons/corex-kit-company', 'addons/corex-kit-portfolio', 'addons/corex-kit-woo',
        ];
        $registrations = [];

        foreach (['model', 'repository', 'controller', 'service', 'option-page', 'guide', 'block', 'api-resource', 'site'] as $type) {
            $registrations["corex make:{$type}"] = [
                'handler' => function (array $args, array $assoc) use ($type): void {
                    $this->makeCommand()->run($type, $args, $assoc);
                },
                'definition' => [],
            ];
        }

        // REST discovery + OpenAPI (spec 046): list the Corex/app routes and emit an API doc.
        $registrations['corex routes:list'] = [
            'handler' => function (): void {
                $routesReader = new RoutesReader();

                foreach ((new RouteList())->lines($routesReader->read($this->routeNamespaces())) as $line) {
                    WP_CLI::log($line);
                }
            },
            'definition' => [],
        ];

        $registrations['corex api:docs'] = [
            'handler' => function (): void {
                $routesReader = new RoutesReader();
                $version      = defined('COREX_CORE_VERSION') ? COREX_CORE_VERSION : '0.0.0';
                $doc          = (new ApiDocsGenerator())->generate(
                    $routesReader->read($this->routeNamespaces()),
                    'Corex API',
                    $version,
                );
                WP_CLI::log((string) wp_json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            },
            'definition' => [],
        ];

        // Asset diagnostics + cache reset (spec 047).
        $registrations['corex assets:doctor'] = [
            'handler' => function (): void {
                $assets  = $this->container->make(AssetManager::class);
                $report  = new AssetReport();
                $present = is_file(COREX_CORE_PATH . 'build/manifest.json');
                $samples = ['build/index.js' => $assets->version('build/index.js')];

                foreach ($report->lines($report->build($assets->environment(), $present, $samples)) as $line) {
                    WP_CLI::log($line);
                }
            },
            'definition' => [],
        ];

        // Cache management (spec 078). What this replaces deleted one transient and printed
        // "success" — a message that read as "your caches are cleared" while seven of the eight
        // things CoreX caches were untouched.
        $registrations['corex cache:status'] = [
            'handler' => function (): void {
                $cache = $this->container->make(CacheManager::class);

                WP_CLI::log('Store: ' . $cache->store()->describe());
                WP_CLI::log('');
                WP_CLI::log('Declared cache entries:');

                foreach ($cache->registry()->all() as $entry) {
                    WP_CLI::log(sprintf(
                        '  %-34s %-18s %s',
                        $entry->key . ($entry->isPrefix ? '*' : ''),
                        $entry->classification->value,
                        $entry->isRoutinelyClearable() ? 'clearable' : 'protected',
                    ));
                }
            },
            'definition' => [],
        ];

        $registrations['corex cache:doctor'] = [
            'handler' => function (): void {
                $cache = $this->container->make(CacheManager::class);

                // Deliberately says what CoreX cannot do as well as what it can — the two most
                // common cache questions have answers CoreX does not control.
                WP_CLI::log('Store:            ' . $cache->store()->describe());
                WP_CLI::log('Survives request: ' . ($cache->store()->isPersistent() ? 'yes' : 'no'));
                WP_CLI::log('Object cache:     ' . (
                    function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache()
                        ? 'persistent (WordPress is using one)'
                        : 'none (transients in the options table)'
                ));
                WP_CLI::log('OPcache:          ' . (
                    function_exists('opcache_get_status') ? 'inspectable' : 'not inspectable on this host'
                ));
                WP_CLI::log('');
                WP_CLI::log('CoreX cannot clear a visitor\'s browser cache. Asset versioning is what');
                WP_CLI::log('makes browsers fetch updated files.');
                WP_CLI::log('Protected from every clear scope:');

                foreach ($cache->registry()->protected() as $entry) {
                    WP_CLI::log('  ' . $entry->key . '* — ' . $entry->classification->value);
                }
            },
            'definition' => [],
        ];

        $registrations['corex cache:clear'] = [
            'handler' => function (array $args, array $assoc): void {
                $cache     = $this->container->make(CacheManager::class);
                $requested = isset($assoc['scope']) ? (string) $assoc['scope'] : CacheScope::Corex->value;
                $scope     = CacheScope::tryFromName($requested);

                // Refused, not interpreted. A scope decides what gets removed, so guessing at an
                // unrecognised one is the last thing this should do.
                if ($scope === null) {
                    WP_CLI::error(sprintf(
                        'Unknown scope "%s". Available: %s',
                        $requested,
                        implode(', ', CacheScope::names()),
                    ));
                }

                $confirmed = ! empty($assoc['yes']);

                if ($scope->requiresExplicitConfirmation() && ! $confirmed) {
                    WP_CLI::error(sprintf(
                        'The "%s" scope reaches beyond CoreX. Re-run with --yes if that is what you intend.',
                        $scope->value,
                    ));
                }

                $outcome = $cache->clear($scope, $confirmed);

                foreach ($outcome->cleared as $what) {
                    WP_CLI::log('cleared:     ' . $what);
                }
                foreach ($outcome->skipped as $what => $why) {
                    WP_CLI::log('skipped:     ' . $what . ' — ' . $why);
                }
                foreach ($outcome->unsupported as $what => $why) {
                    WP_CLI::log('unsupported: ' . $what . ' — ' . $why);
                }
                foreach ($outcome->failed as $what => $why) {
                    WP_CLI::warning('failed:      ' . $what . ' — ' . $why);
                }

                // A failure is a non-zero exit, so a deploy script can tell. "Nothing needed
                // clearing" is a success — it is the desired state, reached early.
                if ($outcome->hasFailures()) {
                    WP_CLI::error($outcome->summary());
                }

                WP_CLI::success($outcome->summary());
            },
            'definition' => [],
        ];

        // Team ops + distribution (spec 050): compliance check, release packaging, local docs.
        $registrations['corex compliance:check'] = [
            'handler' => static function (array $args, array $assoc) use ($frameworkPaths): void {
                $files  = isset($assoc['files']) ? array_filter(array_map('trim', explode(',', (string) $assoc['files']))) : [];
                $result = (new ClientBrandingComplianceCheck())->evaluate(array_values($files), ! empty($assoc['allow-framework']));

                if ($result['passed']) {
                    WP_CLI::success('Compliance OK — no Corex framework files changed.');

                    return;
                }

                foreach ($result['violations'] as $violation) {
                    WP_CLI::log(sprintf('  ✗ framework file changed: %s', $violation));
                }
                WP_CLI::error('Compliance failed — client work must not edit Corex framework folders.');
            },
            'definition' => [],
        ];

        $registrations['corex package:update'] = [
            'handler' => static function (array $args, array $assoc) use ($frameworkPaths): void {
                $version  = (string) ($args[0] ?? '');
                $download = (string) ($assoc['download-url'] ?? '');
                $plan     = new ReleasePackagePlan($frameworkPaths, ['/tests/', '/specs/', 'node_modules', '.git/', 'wp-config', '.env']);
                $manifest = $plan->manifest($version, $download, (string) ($assoc['changelog'] ?? 'Bug fixes and improvements.'));

                WP_CLI::log((string) wp_json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                WP_CLI::success(sprintf('Manifest planned for %s (framework-only; build the ZIP from the included paths).', $version));
            },
            'definition' => [],
        ];

        $registrations['corex docs:sync'] = [
            'handler' => static function () use ($root): void {
                $built = $root . '/docs-app/dist';
                if (! is_dir($built)) {
                    WP_CLI::warning('No built docs found — run `npm run build` in docs-app/ first.');

                    return;
                }
                WP_CLI::success(sprintf('Docs available at %s — copy into .corex/docs/ (git-ignored) to read locally.', $built));
            },
            'definition' => [],
        ];

        $registrations['corex docs:serve'] = [
            'handler' => static function (): void {
                WP_CLI::log('Serve the docs locally: `cd docs-app && npm run dev` → http://localhost:4321');
            },
            'definition' => [],
        ];

        $registrations['corex docs:generate'] = [
            'handler' => function (array $args, array $assoc) use ($root): void {
                $docs = new DocsCommand(
                    $this->container->make(DocsGenerator::class),
                    [
                        'Core'    => $root . '/plugins/corex-core/src',
                        'Blocks'  => $root . '/plugins/corex-blocks/src',
                        'Forms'   => $root . '/plugins/corex-forms/src',
                        'Config'  => $root . '/plugins/corex-config/src',
                        'CLI'     => $root . '/packages/cli/src',
                        'Add-ons' => $root . '/addons',
                    ],
                    $root . '/docs-app/src/content/docs/reference',
                );

                $docs->generate($args, $assoc);
            },
            'definition' => [],
        ];

        $registrations['corex reset'] = [
            'handler' => function (array $args, array $assoc): void {
                $this->container->make(ResetCommand::class)->run($args, $assoc);
            },
            'definition' => $this->resetCommandDefinition(),
        ];

        $registrations['corex migrate'] = [
            'handler' => function (array $args, array $assoc): void {
                $this->container->make(MigrateCommand::class)->run($args, $assoc);
            },
            'definition' => [],
        ];

        $registrations['corex security reset-login'] = [
            'handler' => function (): void {
                $securityReset = new SecurityResetLoginCommand(
                    $this->container->make(\Corex\Config\Security\LoginProtection\LoginLockoutStore::class),
                );

                $securityReset->run();
            },
            'definition' => [],
        ];

        $registrations['corex doctor'] = [
            'handler' => function (array $args, array $assoc): void {
                $doctor = new DoctorCommand($this->container->make(HealthModule::class));
                $doctor->run($args, $assoc);
            },
            'definition' => [],
        ];

        $registrations['corex readiness'] = [
            'handler' => function (array $args, array $assoc) use ($root): void {
                $readiness = new ReadinessCommand(ReadinessCommandServices::fromArray([
                    'metadata' => new MetadataConsistencyCheck(),
                    'ciSecurity' => new CiSecurityReadiness(),
                    'root' => $root,
                    'siteScaffolder' => $this->container->make(SiteScaffolder::class),
                    'siteScaffoldValidator' => $this->container->make(SiteScaffoldValidator::class),
                    'deploymentReadiness' => $this->container->make(DeploymentReadinessCheck::class),
                    'componentCoverage' => $this->container->make(ComponentCoverageReadinessCheck::class),
                    'freeProBoundary' => $this->container->make(FreeProBoundaryReadinessCheck::class),
                    'multiAgent' => $this->container->make(MultiAgentReadinessCheck::class),
                ]));

                $readiness->run($args, $assoc);
            },
            'definition' => [],
        ];

        $registrations['corex version'] = [
            'handler' => function (array $args, array $assoc) use ($root): void {
                $version = new VersionCommand(new VersionPlan(), $this->versionFiles($root));
                $version->run($args, $assoc);
            },
            'definition' => [],
        ];

        return $registrations;
    }

    private function makeCommand(): MakeCommand
    {
        $naming = $this->container->make(Naming::class);

        return new MakeCommand(
            $this->container->make(GeneratorEngine::class),
            [
                'model'       => new ModelGenerator($naming),
                'repository'  => new RepositoryGenerator(),
                'controller'  => new ControllerGenerator(),
                'service'     => new ServiceGenerator(),
                'option-page' => new OptionPageGenerator(),
                'guide'       => new GuideGenerator(),
            ],
            $this->container->make(BlockScaffolder::class),
            $this->container->make(GeneratorContext::class),
            $this->container->make(ApiResourceScaffolder::class),
            $this->container->make(SiteScaffolder::class),
        );
    }

    /** @return list<string> */
    private function routeNamespaces(): array
    {
        return array_values(array_unique([
            'corex',
            $this->container->make(GeneratorContext::class)->prefix,
        ]));
    }

    /**
     * @return array{shortdesc: string, synopsis: list<array{type: string, name: string, optional: bool, description: string}>}
     */
    private function resetCommandDefinition(): array
    {
        return [
            'shortdesc' => __('Reset Corex site state, with explicit safeguards for destructive and network scope.', 'corex'),
            'synopsis' => [
                $this->resetFlag('hard', __('Wipe and rebuild the WordPress database.', 'corex')),
                $this->resetFlag('dry-run', __('Preview the reset plan without changing anything.', 'corex')),
                $this->resetFlag(
                    'yes-i-mean-it',
                    __('Supply the typed safeguard required for a hard reset.', 'corex'),
                ),
                $this->resetFlag('network', __('Permit network-scoped reset actions.', 'corex')),
            ],
        ];
    }

    /**
     * @return array{type: string, name: string, optional: bool, description: string}
     */
    private function resetFlag(string $name, string $description): array
    {
        return [
            'type' => 'flag',
            'name' => $name,
            'optional' => true,
            'description' => $description,
        ];
    }

    /**
     * The framework files that carry a version header, a `COREX_*_VERSION` constant, or the docs
     * site's exported `CURRENT_VERSION`, stamped together by `wp corex version` so none of them
     * drifts from the release tag (spec 036). Missing candidates are filtered out, so a checkout
     * without the docs site still stamps everything else.
     *
     * @return list<string>
     */
    private function versionFiles(string $root): array
    {
        $candidates = [
            $root . '/plugins/corex-core/corex-core.php',
            $root . '/plugins/corex-blocks/corex-blocks.php',
            $root . '/plugins/corex-forms/corex-forms.php',
            $root . '/plugins/corex-config/corex-config.php',
            $root . '/theme/style.css',
            $root . '/docs-app/src/version.ts',
            // Stamped by hand until 0.39.0, which is how ROADMAP.md came to sit three releases
            // behind a correct README. Each of these declares the *current* version in prose or in
            // JSON; VersionPlan anchors to the exact sentence, so historical references elsewhere in
            // the same files ("released as v0.38.1") are left alone.
            $root . '/package.json',
            $root . '/README.md',
            $root . '/ROADMAP.md',
            $root . '/PROJECT-STATUS.md',
            $root . '/docs-app/src/content/docs/project-status.md',
        ];

        foreach (glob($root . '/addons/*/*.php') ?: [] as $addonFile) {
            $candidates[] = $addonFile;
        }

        return array_values(array_filter($candidates, 'is_file'));
    }

    private function context(ConfigInterface $config): GeneratorContext
    {
        $base = (string) $config->get('app.path');

        if ($base === '' && defined('WP_CONTENT_DIR')) {
            $base = WP_CONTENT_DIR . '/corex-app';
        }

        return new GeneratorContext(
            $base,
            (string) $config->get('app.namespace', 'App'),
            (string) $config->get('app.prefix', 'corex'),
        );
    }
}
