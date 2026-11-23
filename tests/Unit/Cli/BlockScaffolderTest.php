<?php

/**
 * Unit tests for the block scaffolder (make:block): a complete, valid dynamic block from one name.
 * By default the block is edited in place, with its `parity.json` (spec 136, FR-014, FR-015, User
 * Story 3; DECISIONS #656); with `--records` it previews the server render, inert.
 *
 * @package Corex\Tests\Unit\Cli
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Cli\Generators\BlockScaffolder;
use Corex\Cli\Generators\BlockScaffoldResult;
use Corex\Cli\Generators\GeneratorContext;
use Corex\Cli\Generators\StubRenderer;
use Corex\Cli\Support\InvalidNameException;
use Corex\Cli\Support\Naming;
use Corex\Tests\Support\ParityTree;

function blockStubsDir(): string
{
    return dirname(__DIR__, 3) . '/packages/cli/stubs';
}

function tempBlockBase(): string
{
    $dir = sys_get_temp_dir() . '/corex_block_' . uniqid('', true);
    mkdir($dir);

    return $dir;
}

function scaffolder(): BlockScaffolder
{
    return new BlockScaffolder(new StubRenderer(), new Naming(), blockStubsDir());
}

/** The block a test scaffolds, as `[base, block folder]`. */
function scaffoldedBlock(string $name, string $shape = BlockScaffolder::IN_PLACE, string $namespace = 'App'): array
{
    $base = tempBlockBase();
    scaffolder()->scaffold($name, new GeneratorContext($base, $namespace, 'corex'), false, $shape);

    return [$base, $base . '/Blocks/' . (new Naming())->blockSlugFor($name)];
}

/** @return array<string,mixed> */
function scaffoldedJson(string $path): array
{
    return json_decode((string) file_get_contents($path), true);
}

function lintsClean(string $php): bool
{
    $tmp = tempnam(sys_get_temp_dir(), 'corex_lint_') . '.php';
    file_put_contents($tmp, $php);
    exec(sprintf('php -l %s 2>&1', escapeshellarg($tmp)), $out, $code);

    return $code === 0;
}

it('scaffolds a complete in-place block from one name', function () {
    $base = tempBlockBase();

    $result = scaffolder()->scaffold('TeamMember', new GeneratorContext($base, 'App', 'corex'));

    expect($result->status)->toBe(BlockScaffoldResult::CREATED);

    $blockDir = $base . '/Blocks/team-member';
    foreach (['block.json', 'index.js', 'style.scss', 'parity.json'] as $file) {
        expect(is_file($blockDir . '/' . $file))->toBeTrue("missing {$file}");
    }
    expect(is_file($base . '/Blocks/TeamMemberRenderer.php'))->toBeTrue();
});

it('writes a valid block.json with the dynamic renderer wired', function () {
    [, $dir] = scaffoldedBlock('Pricing');

    $json = scaffoldedJson($dir . '/block.json');

    expect($json['name'])->toBe('corex/pricing')
        ->and($json['apiVersion'])->toBe(3)
        ->and($json['title'])->toBe('Pricing')
        ->and($json['category'])->toBe('corex')
        ->and($json['editorScript'])->toBe('file:./index.js')
        ->and($json['style'])->toBe('file:./style-index.css')
        ->and($json['corex']['renderer'])->toBe('App\\Blocks\\PricingRenderer');
});

it('FR-014: declares the in-place kind by default, with a title typed on the canvas and an anchor', function () {
    [, $dir] = scaffoldedBlock('TeamMember');

    $json = scaffoldedJson($dir . '/block.json');

    expect($json['corex']['editor'])->toBe('in-place')
        ->and($json['attributes'])->toBe(['title' => ['type' => 'string', 'default' => '']])
        ->and($json['supports'])->toBe(['html' => false, 'anchor' => true]);
});

it('FR-014: draws the title with RichText in the editor, and never previews by the server render', function () {
    [, $dir] = scaffoldedBlock('TeamMember');

    $js = (string) file_get_contents($dir . '/index.js');

    expect($js)->toContain("import './style.scss';")
        ->toContain('registerBlockType')
        ->toContain("useBlockProps( { className: 'corex-team-member' } )")
        ->toContain('<RichText')
        ->toContain('className="corex-team-member__title"')
        ->toContain('save: () => null')
        ->not->toContain('server-side-render')
        ->not->toContain('ServerSideRender');
});

