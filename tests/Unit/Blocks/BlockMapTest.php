<?php

/**
 * Unit tests for block discovery (spec US1: FR-001–FR-004).
 *
 * @package Corex\Tests\Unit\Blocks
 */

declare(strict_types=1);

use Corex\Blocks\BlockMap;
use Corex\Support\BootLogger;

function blocksFixtureDir(): string
{
    $root = sys_get_temp_dir() . '/corex_blocks_' . uniqid('', true);
    mkdir($root . '/valid', 0777, true);
    file_put_contents($root . '/valid/block.json', '{"name":"corex/valid","title":"Valid"}');
    mkdir($root . '/not-a-block', 0777, true); // no block.json
    mkdir($root . '/malformed', 0777, true);
    file_put_contents($root . '/malformed/block.json', '{ this is not json');
    mkdir($root . '/dupe-a', 0777, true);
    file_put_contents($root . '/dupe-a/block.json', '{"name":"corex/dupe"}');
    mkdir($root . '/dupe-b', 0777, true);
    file_put_contents($root . '/dupe-b/block.json', '{"name":"corex/dupe"}');

    return $root;
}

it('discovers valid blocks, skips non-blocks, and returns their metadata', function () {
    $map = new BlockMap(new BootLogger(debug: false));

    $blocks = $map->discover(blocksFixtureDir());
    $names = array_column($blocks, 'name');
    $valid = array_values(array_filter($blocks, fn ($b) => $b['name'] === 'corex/valid'));

    expect($names)->toContain('corex/valid')
        ->and($blocks)->toHaveCount(2) // valid + one dupe (first wins); malformed + non-block skipped
        ->and($valid[0]['metadata']['title'] ?? null)->toBe('Valid');
});

it('logs and skips a malformed block.json and de-dupes by name', function () {
    $logger = new BootLogger(debug: false);

    $names = array_column((new BlockMap($logger))->discover(blocksFixtureDir()), 'name');

    expect(array_count_values($names)['corex/dupe'])->toBe(1) // de-duped
        ->and($logger->messages())->not->toBeEmpty();          // malformed + duplicate logged
});

it('returns an empty set for a directory with no blocks', function () {
    $empty = sys_get_temp_dir() . '/corex_blocks_empty_' . uniqid('', true);
    mkdir($empty);

    expect((new BlockMap(new BootLogger(debug: false)))->discover($empty))->toBe([]);
});

it('returns an empty set when the blocks directory does not exist', function () {
    expect((new BlockMap(new BootLogger(debug: false)))->discover('/no/such/blocks'))->toBe([]);
});
