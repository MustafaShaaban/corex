<?php

/**
 * Unit tests for the block scaffolder (make:block): a complete, valid dynamic block
 * from one name — block.json + index.js + style.scss + the PHP renderer.
 *
 * @package Corex\Tests\Unit\Cli
 */

declare(strict_types=1);

use Corex\Cli\Generators\BlockScaffolder;
use Corex\Cli\Generators\BlockScaffoldResult;
use Corex\Cli\Generators\GeneratorContext;
use Corex\Cli\Generators\StubRenderer;
use Corex\Cli\Support\InvalidNameException;
use Corex\Cli\Support\Naming;

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

it('scaffolds a complete dynamic block from one name', function () {
    $base = tempBlockBase();
    $context = new GeneratorContext($base, 'App', 'corex');

    $result = scaffolder()->scaffold('TeamMember', $context);

    expect($result->status)->toBe(BlockScaffoldResult::CREATED);

    $blockDir = $base . '/Blocks/team-member';
    expect(is_file($blockDir . '/block.json'))->toBeTrue();
    expect(is_file($blockDir . '/index.js'))->toBeTrue();
    expect(is_file($blockDir . '/style.scss'))->toBeTrue();
    expect(is_file($base . '/Blocks/TeamMemberRenderer.php'))->toBeTrue();
});

it('writes a valid block.json with the dynamic renderer wired', function () {
    $base = tempBlockBase();
    $context = new GeneratorContext($base, 'App', 'corex');

    scaffolder()->scaffold('Pricing', $context);

    $json = json_decode((string) file_get_contents($base . '/Blocks/pricing/block.json'), true);

    expect($json)->toBeArray();
    expect($json['name'])->toBe('corex/pricing');
    expect($json['apiVersion'])->toBe(3);
    expect($json['title'])->toBe('Pricing');
    expect($json['category'])->toBe('corex');
    expect($json['editorScript'])->toBe('file:./index.js');
    expect($json['style'])->toBe('file:./style-index.css');
    expect($json['corex']['renderer'])->toBe('App\\Blocks\\PricingRenderer');
});

it('writes a renderer that implements BlockRenderer and is valid PHP', function () {
    $base = tempBlockBase();
    $context = new GeneratorContext($base, 'App', 'corex');

    scaffolder()->scaffold('TeamMember', $context);

    $php = (string) file_get_contents($base . '/Blocks/TeamMemberRenderer.php');

    expect($php)->toContain('namespace App\\Blocks;');
    expect($php)->toContain('final class TeamMemberRenderer implements BlockRenderer');
    expect($php)->toContain("esc_attr('corex-team-member')");
    // No unresolved stub placeholders leaked into the output.
    expect($php)->not->toMatch('/\{\{\s*[\w.]+\s*\}\}/');

    // Lints clean.
    $tmp = tempnam(sys_get_temp_dir(), 'corex_lint_') . '.php';
    file_put_contents($tmp, $php);
    exec(sprintf('php -l %s 2>&1', escapeshellarg($tmp)), $out, $code);
    expect($code)->toBe(0);
});

it('imports the SCSS in index.js so the build compiles a conditional stylesheet', function () {
    $base = tempBlockBase();
    $context = new GeneratorContext($base, 'App', 'corex');

    scaffolder()->scaffold('TeamMember', $context);

    $js = (string) file_get_contents($base . '/Blocks/team-member/index.js');

    expect($js)->toContain("import './style.scss';");
    expect($js)->toContain('registerBlockType');
    expect($js)->toContain('ServerSideRender');
});

it('honours the configured namespace and prefix', function () {
    $base = tempBlockBase();
    $context = new GeneratorContext($base, 'Acme\\Site', 'acme');

    scaffolder()->scaffold('Hero', $context);

    $json = json_decode((string) file_get_contents($base . '/Blocks/hero/block.json'), true);
    expect($json['name'])->toBe('acme/hero');
    expect($json['corex']['renderer'])->toBe('Acme\\Site\\Blocks\\HeroRenderer');
    expect($json['textdomain'])->toBe('acme');
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

it('rejects an invalid block name', function () {
    $context = new GeneratorContext(tempBlockBase(), 'App', 'corex');

    expect(fn () => scaffolder()->scaffold('123-bad', $context))
        ->toThrow(InvalidNameException::class);
});

it('derives slug and title from a class name', function () {
    $naming = new Naming();

    expect($naming->blockSlugFor('TeamMember'))->toBe('team-member');
    expect($naming->titleFor('TeamMember'))->toBe('Team Member');
    expect($naming->blockSlugFor('Hero'))->toBe('hero');
    expect($naming->titleFor('Hero'))->toBe('Hero');
});
