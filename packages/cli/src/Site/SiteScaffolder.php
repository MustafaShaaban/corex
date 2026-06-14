<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Site;

defined('ABSPATH') || exit;

use Corex\Cli\Generators\StubRenderer;

/**
 * Scaffolds a client site (spec 049) from one name: a site **plugin** (`<slug>-site`,
 * namespace `<Name>Site\`) + a site **theme** (`<slug>`) with the client's own prefixes
 * (distinct from Corex), plus the governance set (AGENTS/CLAUDE/README/PROGRESS/DECISIONS/
 * .gitignore + specs/docs). Pure render-all-before-write (mirrors ApiResourceScaffolder):
 * an unresolved placeholder fails loudly without a half-written site. No WordPress.
 *
 * Options: `force`, `plugin_only`, `theme_only`. (`starter` is layered on in US3.)
 */
final class SiteScaffolder
{
    public function __construct(
        private readonly StubRenderer $renderer,
        private readonly string $stubsDir,
    ) {
    }

    /**
     * @param array<string,bool> $options
     */
    public function scaffold(string $rawName, string $outputDir, array $options = []): SiteScaffoldResult
    {
        $id         = SiteIdentity::from($rawName); // throws InvalidNameException on a reserved/empty name
        $force      = ! empty($options['force']);
        $pluginOnly = ! empty($options['plugin_only']);
        $themeOnly  = ! empty($options['theme_only']);

        $values = [
            'name'           => $id->name,
            'namespace'      => $id->namespace,
            'plugin_slug'    => $id->pluginSlug,
            'theme_slug'     => $id->themeSlug,
            'text_domain'    => $id->textDomain,
            'rest_namespace' => $id->restNamespace,
            'css_prefix'     => $id->cssPrefix,
            'option_prefix'  => $id->optionPrefix,
        ];

        $pluginDir = $outputDir . '/plugins/' . $id->pluginSlug;
        $themeDir  = $outputDir . '/themes/' . $id->themeSlug;

        /** @var array<string,string> $stubFiles path => stub name */
        $stubFiles = [];
        /** @var array<string,string> $literals  path => literal content */
        $literals = [];

        if (! $pluginOnly && ! $themeOnly) {
            foreach (['AGENTS.md', 'CLAUDE.md', 'README.md', 'PROGRESS.md', 'DECISIONS.md'] as $doc) {
                $stubFiles[$outputDir . '/' . $doc] = 'site/' . $doc;
            }
            $stubFiles[$outputDir . '/.gitignore'] = 'site/gitignore';
            $literals[$outputDir . '/specs/.gitkeep'] = '';
            $literals[$outputDir . '/docs/.gitkeep']  = '';
        }

        if (! $themeOnly) {
            $stubFiles[$pluginDir . '/' . $id->pluginSlug . '.php']              = 'site/plugin';
            $stubFiles[$pluginDir . '/src/' . $id->namespace . 'ServiceProvider.php'] = 'site/provider';
            foreach (['Models', 'Services', 'Controllers', 'Api', 'Blocks', 'Options'] as $folder) {
                $literals[$pluginDir . '/src/' . $folder . '/.gitkeep'] = '';
            }
        }

        if (! $pluginOnly) {
            $stubFiles[$themeDir . '/style.css'] = 'site/theme-style';
            $stubFiles[$themeDir . '/theme.json'] = 'site/theme-json';
            $literals[$themeDir . '/templates/index.html'] = "<!-- wp:template-part {\"slug\":\"header\"} /-->\n<!-- wp:post-content /-->";
            $literals[$themeDir . '/parts/header.html']    = "<!-- wp:site-title /-->";
        }

        $marker = $themeOnly ? $themeDir . '/style.css' : $pluginDir . '/' . $id->pluginSlug . '.php';
        if (is_file($marker) && ! $force) {
            return SiteScaffoldResult::skipped($outputDir);
        }

        // Render everything before writing anything.
        $rendered = $literals;
        foreach ($stubFiles as $path => $stub) {
            $rendered[$path] = $this->renderer->render($this->readStub($stub), $values);
        }

        return $this->writeAll($outputDir, $rendered);
    }

    /**
     * @param array<string,string> $rendered path => contents
     */
    private function writeAll(string $outputDir, array $rendered): SiteScaffoldResult
    {
        $written = [];

        foreach ($rendered as $path => $contents) {
            $dir = dirname($path);

            if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
                return SiteScaffoldResult::error($outputDir, sprintf('Could not create directory: %s', $dir));
            }

            if (file_put_contents($path, $contents) === false) {
                return SiteScaffoldResult::error($outputDir, sprintf('Could not write: %s', $path));
            }

            $written[] = $path;
        }

        return SiteScaffoldResult::created($outputDir, $written);
    }

    private function readStub(string $name): string
    {
        return (string) file_get_contents(
            rtrim($this->stubsDir, '/\\') . DIRECTORY_SEPARATOR . $name . '.stub'
        );
    }
}
