<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli;

defined('ABSPATH') || exit;

use Corex\Cli\Commands\DocsCommand;
use Corex\Cli\Commands\DoctorCommand;
use Corex\Cli\Commands\MakeCommand;
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

        $naming = $this->container->make(Naming::class);
        $command = new MakeCommand(
            $this->container->make(GeneratorEngine::class),
            [
                'model'       => new ModelGenerator($naming),
                'repository'  => new RepositoryGenerator(),
                'controller'  => new ControllerGenerator(),
                'service'     => new ServiceGenerator(),
                'option-page' => new OptionPageGenerator(),
            ],
            $this->container->make(BlockScaffolder::class),
            $this->container->make(GeneratorContext::class),
            $this->container->make(ApiResourceScaffolder::class),
            $this->container->make(SiteScaffolder::class),
        );

        foreach (['model', 'repository', 'controller', 'service', 'option-page', 'block', 'api-resource', 'site'] as $type) {
            WP_CLI::add_command(
                "corex make:{$type}",
                static function (array $args, array $assoc) use ($command, $type): void {
                    $command->run($type, $args, $assoc);
                },
            );
        }

        // REST discovery + OpenAPI (spec 046): list the Corex/app routes and emit an API doc.
        $routesReader = new RoutesReader();
        $namespaces   = array_values(array_unique(['corex', $this->container->make(GeneratorContext::class)->prefix]));

        WP_CLI::add_command(
            'corex routes:list',
            static function () use ($routesReader, $namespaces): void {
                foreach ((new RouteList())->lines($routesReader->read($namespaces)) as $line) {
                    WP_CLI::log($line);
                }
            },
        );

        WP_CLI::add_command(
            'corex api:docs',
            static function () use ($routesReader, $namespaces): void {
                $version = defined('COREX_CORE_VERSION') ? COREX_CORE_VERSION : '0.0.0';
                $doc     = (new ApiDocsGenerator())->generate($routesReader->read($namespaces), 'Corex API', $version);
                WP_CLI::log((string) wp_json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            },
        );

        // Asset diagnostics + cache reset (spec 047).
        $assets = $this->container->make(AssetManager::class);

        WP_CLI::add_command(
            'corex assets:doctor',
            static function () use ($assets): void {
                $report  = new AssetReport();
                $present = is_file(COREX_CORE_PATH . 'build/manifest.json');
                $samples = ['build/index.js' => $assets->version('build/index.js')];

                foreach ($report->lines($report->build($assets->environment(), $present, $samples)) as $line) {
                    WP_CLI::log($line);
                }
            },
        );

        WP_CLI::add_command(
            'corex cache:clear',
            static function (): void {
                delete_transient('corex_asset_manifest');
                WP_CLI::success('Corex asset cache cleared.');
            },
        );

        // Team ops + distribution (spec 050): compliance check, release packaging, local docs.
        $frameworkPaths = [
            'plugins/corex-core', 'plugins/corex-blocks', 'plugins/corex-config', 'plugins/corex-forms',
            'theme', 'packages/cli', 'addons/corex-ui', 'addons/corex-email', 'addons/corex-captcha',
            'addons/corex-newsletter', 'addons/corex-careers', 'addons/corex-bookings', 'addons/corex-media',
            'addons/corex-kit-company', 'addons/corex-kit-portfolio', 'addons/corex-kit-woo',
        ];
        $cliRoot = dirname(__DIR__, 3);

        WP_CLI::add_command(
            'corex compliance:check',
            static function (array $args, array $assoc) use ($frameworkPaths): void {
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
        );

        WP_CLI::add_command(
            'corex package:update',
            static function (array $args, array $assoc) use ($frameworkPaths): void {
                $version  = (string) ($args[0] ?? '');
                $download = (string) ($assoc['download-url'] ?? '');
                $plan     = new ReleasePackagePlan($frameworkPaths, ['/tests/', '/specs/', 'node_modules', '.git/', 'wp-config', '.env']);
                $manifest = $plan->manifest($version, $download, (string) ($assoc['changelog'] ?? 'Bug fixes and improvements.'));

                WP_CLI::log((string) wp_json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                WP_CLI::success(sprintf('Manifest planned for %s (framework-only; build the ZIP from the included paths).', $version));
            },
        );

        WP_CLI::add_command(
            'corex docs:sync',
            static function () use ($cliRoot): void {
                $built = $cliRoot . '/docs-app/dist';
                if (! is_dir($built)) {
                    WP_CLI::warning('No built docs found — run `npm run build` in docs-app/ first.');

                    return;
                }
                WP_CLI::success(sprintf('Docs available at %s — copy into .corex/docs/ (git-ignored) to read locally.', $built));
            },
        );

        WP_CLI::add_command(
            'corex docs:serve',
            static function (): void {
                WP_CLI::log('Serve the docs locally: `cd docs-app && npm run dev` → http://localhost:4321');
            },
        );

        $root = dirname(__DIR__, 3);
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

        WP_CLI::add_command(
            'corex docs:generate',
            static function (array $args, array $assoc) use ($docs): void {
                $docs->generate($args, $assoc);
            },
        );

        $reset = new ResetCommand(new ResetPlanner(), new ResetGate(), new ResetExecutor());

        WP_CLI::add_command(
            'corex reset',
            static function (array $args, array $assoc) use ($reset): void {
                $reset->run($args, $assoc);
            },
        );

        $securityReset = new SecurityResetLoginCommand(
            $this->container->make(\Corex\Config\Security\LoginProtection\LoginLockoutStore::class),
        );

        WP_CLI::add_command(
            'corex security reset-login',
            static function () use ($securityReset): void {
                $securityReset->run();
            },
        );

        $doctor = new DoctorCommand($this->container->make(HealthModule::class));

        WP_CLI::add_command(
            'corex doctor',
            static function (array $args, array $assoc) use ($doctor): void {
                $doctor->run($args, $assoc);
            },
        );

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

        WP_CLI::add_command(
            'corex readiness',
            static function (array $args, array $assoc) use ($readiness): void {
                $readiness->run($args, $assoc);
            },
        );

        $version = new VersionCommand(new VersionPlan(), $this->versionFiles($root));

        WP_CLI::add_command(
            'corex version',
            static function (array $args, array $assoc) use ($version): void {
                $version->run($args, $assoc);
            },
        );
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
