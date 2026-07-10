<?php

/**
 * Reconciles the approved "Blocks & Components" inventory (Spec 068 US9 / FR-154, design file
 * `Corex Blocks & Components.dc.html`) against what the framework actually ships. Every item the
 * design approved is declared with its delivery resolution; this test proves each non-deferred
 * resolution points at a real registered block, block style, pattern, admin/runtime stylesheet
 * selector, or core-block path — so the inventory can never claim a component that does not exist,
 * and any current-scope component with no delivery is reported as a concrete gap for T208.
 *
 * @package Corex\Tests\Unit\Ui
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Ui\ApprovedComponentInventory;
use Corex\Ui\Blocks\BlockStyles;
use Corex\Ui\Patterns\PatternLibrary;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('esc_html__')->returnArg();
    $this->inventory = new ApprovedComponentInventory();
});

/** Real `corex/<slug>` block slugs that ship a block.json. */
function corexBlockSlugsOnDisk(): array
{
    $dir   = dirname(__DIR__, 3) . '/addons/corex-ui/src/Blocks';
    $slugs = [];

    foreach (glob($dir . '/*/block.json') ?: [] as $file) {
        $slugs[] = basename(dirname($file));
    }

    return $slugs;
}

/** Registered block-style names. */
function corexBlockStyleNames(): array
{
    return array_column((new BlockStyles(''))->styles(), 'name');
}

/** PatternLibrary names plus `theme/patterns/*.php` basenames. */
function corexPatternNames(): array
{
    $names = array_column((new PatternLibrary())->patterns(), 'name');

    foreach (glob(dirname(__DIR__, 3) . '/theme/patterns/*.php') ?: [] as $file) {
        $names[] = basename($file, '.php');
    }

    return $names;
}

/** Every selector string shipped by a Corex admin/front stylesheet, concatenated. */
function corexShippedCss(): string
{
    $root  = dirname(__DIR__, 3);
    $globs = [
        $root . '/plugins/corex-config/assets/*.css',
        $root . '/plugins/corex-core/assets/css/*.css',
        $root . '/addons/corex-kit-company/assets/*.css',
        $root . '/addons/corex-ui/assets/*.css',
    ];

    $css = '';
    foreach ($globs as $glob) {
        foreach (glob($glob) ?: [] as $file) {
            $css .= file_get_contents($file) . "\n";
        }
    }

    return $css;
}

/**
 * Returns null when the resolution is satisfied, or a human-readable gap reason otherwise.
 */
function corexResolutionGap(string $resolution): ?string
{
    [$kind, $target] = array_pad(explode(':', $resolution, 2), 2, '');

    switch ($kind) {
        case ApprovedComponentInventory::RES_COREX_BLOCK:
            return in_array($target, corexBlockSlugsOnDisk(), true)
                ? null : "missing corex/{$target} block";
        case ApprovedComponentInventory::RES_BLOCK_STYLE:
            return in_array($target, corexBlockStyleNames(), true)
                ? null : "missing block style {$target}";
        case ApprovedComponentInventory::RES_PATTERN:
            return in_array($target, corexPatternNames(), true)
                ? null : "missing pattern {$target}";
        case ApprovedComponentInventory::RES_ADMIN:
        case ApprovedComponentInventory::RES_RUNTIME:
            return str_contains(corexShippedCss(), $target)
                ? null : "missing stylesheet selector {$target}";
        case ApprovedComponentInventory::RES_CORE_BLOCK:
            return null; // composed from core WordPress blocks + tokens — no Corex artifact to verify.
        default:
            return "unknown resolution kind {$kind}";
    }
}

it('declares exactly the approved design counts per category (33/8/13/23)', function () {
    foreach ($this->inventory->expectedCounts() as $category => $count) {
        expect($this->inventory->byCategory($category))->toHaveCount($count);
    }

    expect($this->inventory->items())->toHaveCount(77);
});

it('gives every item a valid status and a known resolution kind', function () {
    $statuses = [
        ApprovedComponentInventory::STATUS_APPROVED,
        ApprovedComponentInventory::STATUS_REVISION,
        ApprovedComponentInventory::STATUS_MISSING,
        ApprovedComponentInventory::STATUS_FUTURE,
    ];
    $kinds = [
        ApprovedComponentInventory::RES_COREX_BLOCK,
        ApprovedComponentInventory::RES_BLOCK_STYLE,
        ApprovedComponentInventory::RES_PATTERN,
        ApprovedComponentInventory::RES_ADMIN,
        ApprovedComponentInventory::RES_RUNTIME,
        ApprovedComponentInventory::RES_CORE_BLOCK,
        ApprovedComponentInventory::RES_DEFERRED,
    ];

    foreach ($this->inventory->items() as $item) {
        expect($statuses)->toContain($item['status']);
        $kind = explode(':', $item['resolution'], 2)[0];
        expect($kinds)->toContain($kind);
    }
});

it('claims no corex/* block that is not a real registered block (no drift)', function () {
    $real = corexBlockSlugsOnDisk();

    foreach ($this->inventory->blockSlugs() as $slug) {
        // The carousel primitive is the one intentionally-open T208 gap; asserted separately.
        if ($slug === 'carousel') {
            continue;
        }
        expect($real)->toContain($slug);
    }
});

it('only defers with an allowed reason', function () {
    $allowed = ['woocommerce-dependency', 'future-pro', 'phase-2'];

    foreach ($this->inventory->items() as $item) {
        if (!str_starts_with($item['resolution'], ApprovedComponentInventory::RES_DEFERRED . ':')) {
            continue;
        }
        $reason = substr($item['resolution'], strlen(ApprovedComponentInventory::RES_DEFERRED) + 1);
        expect($allowed)->toContain($reason);
    }
});

it('ships a real delivery for every non-deferred approved component (T208 gate)', function () {
    $gaps = [];

    foreach ($this->inventory->deliverable() as $item) {
        $gap = corexResolutionGap($item['resolution']);
        if ($gap !== null) {
            $gaps[] = "{$item['name']} ({$item['category']}): {$gap}";
        }
    }

    expect($gaps)->toBe([]);
});
