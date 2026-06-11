<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli;

defined('ABSPATH') || exit;

use Corex\Cli\Commands\DocsCommand;
use Corex\Cli\Commands\MakeCommand;
use Corex\Cli\Docs\ClassDocReader;
use Corex\Cli\Docs\DocsGenerator;
use Corex\Cli\Docs\MarkdownDocRenderer;
use Corex\Cli\Generators\BlockScaffolder;
use Corex\Cli\Generators\ControllerGenerator;
use Corex\Cli\Generators\GeneratorContext;
use Corex\Cli\Generators\GeneratorEngine;
use Corex\Cli\Generators\ModelGenerator;
use Corex\Cli\Generators\RepositoryGenerator;
use Corex\Cli\Generators\ServiceGenerator;
use Corex\Cli\Generators\StubRenderer;
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
                'model'      => new ModelGenerator($naming),
                'repository' => new RepositoryGenerator(),
                'controller' => new ControllerGenerator(),
                'service'    => new ServiceGenerator(),
            ],
            $this->container->make(BlockScaffolder::class),
            $this->container->make(GeneratorContext::class),
        );

        foreach (['model', 'repository', 'controller', 'service', 'block'] as $type) {
            WP_CLI::add_command(
                "corex make:{$type}",
                static function (array $args, array $assoc) use ($command, $type): void {
                    $command->run($type, $args, $assoc);
                },
            );
        }

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
