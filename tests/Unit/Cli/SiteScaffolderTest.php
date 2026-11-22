<?php

/**
 * Unit tests for the client-site scaffolder (make:site, spec 049, US1/US2).
 *
 * @package Corex\Tests\Unit\Cli
 */

declare(strict_types=1);

use Corex\Cli\Generators\StubRenderer;
use Corex\Cli\Site\SiteIdentity;
use Corex\Cli\Site\SiteScaffolder;
use Corex\Cli\Site\SiteScaffoldResult;

function siteStubsDir(): string
{
    return dirname(__DIR__, 3) . '/packages/cli/stubs';
}

function tempSiteBase(): string
{
    $dir = sys_get_temp_dir() . '/corex_site_' . uniqid('', true);
    mkdir($dir);

    return $dir;
}

function siteScaffolder(): SiteScaffolder
{
    return new SiteScaffolder(new StubRenderer(), siteStubsDir());
}

it('scaffolds the plugin, theme, and governance set under the flat sites/<client> layout', function () {
    $base   = tempSiteBase();
    $result = siteScaffolder()->scaffold('Acme', $base);

    expect($result->status)->toBe(SiteScaffoldResult::CREATED)
        // spec 061: client plugin/theme sit directly under the site root (no plugins/ or themes/ nesting)
        ->and(is_file($base . '/acme-site/acme-site.php'))->toBeTrue()
        ->and(is_file($base . '/acme-site/src/AcmeSiteServiceProvider.php'))->toBeTrue()
        ->and(is_file($base . '/acme-theme/style.css'))->toBeTrue()
        ->and(is_file($base . '/acme-theme/theme.json'))->toBeTrue()
        ->and(is_dir($base . '/plugins'))->toBeFalse()
        ->and(is_dir($base . '/themes'))->toBeFalse()
        ->and(is_file($base . '/AGENTS.md'))->toBeTrue()
        ->and(is_file($base . '/.gitignore'))->toBeTrue();
});

it('scaffolds header/footer/front-page override points with ownership guidance', function () {
    $base = tempSiteBase();
    siteScaffolder()->scaffold('Acme', $base);

    $header = (string) file_get_contents($base . '/acme-theme/parts/header.html');
    $footer = (string) file_get_contents($base . '/acme-theme/parts/footer.html');
    $front  = (string) file_get_contents($base . '/acme-theme/templates/front-page.html');

    expect(is_file($base . '/acme-theme/parts/header.html'))->toBeTrue()
        ->and(is_file($base . '/acme-theme/parts/footer.html'))->toBeTrue()
        ->and(is_file($base . '/acme-theme/templates/front-page.html'))->toBeTrue()
        // the override points explain brand-via-tokens vs structure-in-client-theme
        ->and($header)->toContain('override')
        ->and($header)->toContain('theme.json')
        ->and($footer)->toContain('override')
        ->and($front)->toContain('front page');
});

it('scaffolds the client image-optimization pipeline in --starter mode', function () {
    $base = tempSiteBase();
    siteScaffolder()->scaffold('Acme', $base, ['starter' => true]);

    $pkg = (string) file_get_contents($base . '/acme-theme/package.json');

    expect(is_file($base . '/acme-theme/scripts/optimize-images.mjs'))->toBeTrue()
        ->and(is_file($base . '/acme-theme/assets/src/images/.gitkeep'))->toBeTrue()
        ->and($pkg)->toContain('"images"')
        ->and($pkg)->toContain('npm run images')
        ->and($pkg)->toContain('sharp');
});

it('generates valid PHP and a valid theme.json', function () {
    $base = tempSiteBase();
    siteScaffolder()->scaffold('Acme', $base);

    foreach (['/acme-site/acme-site.php', '/acme-site/src/AcmeSiteServiceProvider.php'] as $php) {
        exec('php -l ' . escapeshellarg($base . $php) . ' 2>&1', $out, $exit);
        expect($exit)->toBe(0);
    }

    expect(json_decode((string) file_get_contents($base . '/acme-theme/theme.json'), true))->toBeArray();
});

it('writes governance stating the client-only edit boundary + one-feature-one-PR', function () {
    $base = tempSiteBase();
    siteScaffolder()->scaffold('Acme', $base);

    $agents = (string) file_get_contents($base . '/AGENTS.md');

    expect($agents)->toContain('do not edit Corex framework folders')
        ->and($agents)->toContain('acme-site/')
        ->and($agents)->toContain('One feature = one branch = one spec folder = one PR')
        ->and($agents)->toContain('AcmeSite\\')
        // spec 061: the generated stub carries the Role Gate + handoff guidance for Client Site Mode.
        ->and($agents)->toContain('CLIENT SITE MODE')
        ->and($agents)->toContain('do not edit')
        ->and($agents)->toContain('dist/')
        ->and($agents)->toContain('NEXT STEP');
});