it('FR-015: translates every string a person reads in the editor, with the configured text domain', function () {
    [, $dir] = scaffoldedBlock('Hero', BlockScaffolder::IN_PLACE, 'Acme');
    [, $records] = scaffoldedBlock('Hero', BlockScaffolder::RECORDS, 'Acme');
    $base = tempBlockBase();
    scaffolder()->scaffold('Hero', new GeneratorContext($base, 'Acme', 'acme'));

    $js = (string) file_get_contents($base . '/Blocks/hero/index.js');
    $renderer = (string) file_get_contents(dirname($records) . '/HeroRenderer.php');

    expect($js)->toContain("__( 'Title', 'acme' )")
        ->and($renderer)->toContain("esc_html__('Hero', 'corex')")
        ->and((string) file_get_contents($dir . '/index.js'))->toContain("__( 'Title', 'corex' )");
});

it('FR-014: writes the in-place parity.json: the prefix, the attributes and the tree', function () {
    [, $dir] = scaffoldedBlock('TeamMember');

    expect(scaffoldedJson($dir . '/parity.json'))->toBe([
        'prefix' => 'corex-team-member',
        'attributes' => ['title' => 'Team Member'],
        'tree' => [
            [
                'tag' => 'div',
                'classes' => ['corex-team-member'],
                'children' => [['tag' => 'p', 'classes' => ['corex-team-member__title']]],
            ],
        ],
    ]);
});

it('writes a renderer that implements BlockRenderer, is valid PHP and leaves the wrapper to the registrar', function () {
    [$base] = scaffoldedBlock('TeamMember');

    $php = (string) file_get_contents($base . '/Blocks/TeamMemberRenderer.php');

    expect($php)->toContain('namespace App\\Blocks;')
        ->toContain('final class TeamMemberRenderer implements BlockRenderer')
        ->toContain("esc_attr('corex-team-member')")
        ->not->toContain('get_block_wrapper_attributes')
        ->not->toMatch('/\{\{\s*[\w.]+\s*\}\}/')
        ->and(lintsClean($php))->toBeTrue();
});

it('SC-004: the generated renderer prints the tree of the generated parity.json, and nothing for an empty title', function () {
    Functions\stubEscapeFunctions();
    Functions\when('wp_kses_post')->returnArg();
    $namespace = 'ParityProbe' . str_replace('.', '', uniqid('', true));
    [$base, $dir] = scaffoldedBlock('TeamMember', BlockScaffolder::IN_PLACE, $namespace);

    require $base . '/Blocks/TeamMemberRenderer.php';
    $class = $namespace . '\\Blocks\\TeamMemberRenderer';
    $renderer = new $class();
    $fixture = scaffoldedJson($dir . '/parity.json');

    $page = ParityTree::fromMarkup($renderer->render($fixture['attributes'], '', (object) []), $fixture['prefix']);

    expect($page['errors'])->toBe([])
        ->and($page['tree'])->toBe(ParityTree::normalize($fixture['tree']))
        ->and($renderer->render(['title' => '  '], '', (object) []))->toBe('');
});

it('FR-014: with --records, declares the records kind and previews the server render inside the block\'s wrapper, inert', function () {
    [$base, $dir] = scaffoldedBlock('LatestNews', BlockScaffolder::RECORDS);

    $json = scaffoldedJson($dir . '/block.json');
    $js = (string) file_get_contents($dir . '/index.js');
    $php = (string) file_get_contents($base . '/Blocks/LatestNewsRenderer.php');

    expect($json['corex']['editor'])->toBe('records')
        ->and($json['supports'])->toBe(['html' => false, 'anchor' => true])
        ->and(is_file($dir . '/parity.json'))->toBeFalse()
        ->and(is_file($dir . '/style.scss'))->toBeTrue()
        ->and($js)->toContain("import ServerSideRender from '@wordpress/server-side-render';")
        ->toContain('<div { ...useBlockProps() }>')
        ->toContain('<Disabled>')
        ->and($php)->toContain('final class LatestNewsRenderer implements BlockRenderer')
        ->not->toContain('get_block_wrapper_attributes')
        ->and(lintsClean($php))->toBeTrue();
});

