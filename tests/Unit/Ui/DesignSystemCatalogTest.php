<?php

/**
 * Unit tests for the Design Language System catalog (specs 051 + 054: US1, FR-001/002/003).
 * Drift-checks the declared Corex-block entries against the real on-disk corex/* blocks in
 * both directions, and guards the mechanism tagging so a style/core/deferred entry is never
 * counted as a registered block.
 *
 * @package Corex\Tests\Unit\Ui
 */

declare(strict_types=1);

use Corex\Ui\DesignSystemCatalog;

beforeEach(function () {
    $this->catalog = new DesignSystemCatalog();
});

function corexUiRegisteredBlocks(): array
{
    $blocksDir = dirname(__DIR__, 3) . '/addons/corex-ui/src/Blocks';
    $names     = [];

    foreach (glob($blocksDir . '/*/block.json') ?: [] as $file) {
        $json = json_decode((string) file_get_contents($file), true);
        if (is_array($json) && isset($json['name'])) {
            $names[] = (string) $json['name'];
        }
    }

    return $names;
}

it('organizes the UI into the six taxonomy categories', function () {
    foreach ([
        DesignSystemCatalog::FOUNDATION,
        DesignSystemCatalog::COMPONENT,
        DesignSystemCatalog::BLOCK,
        DesignSystemCatalog::PATTERN,
        DesignSystemCatalog::TEMPLATE,
        DesignSystemCatalog::GUIDELINE,
    ] as $category) {
        expect($this->catalog->byCategory($category))->not->toBeEmpty();
    }
});

it('every entry carries a mechanism, and only corex-block entries carry a block name', function () {
    foreach ($this->catalog->entries() as $entry) {
        expect($entry['mechanism'])->toBeString()->not->toBe('');

        if ($entry['mechanism'] === DesignSystemCatalog::MECH_COREX_BLOCK) {
            expect($entry['block'])->toStartWith('corex/');
        } else {
            expect($entry['block'])->toBeNull();
        }
    }
});

it('lists no Corex block that is not a real registered corex/* block (no drift)', function () {
    $real = corexUiRegisteredBlocks();

    foreach ($this->catalog->blockNames() as $name) {
        expect($real)->toContain($name);
    }
});

it('catalogs every registered corex-ui block (no missing block)', function () {
    $catalogued = $this->catalog->blockNames();

    foreach (corexUiRegisteredBlocks() as $name) {
        expect($catalogued)->toContain($name);
    }
});

it('returns the component atoms for the component category', function () {
    $blocks = array_column($this->catalog->byCategory(DesignSystemCatalog::COMPONENT), 'block');

    expect($blocks)->toContain('corex/alert', 'corex/badge');
});

it('classifies the modal as deferred until the block exists (054 US3 flips it)', function () {
    $modal = array_values(array_filter(
        $this->catalog->byCategory(DesignSystemCatalog::COMPONENT),
        static fn (array $e): bool => $e['name'] === 'Modal / dialog',
    ));

    expect($modal)->toHaveCount(1)
        ->and($modal[0]['mechanism'])->toBe(DesignSystemCatalog::MECH_DEFERRED)
        ->and($modal[0]['block'])->toBeNull();
});