it('ignores local AI/cache folders in the generated .gitignore', function () {
    $base = tempSiteBase();
    siteScaffolder()->scaffold('Acme', $base);

    $gitignore = (string) file_get_contents($base . '/.gitignore');

    expect($gitignore)->toContain('.claude/local/')
        ->and($gitignore)->toContain('.corex/cache/')
        ->and($gitignore)->toContain('.ai/');
});

it('is idempotent without --force and regenerates with it', function () {
    $base = tempSiteBase();

    expect(siteScaffolder()->scaffold('Acme', $base)->status)->toBe(SiteScaffoldResult::CREATED)
        ->and(siteScaffolder()->scaffold('Acme', $base)->status)->toBe(SiteScaffoldResult::SKIPPED)
        ->and(siteScaffolder()->scaffold('Acme', $base, ['force' => true])->status)->toBe(SiteScaffoldResult::CREATED);
});

it('honours --plugin-only and --theme-only', function () {
    $pluginOnly = tempSiteBase();
    siteScaffolder()->scaffold('Acme', $pluginOnly, ['plugin_only' => true]);

    expect(is_file($pluginOnly . '/acme-site/acme-site.php'))->toBeTrue()
        ->and(is_dir($pluginOnly . '/acme-theme'))->toBeFalse();

    $themeOnly = tempSiteBase();
    siteScaffolder()->scaffold('Acme', $themeOnly, ['theme_only' => true]);

    expect(is_file($themeOnly . '/acme-theme/style.css'))->toBeTrue()
        ->and(is_dir($themeOnly . '/acme-site'))->toBeFalse();
});

// spec 101 — every new client theme starts with a coming-soon page it can edit (T052, FR-020).

it('gives a generated client theme its own coming-soon template', function () {
    $base = tempSiteBase();
    siteScaffolder()->scaffold('Acme', $base);

    $template = (string) file_get_contents($base . '/acme-theme/templates/coming-soon.html');

    preg_match_all('/<!-- wp:([a-z0-9\/-]+)/', $template, $blocks);
    preg_match_all('/<!-- wp:[a-z0-9\/-]+ (?:\{.*?\} )?-->/s', $template, $opened);
    preg_match_all('/<!-- \/wp:[a-z0-9\/-]+ -->/', $template, $closed);

    // The file name is the contract: `coming-soon` is the slug CoreX's default is registered
    // under, and a theme file of that name is what replaces it.
    expect($template)->not->toBe('')
        ->and($blocks[1])->not->toBeEmpty()
        // Core blocks only, so the page does not depend on any plugin's blocks being active.
        ->and(array_filter($blocks[1], static fn (string $name): bool => str_contains($name, '/')))->toBe([])
        // No header or footer: every link in one is redirected straight back to this page.
        ->and($template)->not->toContain('wp:template-part')
        ->and(count($opened[0]))->toBe(count($closed[0]))
        ->and($template)->toContain('Coming soon')
        // Nothing left for the renderer to fill in.
        ->and($template)->not->toMatch('/\{\{\s*[\w.]+\s*\}\}/');
});

it('tells whoever opens the generated template what it is and where its assets go', function () {
    $base = tempSiteBase();
    siteScaffolder()->scaffold('Acme', $base);

    $template = (string) file_get_contents($base . '/acme-theme/templates/coming-soon.html');

    expect($template)->toStartWith('<!--')
        ->and($template)->toContain('Acme')
        ->and($template)->toContain('Coming soon mode')
        // The action a designed page loads its own stylesheet and fonts on (T054).
        ->and($template)->toContain('corex_coming_soon_enqueue_assets');
});

it('generates the coming-soon template with the theme, and not without it', function () {
    $pluginOnly = tempSiteBase();
    siteScaffolder()->scaffold('Acme', $pluginOnly, ['plugin_only' => true]);

    $themeOnly = tempSiteBase();
    siteScaffolder()->scaffold('Acme', $themeOnly, ['theme_only' => true]);

    $starter = tempSiteBase();
    siteScaffolder()->scaffold('Acme', $starter, ['starter' => true]);

    expect(is_file($pluginOnly . '/acme-theme/templates/coming-soon.html'))->toBeFalse()
        ->and(is_file($themeOnly . '/acme-theme/templates/coming-soon.html'))->toBeTrue()
        ->and(is_file($starter . '/acme-theme/templates/coming-soon.html'))->toBeTrue();
});

