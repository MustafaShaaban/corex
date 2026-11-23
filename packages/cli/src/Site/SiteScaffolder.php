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
 * namespace `<Name>Site\`) and a site **theme** (`<slug>-theme`) with the client's own prefixes
 * (distinct from Corex), plus the governance set (AGENTS/CLAUDE/README/PROGRESS/DECISIONS/
 * .gitignore + specs/docs). Pure render-all-before-write (mirrors ApiResourceScaffolder):
 * an unresolved placeholder fails loudly without a half-written site. No WordPress.
 *
 * The theme is a child of the CoreX theme (spec 135, DECISIONS #655 and #660): its `style.css`
 * names `Template: corex`, and its `theme.json` lists the CoreX theme's preset lists, custom
 * tokens and layout, read from the CoreX theme's own file at generation (ParentThemeTokens).
 * Templates, parts, patterns and style variations are the parent's until the client's design
 * replaces them with a file of the same name, so the theme gets only its coming-soon page.
 *
 * Options: `force`, `plugin_only`, `theme_only`, `starter`. With `starter` (spec 053 US4) it
 * also emits a runnable, client-namespaced example vertical slice (model, repository, service,
 * controller on the envelope, block, option page, test and REMOVE-EXAMPLE.md) and a theme asset
 * architecture (SCSS/JS, a wp-scripts build and an Assets url/path/version helper); the default
 * and `--minimal` omit it.
 *
 * With the governance set it also writes what lets the site take framework updates (spec 102):
 * `corex-baseline.json`, `UPDATING-COREX.md` and, when told where the repository root is, the
 * client's own CI workflow. It is given the baseline; it never asks git for it.
 */
final class SiteScaffolder
{
    public function __construct(
        private readonly StubRenderer $renderer,
        private readonly string $stubsDir,
        private readonly ParentThemeTokens $parentTheme,
    ) {
    }

    /**
     * @param array<string,bool> $options
     * @param SiteRepository|null $repository The framework baseline to record and, when the site
     *                                        sits at `<root>/sites/<client>`, the repository root
     *                                        (spec 102). Null records an unknown baseline.
     */
    public function scaffold(
        string $rawName,
        string $outputDir,
        array $options = [],
        ?SiteRepository $repository = null,
    ): SiteScaffoldResult {
        $id         = SiteIdentity::from($rawName); // throws InvalidNameException on a reserved/empty name
        $force      = ! empty($options['force']);
        $pluginOnly = ! empty($options['plugin_only']);
        $themeOnly  = ! empty($options['theme_only']);
        $starter    = ! empty($options['starter']);
        $repository ??= SiteRepository::unknown();
        // The directory the site is generated in, which need not be the site's own slug.
        $siteDir    = basename(str_replace('\\', '/', $outputDir));

        $values = [
            'name'              => $id->name,
            'namespace'         => $id->namespace,
            'plugin_slug'       => $id->pluginSlug,
            'theme_slug'        => $id->themeSlug,
            'text_domain'       => $id->textDomain,
            'rest_namespace'    => $id->restNamespace,
            'css_prefix'        => $id->cssPrefix,
            'option_prefix'     => $id->optionPrefix,
            'site_dir'          => $siteDir,
            'baseline_release'  => $repository->baseline->release,
            'baseline_commit'   => $repository->baseline->commit,
            'baseline_recorded' => gmdate('Y-m-d'),
        ];
        // The README describes the theme's build pipeline only when the site is given one.
        $values['theme_assets'] = $starter && ! $pluginOnly
            ? $this->renderer->render($this->readStub('starter/readme-theme-assets'), $values)
            : '';

        // spec 061: the client plugin and theme sit directly under the site root (sites/<client>/<slug>-site,
        // <slug>-theme), not nested under plugins/themes, so the layout reads as one client unit.
        $pluginDir = $outputDir . '/' . $id->pluginSlug;
        $themeDir  = $outputDir . '/' . $id->themeSlug;

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
            $stubFiles += $this->updateSafetyStubs($outputDir, $siteDir, $repository);
        }

        if (! $themeOnly) {
            $stubFiles[$pluginDir . '/' . $id->pluginSlug . '.php']              = 'site/plugin';
            $stubFiles[$pluginDir . '/src/' . $id->namespace . 'ServiceProvider.php'] = 'site/provider';
            // spec 135, FR-034: the plugin builds its own blocks, from a clean install of its own folder.
            $stubFiles[$pluginDir . '/package.json'] = 'site/plugin-package-json';
            foreach (['Models', 'Services', 'Controllers', 'Api', 'Blocks', 'Options'] as $folder) {
                $literals[$pluginDir . '/src/' . $folder . '/.gitkeep'] = '';
            }
        }

        /** @var list<string> $kept an existing theme's files, left as they are (FR-019) */
        $kept = [];
        /** @var bool $childTokens whether the theme.json is written from the CoreX theme's tokens */
        $childTokens = false;

        if (! $pluginOnly) {
            // spec 135, FR-019: a theme that already has a style.css is never converted, and its
            // tokens are never replaced. It keeps both files, whatever else --force rewrites.
            if (is_file($themeDir . '/style.css')) {
                $kept = array_values(array_filter(
                    [$themeDir . '/style.css', $themeDir . '/theme.json'],
                    'is_file',
                ));
            } else {
                $stubFiles[$themeDir . '/style.css'] = 'site/theme-style';
                $childTokens                         = true;
            }
            // spec 101: the page a site in Coming soon mode serves. CoreX registers a default under
            // this template's name and a theme file of the same name replaces it, so a new site's
            // launch page is the one in its own repository from the first day (FR-020).
            $stubFiles[$themeDir . '/templates/coming-soon.html'] = 'site/theme-coming-soon';
        }

        // --starter (spec 053 US4): the runnable example slice + the starter-theme assets.
        if ($starter && ! $themeOnly) {
            // The same plugin file boots it (spec 135, FR-035); the provider also wires the example.
            $stubFiles[$pluginDir . '/src/' . $id->namespace . 'ServiceProvider.php'] = 'starter/provider';
            $stubFiles[$pluginDir . '/src/Models/Example.php']                        = 'starter/model';
            $stubFiles[$pluginDir . '/src/Repositories/ExampleRepository.php']        = 'starter/repository';
            $stubFiles[$pluginDir . '/src/Services/ExampleService.php']               = 'starter/service';
            $stubFiles[$pluginDir . '/src/Controllers/ExampleController.php']         = 'starter/controller';
            $stubFiles[$pluginDir . '/src/Blocks/ExampleRenderer.php']                = 'starter/renderer';
            $stubFiles[$pluginDir . '/src/Blocks/example/block.json']                 = 'starter/block-json';
            $stubFiles[$pluginDir . '/src/Blocks/example/index.js']                   = 'starter/block-js';
            $stubFiles[$pluginDir . '/src/Blocks/example/style.scss']                 = 'starter/block-scss';
            $stubFiles[$pluginDir . '/src/Options/ExampleOptions.php']                = 'starter/options';
            $stubFiles[$pluginDir . '/tests/ExampleTest.php']                         = 'starter/test';
            // spec 102: the example test shipped with nothing that could run it.
            $stubFiles[$pluginDir . '/phpunit.xml.dist']                              = 'starter/phpunit-xml';
            $stubFiles[$pluginDir . '/tests/bootstrap.php']                           = 'starter/test-bootstrap';
            $stubFiles[$pluginDir . '/REMOVE-EXAMPLE.md']                             = 'starter/remove';
        }

        if ($starter && ! $pluginOnly) {
            // SCSS/JS/image pipeline (spec 062): sources in assets/src/{scss,js,images}/ build to
            // assets/{css,js,images}/ via `npm run build`; functions.php enqueues the COMPILED output
            // through the CoreX asset helpers (Corex\Assets\*) — never hardcoded paths.
            $stubFiles[$themeDir . '/package.json']                = 'starter/theme-package-json';
            $stubFiles[$themeDir . '/functions.php']               = 'starter/theme-functions';
            $stubFiles[$themeDir . '/assets/src/scss/main.scss']   = 'starter/theme-scss';
            $stubFiles[$themeDir . '/assets/src/js/main.js']       = 'starter/theme-js';
            $stubFiles[$themeDir . '/scripts/optimize-images.mjs'] = 'starter/theme-optimize-images';
            $literals[$themeDir . '/assets/src/images/.gitkeep']   = "# Source images for `npm run images`. Built output (with .webp) goes to ../../images/.\n";
            $literals[$themeDir . '/assets/css/.gitkeep']          = "# Built CSS — `npm run styles`. Do not edit by hand.\n";
            $literals[$themeDir . '/assets/js/.gitkeep']           = "# Built JS — `npm run scripts`. Do not edit by hand.\n";
            $literals[$themeDir . '/assets/images/.gitkeep']       = "# Optimized images — `npm run images`. Do not edit by hand.\n";
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

        if ($childTokens) {
            // spec 135, FR-011 and FR-012: the CoreX theme's own tokens, or no site at all.
            try {
                $rendered[$themeDir . '/theme.json'] = $this->parentTheme->childThemeJson();
            } catch (ParentThemeUnavailable $e) {
                return SiteScaffoldResult::parentThemeUnavailable($outputDir, $e);
            }
        }

        return $this->writeAll($outputDir, $rendered, $kept);
    }

    /**
     * What lets the site take framework updates (spec 102): the baseline record, the client's
     * copy of the update checklist, and (only when the repository root is known) the client's
     * own CI workflow. The workflow is the one file written outside the site root, because a
     * workflow runs from `.github/workflows/` and nowhere else; its name carries the site
     * directory so no framework release can ship a file that collides with it.
     *
     * @return array<string,string> path => stub name
     */
    private function updateSafetyStubs(string $outputDir, string $siteDir, SiteRepository $repository): array
    {
        $stubs = [
            $outputDir . '/corex-baseline.json' => 'site/baseline',
            $outputDir . '/UPDATING-COREX.md'   => 'site/updating',
        ];

        if ($repository->root !== null) {
            $stubs[$repository->root . '/.github/workflows/site-' . $siteDir . '.yml'] = 'site/workflow';
        }

        return $stubs;
    }

    /**
     * @param array<string,string> $rendered path => contents
     * @param list<string>         $kept     existing files left untouched
     */
    private function writeAll(string $outputDir, array $rendered, array $kept): SiteScaffoldResult
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

        return SiteScaffoldResult::created($outputDir, $written, $kept);
    }

    private function readStub(string $name): string
    {
        return (string) file_get_contents(
            rtrim($this->stubsDir, '/\\') . DIRECTORY_SEPARATOR . $name . '.stub'
        );
    }
}
