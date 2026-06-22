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
 * Options: `force`, `plugin_only`, `theme_only`, `starter`. With `starter` (spec 053 US4) it
 * also emits a runnable, client-namespaced example vertical slice (model → repository →
 * service → controller-on-envelope → block → option page → test + REMOVE-EXAMPLE.md) and a
 * starter-theme asset architecture (SCSS/JS + wp-scripts build + an Assets url/path/version
 * helper); the default and `--minimal` omit it.
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
        $starter    = ! empty($options['starter']);

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

        // spec 061: the client plugin + theme sit directly under the site root (sites/<client>/<slug>-site,
        // <slug>-theme) — not nested under plugins/themes — so the layout reads as one client unit.
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
            $literals[$themeDir . '/templates/index.html'] = "<!-- wp:template-part {\"slug\":\"header\",\"tagName\":\"header\"} /-->\n<!-- wp:post-content /-->\n<!-- wp:template-part {\"slug\":\"footer\",\"tagName\":\"footer\"} /-->";
            // Structural header/footer override points (spec 061). Brand-only changes (colours, fonts,
            // spacing) belong in theme.json / a style variation — NOT here, and never in the CoreX parent
            // theme. Edit these parts when the approved client design changes header/footer structure.
            $literals[$themeDir . '/parts/header.html'] = "<!-- Client header — override CoreX's default header structure here. Brand-only changes: use theme.json/tokens, not markup edits. -->\n<!-- wp:site-title /-->";
            $literals[$themeDir . '/parts/footer.html'] = "<!-- Client footer — override structure here; brand via tokens. -->\n<!-- wp:paragraph {\"align\":\"center\"} -->\n<p class=\"has-text-align-center\"></p>\n<!-- /wp:paragraph -->";
            $literals[$themeDir . '/templates/front-page.html'] = "<!-- Client front page — compose CoreX UI patterns + core blocks here; this layout is yours to own. -->\n<!-- wp:template-part {\"slug\":\"header\",\"tagName\":\"header\"} /-->\n<!-- wp:post-content /-->\n<!-- wp:template-part {\"slug\":\"footer\",\"tagName\":\"footer\"} /-->";
        }

        // --starter (spec 053 US4): the runnable example slice + the starter-theme assets.
        if ($starter && ! $themeOnly) {
            // Swap the skeleton plugin + provider for versions that autoload and wire the example.
            $stubFiles[$pluginDir . '/' . $id->pluginSlug . '.php']                   = 'starter/plugin';
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