/**
 * The README documented a theme build pipeline — `assets/src/`, `npm run build`, the
 * `Corex\Assets\*` calls — whether or not the site had one. Only `--starter` generates it (#251).
 */
it('documents the theme’s asset pipeline only for a site that has one', function () {
    $lean = tempSiteBase();
    siteScaffolder()->scaffold('Acme', $lean);

    $starter = tempSiteBase();
    siteScaffolder()->scaffold('Acme', $starter, ['starter' => true]);

    expect((string) file_get_contents($lean . '/README.md'))
        ->not->toContain('npm run build')
        ->not->toContain('assets/src')
        ->and((string) file_get_contents($starter . '/README.md'))
        ->toContain('## Theme assets')
        ->toContain('acme-theme/assets/src/{scss,js,images}/')
        ->toContain('npm run build');
});

/**
 * The generated AGENTS.md once said the `make:*` generators write into the client plugin when they
 * wrote elsewhere, and its specs landed in the framework's own `specs/` (#251). It says where each
 * thing goes; the specs path is the site's own folder (spec 135, FR-004).
 */
it('tells a client where its specs and generated code go', function () {
    $base = tempSiteBase();
    siteScaffolder()->scaffold('Acme', $base);

    expect((string) file_get_contents($base . '/AGENTS.md'))
        ->toContain('acme-site/src')
        ->toContain('APP_PATH')
        ->toContain('sites/' . basename($base) . '/specs/NNN-name/spec.md')
        ->toContain('never in the root `specs/`')
        ->not->toContain('by hand');
});

// Spec 135, slice 1 (G-002): the generated agent files teach the CoreX loop, not the retired chain.

/**
 * @return array{agents:string, claude:string, base:string}
 */
function generatedAgentFiles(): array
{
    $base = tempSiteBase();
    siteScaffolder()->scaffold('Acme', $base);

    return [
        'agents' => (string) file_get_contents($base . '/AGENTS.md'),
        'claude' => (string) file_get_contents($base . '/CLAUDE.md'),
        'base'   => $base,
    ];
}

it('FR-004: describes the CoreX loop as a client site runs it, its steps in order', function () {
    $files   = generatedAgentFiles();
    $agents  = $files['agents'];
    $site    = 'sites/' . basename($files['base']);
    $heading = '## The CoreX loop, as this site runs it';
    $start   = strpos($agents, $heading);

    expect($start)->not->toBeFalse();

    // The section alone: the Role Gate above it also names the Guard Gate.
    $section = substr($agents, (int) $start);
    $next    = strpos($section, "\n## ", strlen($heading));
    $section = $next === false ? $section : substr($section, 0, $next);

    $steps = [
        '**Grill**',
        '**Research**',
        '**Spec**',
        '**Owner-only steps**',
        '**One slice at a time, test first.**',
        '**Guard Gate**',
        'the measured UI review',
        '`debate-review --local`',
        '**Pull request**',
        'a retro after a release',
    ];

    $positions = array_combine(
        $steps,
        array_map(static fn (string $step): int|false => strpos($section, $step), $steps)
    );
    $inOrder = $positions;
    asort($inOrder);

    expect(array_keys(array_filter($positions, static fn (int|false $at): bool => $at === false)))->toBe([])
        // Each step appears after the one before it.
        ->and(array_keys($inOrder))->toBe($steps)
        ->and($section)->toContain('The root `AGENTS.md`, "The CoreX loop", holds each step in full')
        // Every file the root loop writes at the repository root has this site's own path (FR-004).
        ->and($section)->toContain('a file written there is drift in `npm run verify:framework`')
        ->and($section)->toContain("`{$site}/specs/`")
        ->and($section)->toContain("`{$site}/DECISIONS.md`")
        ->and($section)->toContain("`{$site}/PROGRESS.md`")
        ->and($section)->toContain("`{$site}/docs/glossary.md`")
        ->and($section)->toContain("`{$site}/design/handoffs/`")
        ->and($section)->toContain('this site\'s `DECISIONS.md`')
        ->and($section)->toContain('`specs/NNN-name/research.md`')
        ->and($section)->toContain('(`FR-001`)')
        ->and($section)->toContain('Bugs start by reproducing the bug and proving its cause');
});

