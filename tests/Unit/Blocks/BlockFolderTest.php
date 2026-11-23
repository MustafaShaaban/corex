<?php

/**
 * Unit tests for corex-blocks' one public way to register a folder of blocks (spec 135, FR-032), and
 * for the text domain a block's scripts are bound to (FR-033).
 *
 * Registration runs in a PHP process of its own (tests/Fixtures/Blocks/register-block-folder.php),
 * because it needs `WP_PLUGIN_DIR` and WordPress's block-type classes, which must not stay defined for
 * the rest of the suite.
 *
 * @package Corex\Tests\Unit\Blocks
 */

declare(strict_types=1);

/**
 * A folder of blocks, one folder per block, each with the block.json given.
 *
 * @param array<string, string> $blocks folder name => block.json contents
 */
function blockFolderOf(string $root, string $relative, array $blocks): string
{
    $dir = $root . '/' . $relative;
    mkdir($dir, 0777, true);

    foreach ($blocks as $folder => $json) {
        mkdir($dir . '/' . $folder, 0777, true);
        file_put_contents($dir . '/' . $folder . '/block.json', $json);
    }

    return $dir;
}

function blockFolderRoot(): string
{
    $root = sys_get_temp_dir() . '/corex_block_folder_' . uniqid('', true);
    mkdir($root . '/plugins', 0777, true);

    return str_replace('\\', '/', $root);
}

/**
 * @return array{registered: list<string>, translations: list<array{0: string, 1: string}>}
 */
function registerBlockFolder(string $built, string $source, string $pluginsDir): array
{
    $runner = dirname(__DIR__, 2) . '/Fixtures/Blocks/register-block-folder.php';
    $output = [];

    exec(
        implode(' ', array_map('escapeshellarg', [PHP_BINARY, $runner, $built, $source, $pluginsDir])) . ' 2>&1',
        $output,
        $exit
    );

    $seen = json_decode(implode("\n", $output), true);

    expect($exit)->toBe(0, implode("\n", $output))
        ->and($seen)->toBeArray(implode("\n", $output));

    return $seen;
}

it('FR-032: registers the built folder when it exists, and not the source beside it', function () {
    $root   = blockFolderRoot();
    $built  = blockFolderOf($root, 'plugins/acme-site/build/blocks', ['banner' => '{"name":"acme-site/banner"}']);
    $source = blockFolderOf($root, 'plugins/acme-site/src/Blocks', ['banner' => '{"name":"acme-site/banner-source"}']);

    expect(registerBlockFolder($built, $source, $root . '/plugins')['registered'])->toBe([$built . '/banner']);
});

it('FR-032: registers the source folder when there is no built folder', function () {
    $root   = blockFolderRoot();
    $source = blockFolderOf($root, 'plugins/acme-site/src/Blocks', [
        'banner'  => '{"name":"acme-site/banner"}',
        'example' => '{"name":"acme-site/example"}',
    ]);

    expect(registerBlockFolder($root . '/plugins/acme-site/build/blocks', $source, $root . '/plugins')['registered'])
        ->toBe([$source . '/banner', $source . '/example']);
});

it('FR-032: registers nothing, and raises nothing, for a missing folder or one with no block.json', function () {
    $root  = blockFolderRoot();
    $empty = $root . '/plugins/acme-site/src/Blocks';
    mkdir($empty . '/not-a-block', 0777, true);
    file_put_contents($empty . '/.gitkeep', '');

    expect(registerBlockFolder($root . '/no/build', $root . '/no/source', $root . '/plugins'))
        ->toBe(['registered' => [], 'translations' => []])
        ->and(registerBlockFolder($root . '/no/build', $empty, $root . '/plugins'))
        ->toBe(['registered' => [], 'translations' => []]);
});

it('FR-033: binds a block\'s script translations to its own text domain, and to corex when it names none', function () {
    $root   = blockFolderRoot();
    $source = blockFolderOf($root, 'plugins/acme-site/src/Blocks', [
        'banner'   => '{"name":"acme-site/banner","textdomain":"acme-site"}',
        'untitled' => '{"name":"acme-site/untitled"}',
    ]);

    expect(registerBlockFolder($root . '/plugins/acme-site/build/blocks', $source, $root . '/plugins')['translations'])
        ->toBe([
            ['acme-site-banner-editor-script', 'acme-site'],
            ['acme-site-untitled-editor-script', 'corex'],
        ]);
});

it('FR-033: every CoreX block names corex as its text domain, so none is bound to another', function () {
    $repository = dirname(__DIR__, 3);
    $files      = array_merge(
        glob($repository . '/plugins/*/src/blocks/*/block.json') ?: [],
        glob($repository . '/addons/*/src/Blocks/*/block.json') ?: [],
    );
    $others = [];

    foreach ($files as $file) {
        $metadata = json_decode((string) file_get_contents($file), true);

        if (($metadata['textdomain'] ?? null) !== 'corex') {
            $others[] = $file;
        }
    }

    expect($files)->not->toBe([])
        ->and($others)->toBe([]);
});