it('FR-015: the generated style sheet uses theme.json tokens and logical properties only', function () {
    [, $dir] = scaffoldedBlock('TeamMember');
    $scss = (string) file_get_contents($dir . '/style.scss');
    $palette = array_column(scaffoldedJson(dirname(__DIR__, 3) . '/theme/theme.json')['settings']['color']['palette'], 'slug');

    preg_match_all('/^\s*([a-z-]+)\s*:\s*([^;]+);/m', $scss, $declarations, PREG_SET_ORDER);
    preg_match_all('/--wp--preset--color--([a-z0-9-]+)/', $scss, $colors);

    expect($declarations)->not->toBeEmpty();
    foreach ($declarations as [, $property, $value]) {
        expect($value)->toMatch('/^var\(--wp--preset--[a-z-]+--[a-z0-9-]+\)$/', "{$property} is not a token")
            ->and($property)->not->toMatch('/(^|-)(left|right|top|bottom|width|height)($|-)/', "{$property} is physical");
    }
    expect(array_diff($colors[1], $palette))->toBe([]);
    foreach (explode("\n", $scss) as $line) {
        expect(strlen($line))->toBeLessThan(80);
    }
});

it('FR-015: the records shape has a style sheet of its own, a root rule of tokens and logical properties', function () {
    [, $dir] = scaffoldedBlock('LatestNews', BlockScaffolder::RECORDS);
    $scss = (string) file_get_contents($dir . '/style.scss');

    preg_match_all('/^\s*([a-z-]+)\s*:\s*([^;]+);/m', $scss, $declarations, PREG_SET_ORDER);

    expect($scss)->toContain('.corex-latest-news {')
        ->not->toContain('__title')
        ->and($declarations)->not->toBeEmpty();
    foreach ($declarations as [, $property, $value]) {
        expect($value)->toMatch('/^var\(--wp--preset--[a-z-]+--[a-z0-9-]+\)$/', "{$property} is not a token")
            ->and($property)->not->toMatch('/(^|-)(left|right|top|bottom|width|height)($|-)/', "{$property} is physical");
    }
    foreach (explode("\n", $scss) as $line) {
        expect(strlen($line))->toBeLessThan(80);
    }
});

it('honours the configured namespace and prefix', function () {
    $base = tempBlockBase();

    scaffolder()->scaffold('Hero', new GeneratorContext($base, 'Acme\\Site', 'acme'));

    $json = scaffoldedJson($base . '/Blocks/hero/block.json');
    expect($json['name'])->toBe('acme/hero')
        ->and($json['corex']['renderer'])->toBe('Acme\\Site\\Blocks\\HeroRenderer')
        ->and($json['textdomain'])->toBe('acme')
        ->and(scaffoldedJson($base . '/Blocks/hero/parity.json')['prefix'])->toBe('acme-hero');
});

it('skips an existing block unless forced', function () {
    $base = tempBlockBase();
    $context = new GeneratorContext($base, 'App', 'corex');
    $scaffolder = scaffolder();

    $scaffolder->scaffold('Pricing', $context);
    $second = $scaffolder->scaffold('Pricing', $context);

    expect($second->status)->toBe(BlockScaffoldResult::SKIPPED);

    $forced = $scaffolder->scaffold('Pricing', $context, true);
    expect($forced->status)->toBe(BlockScaffoldResult::CREATED);
});

it('FR-014: re-scaffolding an in-place block as records with --force removes its parity.json', function () {
    $base = tempBlockBase();
    $context = new GeneratorContext($base, 'App', 'corex');
    $dir = $base . '/Blocks/pricing';

    scaffolder()->scaffold('Pricing', $context);
    expect(is_file($dir . '/parity.json'))->toBeTrue();

    $result = scaffolder()->scaffold('Pricing', $context, true, BlockScaffolder::RECORDS);

    expect($result->status)->toBe(BlockScaffoldResult::CREATED)
        ->and(scaffoldedJson($dir . '/block.json')['corex']['editor'])->toBe('records')
        ->and(is_file($dir . '/parity.json'))->toBeFalse();
});

it('rejects an invalid block name', function () {
    $context = new GeneratorContext(tempBlockBase(), 'App', 'corex');

    expect(fn () => scaffolder()->scaffold('123-bad', $context))
        ->toThrow(InvalidNameException::class);
});

it('rejects a shape it does not know', function () {
    $context = new GeneratorContext(tempBlockBase(), 'App', 'corex');

    expect(fn () => scaffolder()->scaffold('Pricing', $context, false, 'static'))
        ->toThrow(InvalidArgumentException::class);
});

it('derives slug and title from a class name', function () {
    $naming = new Naming();

    expect($naming->blockSlugFor('TeamMember'))->toBe('team-member');
    expect($naming->titleFor('TeamMember'))->toBe('Team Member');
    expect($naming->blockSlugFor('Hero'))->toBe('hero');
    expect($naming->titleFor('Hero'))->toBe('Hero');
});