it('FR-005: carries the model routing table, its seven rows, and who commits', function () {
    $files  = generatedAgentFiles();
    $agents = $files['agents'];
    $site   = 'sites/' . basename($files['base']);

    $rows = [
        ['the main session', 'Fable', 'high'],
        ['`corex-builder`', 'Opus', 'medium (high for a UI slice or a bug)'],
        ['`corex-tester`', 'Sonnet', 'medium'],
        ['`corex-scout`', 'Haiku', 'low'],
        ['`corex-reviewer`', 'Fable', 'high'],
        ['`corex-ui-reviewer`', 'Fable', 'high'],
        ['the `debate-review` lane', 'Codex', 'high'],
    ];

    foreach ($rows as [$who, $model, $effort]) {
        expect($agents)->toContain("| {$who} | {$model} | {$effort} |");
    }

    expect($agents)->toContain('## Models, effort and agents')
        ->and($agents)->toContain('`.claude/agents/`')
        ->and($agents)->toContain('Max effort only when the owner asks, for one task')
        ->and($agents)->toContain('Agents never commit')
        // A brief names Client Site Mode and the site's folder, or the agent takes the work for the framework's.
        ->and($agents)->toContain('Every brief to an agent says the work is in Client Site Mode')
        ->and($agents)->toContain("(`{$site}/`)");
});

it('FR-005: the builder agent may work in a client site when its brief says so', function () {
    $builder = (string) file_get_contents(dirname(__DIR__, 3) . '/.claude/agents/corex-builder.md');

    expect($builder)->toContain('**A client site\'s slice.**')
        ->and($builder)->toContain('`sites/<client>/specs/NNN-name/spec.md`')
        ->and($builder)->toContain('Edit `sites/<client>/` in a framework slice, or anything outside it in a client site\'s slice.')
        // The rule it replaces refused every client folder outright.
        ->and($builder)->not->toContain('Edit `wp/wp-content/`, `dist/`, `sites/<client>/` or another slice\'s files.');
});

it('FR-006: carries the UI steps for a client site, in the site\'s own text domain', function () {
    $agents     = generatedAgentFiles()['agents'];
    $textDomain = SiteIdentity::from('Acme')->textDomain;

    expect($agents)->toContain('### UI, UX and front end')
        ->and($agents)->toContain('the functional contract')
        ->and($agents)->toContain('designed first and approved by the owner before it is built')
        ->and($agents)->toContain('`ui-ux-pro-max`')
        ->and($agents)->toContain('tokens from the theme\'s `theme.json` only')
        ->and($agents)->toContain('logical CSS properties')
        ->and($agents)->toContain("every string translatable in `{$textDomain}`")
        ->and($agents)->toContain('never judge it from a screenshot')
        ->and($agents)->toContain('375px')
        ->and($agents)->toContain('200% zoom')
        ->and($agents)->toContain('right to left')
        ->and($agents)->toContain('`corex-ui-reviewer`')
        ->and($agents)->toContain('WCAG 2.2 AA')
        ->and($agents)->toContain('One recorded round for the owner');
});

it('FR-007: states the Role Gate and the handoff in the loop\'s words', function () {
    $files = generatedAgentFiles();

    expect($files['agents'])->toContain('the spec decides **what**')
        ->and($files['agents'])->toContain('Follow **the CoreX loop**, the **Guard Gate** and **UI/UX ProMax**')
        ->and($files['agents'])->toContain(
            'SUMMARY / WORKSPACE / MODE / SPEC STATUS / VERIFICATION / BLOCKERS / RECOMMENDED NEXT STEP'
        )
        ->and($files['claude'])->toContain(
            'Follow the CoreX loop, the Guard Gate, and UI/UX ProMax (this site\'s version of the loop and '
            . 'the model routing table are in `AGENTS.md`)'
        )
        ->and($files['claude'])->toContain('This file mirrors `AGENTS.md`');
});

it('FR-007: writes no em dash into the generated agent files', function () {
    $files = generatedAgentFiles();

    expect($files['agents'])->not->toContain("\u{2014}")
        ->and($files['claude'])->not->toContain("\u{2014}");
});

/**
 * Read from the stub directory itself, so a site stub added later is held to the same rule (FR-040).
 */
it('FR-007: names the retired command chain in no site stub', function () {
    $directory = siteStubsDir() . '/site';
    $stubs     = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );
    $offenders = [];
    $read      = 0;

    foreach ($stubs as $stub) {
        if (! $stub->isFile()) {
            continue;
        }

        $read++;
        $contents = (string) file_get_contents($stub->getPathname());

        if (preg_match('/spec\s*kit|speckit/i', $contents) === 1) {
            $offenders[] = $stub->getFilename();
        }
    }

    expect($read)->toBeGreaterThan(0)
        ->and($offenders)->toBe([]);
});
