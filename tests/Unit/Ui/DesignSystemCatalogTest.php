<?php

/**
 * Unit tests for the Design Language System catalog (spec 051: US1, FR-001/FR-002).
 * Drift-checks the declared Block entries against the real on-disk corex/* blocks.
 *
 * @package Corex\Tests\Unit\Ui
 */

declare(strict_types=1);

use Corex\Ui\DesignSystemCatalog;

beforeEach(function () {
    $this->catalog = new DesignSystemCatalog();
});

it('organizes the UI into the five taxonomy categories', function () {
    foreach ([
        DesignSystemCatalog::COMPONENT,
        DesignSystemCatalog::BLOCK,
        DesignSystemCatalog::PATTERN,
        DesignSystemCatalog::TEMPLATE,
        DesignSystemCatalog::GUIDELINE,
    ] as $category) {
        expect($this->catalog->byCategory($category))->not->toBeEmpty();
    }
});

it('lists no block that is not a real registered corex/* block (no drift)', function () {
    $blocksDir = dirname(__DIR__, 3) . '/addons/corex-ui/src/Blocks';

    $real = [];
    foreach (glob($blocksDir . '/*/block.json') ?: [] as $file) {
        $json = json_decode((string) file_get_contents($file), true);
        if (is_array($json) && isset($json['name'])) {
            $real[] = (string) $json['name'];
        }
    }

    foreach ($this->catalog->blockNames() as $name) {
        expect($real)->toContain($name);
    }
});

it('returns the entries for a given category', function () {
    $components = $this->catalog->byCategory(DesignSystemCatalog::COMPONENT);

    expect(array_column($components, 'block'))->toContain('corex/alert', 'corex/badge');
});
